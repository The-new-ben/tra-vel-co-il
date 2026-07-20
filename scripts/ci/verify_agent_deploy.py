#!/usr/bin/env python3
"""Bounded, secret-safe production verification for an Agent Core deployment."""

from __future__ import annotations

import argparse
import base64
import hmac
import json
import os
import secrets
import socket
import ssl
import sys
import time
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Callable
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlsplit, urlunsplit
from urllib.request import HTTPSHandler, HTTPRedirectHandler, Request, build_opener


MAX_RESPONSE_BYTES = 1_048_576
SHA256_LENGTH = 64


class VerificationError(RuntimeError):
    """A safe-to-report verification failure that never includes response bodies."""


class NoRedirectHandler(HTTPRedirectHandler):
    """Reject redirects so a missing REST route cannot become a homepage success."""

    def redirect_request(self, request, file_pointer, code, message, headers, new_url):  # noqa: ANN001
        return None


@dataclass(frozen=True)
class VerificationConfig:
    site_url: str
    username: str
    app_password: str
    attempts: int = 6
    request_timeout_seconds: float = 20.0
    base_delay_seconds: float = 5.0
    max_delay_seconds: float = 5.0


@dataclass(frozen=True)
class JsonResponse:
    data: dict[str, Any]
    status: int
    content_type: str
    byte_count: int

    def summary(self) -> str:
        return f"status={self.status} content_type={self.content_type} bytes={self.byte_count}"


def require(condition: bool, message: str) -> None:
    if not condition:
        raise VerificationError(message)


def load_object(path: Path, label: str) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as error:
        raise VerificationError(f"{label} is unavailable or invalid JSON.") from error
    require(isinstance(value, dict), f"{label} must contain a JSON object.")
    return value


def normalized_site_url(value: str) -> str:
    parsed = urlsplit(value.strip())
    require(parsed.scheme == "https", "WP_SITE_URL must use HTTPS.")
    require(bool(parsed.netloc), "WP_SITE_URL must include a host.")
    require(parsed.username is None and parsed.password is None, "WP_SITE_URL must not contain credentials.")
    require(not parsed.query and not parsed.fragment, "WP_SITE_URL must not contain a query or fragment.")
    path = parsed.path.rstrip("/")
    return urlunsplit((parsed.scheme, parsed.netloc, path, "", ""))


def validate_config(config: VerificationConfig) -> VerificationConfig:
    site_url = normalized_site_url(config.site_url)
    require(bool(config.username), "WP_USERNAME is required.")
    require(bool(config.app_password), "WP_APP_PASSWORD is required.")
    require(1 <= config.attempts <= 12, "Verification attempts must be between 1 and 12.")
    require(1.0 <= config.request_timeout_seconds <= 60.0, "Request timeout must be between 1 and 60 seconds.")
    require(0.0 <= config.base_delay_seconds <= 30.0, "Base retry delay must be between 0 and 30 seconds.")
    require(
        config.base_delay_seconds <= config.max_delay_seconds <= 60.0,
        "Maximum retry delay must be at least the base delay and no more than 60 seconds.",
    )
    return VerificationConfig(
        site_url=site_url,
        username=config.username,
        app_password=config.app_password,
        attempts=config.attempts,
        request_timeout_seconds=config.request_timeout_seconds,
        base_delay_seconds=config.base_delay_seconds,
        max_delay_seconds=config.max_delay_seconds,
    )


def valid_sha256(value: Any) -> bool:
    text = str(value)
    return len(text) == SHA256_LENGTH and all(character in "0123456789abcdef" for character in text)


def validate_local_identity(manifest: dict[str, Any], deployed: dict[str, Any]) -> None:
    for key in ("version", "sha256", "content_sha256"):
        require(isinstance(manifest.get(key), str) and bool(manifest[key]), f"Manifest {key} is missing.")
        require(isinstance(deployed.get(key), str) and bool(deployed[key]), f"Deployment receipt {key} is missing.")
        require(
            hmac.compare_digest(manifest[key], deployed[key]),
            f"Deployment receipt {key} does not match the package manifest.",
        )
    require(valid_sha256(manifest["sha256"]), "Manifest package SHA256 is invalid.")
    require(valid_sha256(manifest["content_sha256"]), "Manifest content SHA256 is invalid.")


def basic_authorization(username: str, app_password: str) -> str:
    token = base64.b64encode(f"{username}:{app_password}".encode("utf-8")).decode("ascii")
    return f"Basic {token}"


def safe_content_type(value: str) -> str:
    media_type = value.split(";", 1)[0].strip().lower()
    return media_type if re.fullmatch(r"[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+", media_type) else "missing-or-invalid"


