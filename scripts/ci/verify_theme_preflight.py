#!/usr/bin/env python3
"""Fail closed before a Tra-Vel theme mutation when live dependencies are unsafe."""

from __future__ import annotations

import argparse
import json
import re
import secrets
import ssl
import sys
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urljoin, urlparse
from urllib.request import HTTPSHandler, HTTPRedirectHandler, Request, build_opener


SEMVER = re.compile(r"^(\d+)\.(\d+)\.(\d+)(?:([-+])([A-Za-z0-9.-]+))?$")
SAFE_PATH = re.compile(r"^/[A-Za-z0-9._~!$&'()*+,;=:@%/-]+$")
MAX_BODY_BYTES = 1_048_576
ASSISTED_PROPOSAL_STORE_HEALTH = {
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


class PreflightError(RuntimeError):
    """A release requirement or live prerequisite failed."""


class NoRedirectHandler(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):  # noqa: ANN001
        return None


def require(condition: bool, message: str) -> None:
    if not condition:
        raise PreflightError(message)


def semver_parts(value: Any, label: str) -> tuple[tuple[int, int, int], str | None]:
    match = SEMVER.fullmatch(str(value or ""))
    require(match is not None, f"{label} is not a valid semantic version.")
    assert match is not None
    return (int(match[1]), int(match[2]), int(match[3])), match[5]


def version_at_least(current: Any, minimum: Any) -> bool:
    current_numbers, current_suffix = semver_parts(current, "Current version")
    minimum_numbers, minimum_suffix = semver_parts(minimum, "Minimum version")
    if current_numbers != minimum_numbers:
        return current_numbers > minimum_numbers
    if minimum_suffix is None:
        return current_suffix is None
    if current_suffix is None:
        return True
    return current_suffix >= minimum_suffix


def load_requirements(path: Path) -> dict[str, Any]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError, TypeError) as error:
        raise PreflightError(f"Release requirements are unreadable or invalid: {error}") from error
    require(isinstance(data, dict), "Release requirements must be a JSON object.")
    require(data.get("contract_version") == "1.0.0", "Release requirements contract version is unsupported.")

    theme = data.get("theme")
    require(isinstance(theme, dict), "Theme release requirements are missing.")
    require(theme.get("slug") == "tra-vel-v2", "Theme release requirement has an unexpected slug.")
    semver_parts(theme.get("version"), "Theme version")
    require(re.fullmatch(r"\d+\.\d+", str(theme.get("requires_wordpress", ""))) is not None, "WordPress minimum is invalid.")
    require(re.fullmatch(r"\d+\.\d+", str(theme.get("requires_php", ""))) is not None, "PHP minimum is invalid.")

    gateway = data.get("deploy_gateway")
    require(isinstance(gateway, dict), "Deployment gateway requirement is missing.")
    semver_parts(gateway.get("min_version"), "Deployment gateway minimum version")

    http = data.get("public_http")
    require(isinstance(http, dict), "Public HTTP requirements are missing.")
    prefix = str(http.get("unknown_path_prefix", ""))
    require(SAFE_PATH.fullmatch(prefix) is not None and prefix.endswith("-"), "Unknown-path prefix is invalid.")
    require(http.get("unknown_status") == 404, "Unknown public routes must require HTTP 404.")
    require(http.get("redirects_allowed") is False, "Unknown public routes must forbid redirects.")

    dependencies = data.get("dependencies")
    require(isinstance(dependencies, list) and len(dependencies) == 1, "Exactly one required runtime dependency must be declared.")
    dependency = dependencies[0]
    require(isinstance(dependency, dict) and dependency.get("id") == "tra-vel-agent-core", "Agent Core dependency is missing.")
    health_path = str(dependency.get("health_path", ""))
    require(SAFE_PATH.fullmatch(health_path) is not None, "Agent Core health path is invalid.")
    semver_parts(dependency.get("min_version"), "Agent Core minimum version")
    require(dependency.get("contract_version") == "1.0.0", "Agent Core contract requirement is unsupported.")
    capabilities = dependency.get("required_capabilities")
    require(isinstance(capabilities, list) and capabilities == sorted(set(capabilities)) and all(re.fullmatch(r"[a-z][a-z0-9_]{1,60}", str(value)) for value in capabilities), "Agent Core capability requirements are invalid or not sorted.")
    stores = dependency.get("required_stores")
    require(stores == ["agent_store", "assisted_proposal_store", "commercial_intent_store", "quote_case_store"], "Agent Core store requirements are invalid.")
    return data


def response_body(response) -> bytes:  # noqa: ANN001
    body = response.read(MAX_BODY_BYTES + 1)
    require(len(body) <= MAX_BODY_BYTES, "Preflight response exceeded the one-megabyte limit.")
    return body


