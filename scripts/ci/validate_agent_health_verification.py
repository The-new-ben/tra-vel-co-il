#!/usr/bin/env python3
"""Runtime validation for bounded Agent Core post-deploy verification."""

from __future__ import annotations

from verify_agent_deploy import (
    NoRedirectHandler,
    VerificationConfig,
    VerificationError,
    decode_json_response,
    run_verification,
    validate_config,
    validate_local_identity,
    validate_remote_contract,
)


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


MANIFEST = {
    "version": "0.3.0",
    "sha256": "a" * 64,
    "content_sha256": "b" * 64,
}
DEPLOYED = dict(MANIFEST)
HEALTH = {
    "ok": True,
    "plugin_version": "0.3.0",
    "contract_version": "1.0.0",
    "provider": {"configured": True},
    "capabilities": {
        "assisted_quote_cases": True,
        "operator_queue": True,
        "supplier_search": False,
        "proposal_generation": False,
        "booking_execution": False,
    },
    "agent_store": {
        "schema_version": "1.2.0",
        "installed_schema_version": "1.2.0",
        "expected_tables": 4,
        "ready_tables": 4,
        "transactional_tables": 4,
        "required_indexes": 9,
        "ready_indexes": 9,
        "required_indexes_ready": True,
        "tables_ready": True,
    },
    "quote_case_store": {
        "schema_version": "1.0.1",
        "installed_schema_version": "1.0.1",
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
    },
}
QUEUE = {"cases": [], "meta": {"page": 1}}


validate_local_identity(MANIFEST, DEPLOYED)
validate_remote_contract(HEALTH, QUEUE, MANIFEST)

try:
    decode_json_response("health", "text/html; charset=UTF-8", b"<html>private body</html>")
    raise AssertionError("A 200 HTML fallback was accepted as Agent health JSON.")
except VerificationError as error:
    check("private body" not in str(error), "HTML response content leaked into diagnostics.")

try:
    decode_json_response("health", "application/json", b"")
    raise AssertionError("An empty 200 response was accepted as Agent health JSON.")
except VerificationError:
    pass

try:
    stale_health = dict(HEALTH, plugin_version="0.2.1")
    validate_remote_contract(stale_health, QUEUE, MANIFEST)
    raise AssertionError("A stale OPcache/plugin version passed the health contract.")
except VerificationError:
    pass

config = validate_config(
    VerificationConfig(
        site_url="https://tra-vel.co.il/",
        username="operator",
        app_password="not-printed",
        attempts=4,
        request_timeout_seconds=2,
        base_delay_seconds=1,
        max_delay_seconds=2,
    )
)
attempts: list[int] = []
delays: list[float] = []
reports: list[str] = []


def eventually_ready(_config, _manifest, _deployed, attempt):  # noqa: ANN001
    attempts.append(attempt)
    if attempt < 3:
        raise VerificationError("REST route has not propagated.")
    return HEALTH


check(
    run_verification(config, MANIFEST, DEPLOYED, eventually_ready, delays.append, reports.append),
    "Bounded verification did not recover from a transient route propagation delay.",
)
check(attempts == [1, 2, 3], "Retry verification did not stop immediately after success.")
check(delays == [1, 2], "Retry verification did not use bounded exponential backoff.")
check(all("not-printed" not in line for line in reports), "Retry diagnostics exposed the application password.")

failed_attempts: list[int] = []


def never_ready(_config, _manifest, _deployed, attempt):  # noqa: ANN001
    failed_attempts.append(attempt)
    raise VerificationError("REST route remains unavailable.")


check(
    not run_verification(config, MANIFEST, DEPLOYED, never_ready, lambda _delay: None, lambda _line: None),
    "Exhausted verification did not fail closed for rollback.",
)
check(failed_attempts == [1, 2, 3, 4], "Exhausted verification exceeded or skipped its attempt bound.")

handler = NoRedirectHandler()
check(
    handler.redirect_request(None, None, 302, "Found", {}, "https://tra-vel.co.il/") is None,
    "The verifier can follow a missing REST route redirect to the homepage.",
)

print(
    "Tra-Vel Agent Core health verification validation passed "
    "(JSON-only no-redirect checks, bounded backoff, stale-version rejection, secret-safe diagnostics, rollback signal)."
)