def decode_json_response(label: str, content_type: str, body: bytes) -> dict[str, Any]:
    media_type = safe_content_type(content_type)
    metadata = f"status=200 content_type={media_type} bytes={len(body)}"
    require(
        media_type == "application/json" or media_type.endswith("+json"),
        f"{label} returned a non-JSON response ({metadata}).",
    )
    require(bool(body), f"{label} returned an empty response ({metadata}).")
    require(len(body) <= MAX_RESPONSE_BYTES, f"{label} response exceeded the size limit ({metadata}).")
    try:
        value = json.loads(body.decode("utf-8-sig"))
    except (UnicodeError, json.JSONDecodeError) as error:
        raise VerificationError(f"{label} returned invalid JSON ({metadata}).") from error
    require(isinstance(value, dict), f"{label} must return a JSON object ({metadata}).")
    return value


def fetch_json(opener, url: str, label: str, timeout: float, authorization: str = "") -> JsonResponse:  # noqa: ANN001
    headers = {
        "Accept": "application/json",
        "Cache-Control": "no-cache, no-store",
        "Pragma": "no-cache",
        "User-Agent": "tra-vel-agent-deploy-verifier/1.0",
    }
    if authorization:
        headers["Authorization"] = authorization
    request = Request(url, headers=headers, method="GET")
    try:
        with opener.open(request, timeout=timeout) as response:
            status = int(response.getcode())
            require(status == 200, f"{label} returned HTTP {status}.")
            body = response.read(MAX_RESPONSE_BYTES + 1)
            content_type = safe_content_type(response.headers.get("Content-Type", ""))
            return JsonResponse(
                data=decode_json_response(label, content_type, body),
                status=status,
                content_type=content_type,
                byte_count=len(body),
            )
    except HTTPError as error:
        body = error.read(MAX_RESPONSE_BYTES + 1)
        content_type = safe_content_type(error.headers.get("Content-Type", ""))
        status = int(error.code)
        error.close()
        raise VerificationError(
            f"{label} returned HTTP {status} (status={status} content_type={content_type} bytes={len(body)})."
        ) from error
    except (TimeoutError, socket.timeout) as error:
        raise VerificationError(f"{label} timed out (status=unavailable content_type=unavailable bytes=0).") from error
    except URLError as error:
        reason_name = type(error.reason).__name__
        raise VerificationError(
            f"{label} transport failed ({reason_name}; status=unavailable content_type=unavailable bytes=0)."
        ) from error
    except OSError as error:
        raise VerificationError(
            f"{label} transport failed ({type(error).__name__}; status=unavailable content_type=unavailable bytes=0)."
        ) from error