def fetch_without_redirects(opener, url: str, accept: str, timeout: int) -> tuple[int, Any, bytes]:  # noqa: ANN001
    request = Request(
        url,
        headers={
            "Accept": accept,
            "Cache-Control": "no-cache, no-store",
            "User-Agent": "Tra-Vel-Release-Preflight/1.0",
        },
        method="GET",
    )
    try:
        with opener.open(request, timeout=timeout) as response:
            return int(response.status), response.headers, response_body(response)
    except HTTPError as error:
        return int(error.code), error.headers, response_body(error)
    except (URLError, TimeoutError, OSError) as error:
        raise PreflightError(f"Preflight transport failed: {type(error).__name__}.") from error


def verify_live(site_url: str, requirements: dict[str, Any], timeout: int) -> None:
    parsed = urlparse(site_url)
    require(parsed.scheme == "https" and bool(parsed.netloc) and not parsed.username and not parsed.password, "Site URL must be an HTTPS origin without credentials.")
    require(not parsed.query and not parsed.fragment, "Site URL must not contain a query or fragment.")
    site_root = site_url.rstrip("/") + "/"
    opener = build_opener(NoRedirectHandler(), HTTPSHandler(context=ssl.create_default_context()))

    dependency = requirements["dependencies"][0]
    cache_token = secrets.token_hex(12)
    health_url = urljoin(site_root, dependency["health_path"].lstrip("/"))
    health_url = f"{health_url}?{urlencode({'release_preflight': cache_token})}"
    status, headers, body = fetch_without_redirects(opener, health_url, "application/json", timeout)
    require(status == 200, f"Agent Core health returned HTTP {status}; 200 is required.")
    require(headers.get("Location") is None, "Agent Core health unexpectedly redirected.")
    content_type = str(headers.get("Content-Type", "")).split(";", 1)[0].strip().lower()
    require(content_type == "application/json", "Agent Core health did not return JSON.")
    try:
        health = json.loads(body.decode("utf-8"))
    except (UnicodeDecodeError, ValueError, TypeError) as error:
        raise PreflightError("Agent Core health returned invalid JSON.") from error
    require(isinstance(health, dict) and health.get("ok") is True, "Agent Core health is not ready.")
    require(health.get("contract_version") == dependency["contract_version"], "Agent Core health contract is incompatible.")
    current_version = health.get("plugin_version")
    require(version_at_least(current_version, dependency["min_version"]), f"Agent Core {current_version or 'unknown'} is below required {dependency['min_version']}.")
    capabilities = health.get("capabilities")
    require(isinstance(capabilities, dict), "Agent Core capabilities are missing.")
    for capability in dependency["required_capabilities"]:
        require(capabilities.get(capability) is True, f"Agent Core capability {capability} is not ready.")
    for store_name in dependency["required_stores"]:
        store = health.get(store_name)
        require(isinstance(store, dict) and store.get("tables_ready") is True, f"Agent Core store {store_name} is not ready.")
        require(store.get("installed_schema_version") == store.get("schema_version"), f"Agent Core store {store_name} has an incomplete schema upgrade.")
        if store_name == "assisted_proposal_store":
            for key, expected in ASSISTED_PROPOSAL_STORE_HEALTH.items():
                require(store.get(key) == expected, f"Agent Core assisted-proposal store {key} does not match the required release contract.")

    http = requirements["public_http"]
    missing_path = f"{http['unknown_path_prefix']}{secrets.token_hex(12)}/"
    missing_url = urljoin(site_root, missing_path.lstrip("/"))
    missing_status, missing_headers, _ = fetch_without_redirects(opener, missing_url, "text/html", timeout)
    require(missing_headers.get("Location") is None, "A nonexistent public URL redirected instead of remaining missing.")
    require(missing_status == http["unknown_status"], f"A nonexistent public URL returned HTTP {missing_status}; 404 is required.")
    print(f"Tra-Vel theme preflight passed: HTTP 404 semantics and {dependency['id']} {current_version} dependency readiness.")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--requirements", required=True, type=Path)
    parser.add_argument("--site-url", default="")
    parser.add_argument("--timeout-seconds", type=int, default=20)
    parser.add_argument("--validate-only", action="store_true")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    try:
        require(1 <= args.timeout_seconds <= 30, "Timeout must be between 1 and 30 seconds.")
        requirements = load_requirements(args.requirements)
        if args.validate_only:
            print("Tra-Vel theme release requirements are valid.")
            return 0
        require(bool(args.site_url), "--site-url is required outside validate-only mode.")
        verify_live(args.site_url, requirements, args.timeout_seconds)
        return 0
    except PreflightError as error:
        print(f"Tra-Vel theme preflight failed: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