def validate_remote_contract(
    health: dict[str, Any],
    queue: dict[str, Any],
    manifest: dict[str, Any],
) -> None:
    require(health.get("ok") is True, "Health contract is not ready.")
    require(health.get("contract_version") == "1.0.0", "Health contract version is unexpected.")
    capabilities = health.get("capabilities")
    require(isinstance(capabilities, dict), "Health capabilities are missing.")
    require(capabilities.get("account_plan_history") is True, "Account plan history is not ready.")
    require(capabilities.get("assisted_quote_cases") is True, "Assisted quote cases are not ready.")
    require(capabilities.get("operator_queue") is True, "Operator queue is not ready.")
    require(capabilities.get("commercial_intents") is True, "Commercial intents are not ready.")
    require(capabilities.get("durable_commercial_handoffs") is True, "Durable commercial handoffs are not ready.")
    require(capabilities.get("lead_contact_capture") is True, "Consent-gated lead contact capture is not ready.")
    require(capabilities.get("sourced_assisted_proposals") is True, "Sourced assisted proposals are not ready.")
    require(capabilities.get("audited_proposal_actions") is True, "Audited proposal actions are not ready.")
    require(capabilities.get("no_login_scoped_sessions") is True, "No-login scoped sessions are not ready.")
    require(capabilities.get("customer_trip_cockpit") is True, "Customer Trip Cockpit is not ready.")
    require(capabilities.get("supplier_search") is False, "Health contract overstates supplier search.")
    require(capabilities.get("supplier_dispatch") is False, "Health contract overstates supplier dispatch.")
    require(capabilities.get("proposal_generation") is False, "Health contract overstates proposal generation.")
    require(capabilities.get("payment_execution") is False, "Health contract overstates payment execution.")
    require(capabilities.get("booking_execution") is False, "Health contract overstates booking execution.")
    require(capabilities.get("reservation_execution") is False, "Health contract overstates reservation execution.")
    require(capabilities.get("ticket_issuance") is False, "Health contract overstates ticket issuance.")

    provider = health.get("provider")
    require(isinstance(provider, dict), "Provider health is missing.")
    model = provider.get("model")
    require(isinstance(model, str) and model.strip() != "", "Provider health does not disclose the active model identifier.")
    require(
        provider.get("model_source") in ("filter", "option", "default"),
        "Provider health does not disclose a truthful model_source.",
    )
    forbidden_provider_fields = {"api_key", "apikey", "key", "secret", "authorization", "credential", "bearer"}
    require(
        not forbidden_provider_fields.intersection(str(name).lower() for name in provider),
        "Provider health must never disclose credential material.",
    )

    agent_store = health.get("agent_store")
    require(isinstance(agent_store, dict), "Agent store health is missing.")
    expected_agent_store = {
        "schema_version": "1.2.0",
        "installed_schema_version": "1.2.0",
        "expected_tables": 4,
        "ready_tables": 4,
        "transactional_tables": 4,
        "required_indexes": 9,
        "ready_indexes": 9,
        "required_indexes_ready": True,
        "tables_ready": True,
    }
    for key, expected in expected_agent_store.items():
        require(agent_store.get(key) == expected, f"Agent store {key} is not ready.")

    quote_store = health.get("quote_case_store")
    require(isinstance(quote_store, dict), "Quote-case store health is missing.")
    expected_quote_store = {
        "schema_version": "1.1.0",
        "installed_schema_version": "1.1.0",
        "tables_ready": True,
        "expected_tables": 4,
        "ready_tables": 4,
        "transactional_tables": 4,
        "required_indexes": 7,
        "ready_indexes": 7,
        "required_indexes_ready": True,
        "required_supporting_indexes": 1,
        "ready_supporting_indexes": 1,
        "supporting_indexes_ready": True,
    }
    for key, expected in expected_quote_store.items():
        require(quote_store.get(key) == expected, f"Quote-case store {key} is not ready.")

    proposal_store = health.get("assisted_proposal_store")
    require(isinstance(proposal_store, dict), "Assisted-proposal store health is missing.")
    expected_proposal_store = {
        "schema_version": "1.0.0",
        "installed_schema_version": "1.0.0",
        "idempotency_days": 7,
        "max_proposals_per_case": 12,
        "max_revisions_per_proposal": 20,
        "max_snapshot_bytes": 524288,
        "expected_tables": 5,
        "ready_tables": 5,
        "transactional_tables": 5,
        "required_indexes": 9,
        "ready_indexes": 9,
        "required_indexes_ready": True,
        "inspection_errors": [],
        "tables_ready": True,
    }
    for key, expected in expected_proposal_store.items():
        require(proposal_store.get(key) == expected, f"Assisted-proposal store {key} is not ready.")

    commercial_store = health.get("commercial_intent_store")
    require(isinstance(commercial_store, dict), "Commercial-intent store health is missing.")
    expected_commercial_store = {
        "schema_version": "1.1.0",
        "installed_schema_version": "1.1.0",
        "tables_ready": True,
        "expected_tables": 3,
        "ready_tables": 3,
        "transactional_tables": 3,
        "required_indexes": 5,
        "ready_indexes": 5,
        "required_indexes_ready": True,
    }
    for key, expected in expected_commercial_store.items():
        require(commercial_store.get(key) == expected, f"Commercial-intent store {key} is not ready.")

    capability_store = health.get("vip_capability_session_store")
    require(isinstance(capability_store, dict), "Capability-session store health is missing.")
    expected_capability_store = {
        "schema_version": "1.1.0",
        "installed_schema_version": "1.1.0",
        "grant_retention_days": 30,
        "session_retention_days": 30,
        "idempotency_days": 2,
        "session_ttl_seconds": 1800,
        "expected_tables": 4,
        "ready_tables": 4,
        "transactional_tables": 4,
        "required_indexes": 7,
        "ready_indexes": 7,
        "required_indexes_ready": True,
        "tables_ready": True,
    }
    for key, expected in expected_capability_store.items():
        require(capability_store.get(key) == expected, f"Capability-session store {key} is not ready.")

    customer_cockpit_store = health.get("customer_trip_cockpit_store")
    require(isinstance(customer_cockpit_store, dict), "Customer Trip Cockpit store health is missing.")
    expected_customer_cockpit_store = {
        "schema_version": "1.0.0",
        "installed_schema_version": "1.0.0",
        "retention_days": 400,
        "max_projection_bytes": 524288,
        "expected_tables": 3,
        "ready_tables": 3,
        "transactional_tables": 3,
        "required_indexes": 13,
        "ready_indexes": 13,
        "required_indexes_ready": True,
        "inspection_errors": [],
        "tables_ready": True,
    }
    for key, expected in expected_customer_cockpit_store.items():
        require(customer_cockpit_store.get(key) == expected, f"Customer Trip Cockpit store {key} is not ready.")

    require(isinstance(queue.get("cases"), list), "Operator queue cases are missing.")
    require(isinstance(queue.get("meta"), dict), "Operator queue metadata is missing.")
    require(health.get("plugin_version") == manifest.get("version"), "Health endpoint still serves another plugin version.")


def build_opener_without_redirects():
    return build_opener(NoRedirectHandler(), HTTPSHandler(context=ssl.create_default_context()))


def verify_remote_once(
    config: VerificationConfig,
    manifest: dict[str, Any],
    deployed: dict[str, Any],
    attempt: int,
) -> dict[str, Any]:
    del deployed
    opener = build_opener_without_redirects()
    cache_token = f"{os.environ.get('GITHUB_RUN_ID', 'local')}-{attempt}-{secrets.token_hex(8)}"
    health_url = f"{config.site_url}/wp-json/tra-vel-agent/v1/health?{urlencode({'deployment_check': cache_token})}"
    queue_url = f"{config.site_url}/wp-json/tra-vel-agent/v1/operator/quote-cases?{urlencode({'per_page': 1, 'deployment_check': cache_token})}"
    health_response = fetch_json(opener, health_url, "Agent health endpoint", config.request_timeout_seconds)
    queue_response = fetch_json(
        opener,
        queue_url,
        "Operator queue endpoint",
        config.request_timeout_seconds,
        authorization=basic_authorization(config.username, config.app_password),
    )
    try:
        validate_remote_contract(health_response.data, queue_response.data, manifest)
    except VerificationError as error:
        raise VerificationError(
            f"{error} health[{health_response.summary()}] queue[{queue_response.summary()}]"
        ) from error
    return health_response.data


VerifyOnce = Callable[[VerificationConfig, dict[str, Any], dict[str, Any], int], dict[str, Any]]


def run_verification(
    config: VerificationConfig,
    manifest: dict[str, Any],
    deployed: dict[str, Any],
    verify_once: VerifyOnce = verify_remote_once,
    sleep: Callable[[float], None] = time.sleep,
    report: Callable[[str], None] = print,
) -> bool:
    last_error = "verification did not run"
    for attempt in range(1, config.attempts + 1):
        try:
            health = verify_once(config, manifest, deployed, attempt)
            provider = health.get("provider") if isinstance(health.get("provider"), dict) else {}
            report(
                f"Agent Core {health.get('plugin_version')} health and operator queue passed "
                f"on attempt {attempt}/{config.attempts}; provider_configured={provider.get('configured') is True}"
            )
            return True
        except VerificationError as error:
            last_error = str(error)
        except Exception as error:  # Fail closed without rendering request headers or response bodies.
            last_error = f"Verifier failed safely ({type(error).__name__})."

        if attempt < config.attempts:
            delay = min(config.base_delay_seconds * (2 ** (attempt - 1)), config.max_delay_seconds)
            report(f"Agent Core verification attempt {attempt}/{config.attempts} is not ready: {last_error} Retrying in {delay:g}s.")
            sleep(delay)

    report(f"Agent Core verification exhausted {config.attempts} attempts: {last_error}")
    return False


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--manifest", type=Path, default=Path("dist/agent-core-manifest.json"))
    parser.add_argument("--deployment", type=Path, default=Path(os.environ.get("AGENT_DEPLOY_RESULT_FILE", "agent-deploy-result.json")))
    parser.add_argument("--attempts", type=int, default=6)
    parser.add_argument("--request-timeout-seconds", type=float, default=20.0)
    parser.add_argument("--base-delay-seconds", type=float, default=5.0)
    parser.add_argument("--max-delay-seconds", type=float, default=5.0)
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = parse_args(sys.argv[1:] if argv is None else argv)
    try:
        config = validate_config(
            VerificationConfig(
                site_url=os.environ.get("WP_SITE_URL", ""),
                username=os.environ.get("WP_USERNAME", ""),
                app_password=os.environ.get("WP_APP_PASSWORD", ""),
                attempts=args.attempts,
                request_timeout_seconds=args.request_timeout_seconds,
                base_delay_seconds=args.base_delay_seconds,
                max_delay_seconds=args.max_delay_seconds,
            )
        )
        manifest = load_object(args.manifest, "Agent Core manifest")
        deployed = load_object(args.deployment, "Agent Core deployment receipt")
        validate_local_identity(manifest, deployed)
        return 0 if run_verification(config, manifest, deployed) else 1
    except VerificationError as error:
        print(f"Agent Core verification could not start: {error}", file=sys.stderr)
        return 1
    except Exception as error:  # Never leak environment values through diagnostics.
        print(f"Agent Core verification failed safely ({type(error).__name__}).", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
