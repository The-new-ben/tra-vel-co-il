#!/usr/bin/env python3
"""Runtime validation for bounded Agent Core post-deploy verification."""

from __future__ import annotations

from copy import deepcopy

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
    "version": "0.9.1",
    "sha256": "a" * 64,
    "content_sha256": "b" * 64,
}
DEPLOYED = dict(MANIFEST)
HEALTH = {
    "ok": True,
    "plugin_version": "0.9.1",
    "contract_version": "1.0.0",
    "provider": {"configured": True},
    "capabilities": {
        "account_plan_history": True,
        "assisted_quote_cases": True,
        "operator_queue": True,
        "commercial_intents": True,
        "durable_commercial_handoffs": True,
        "lead_contact_capture": True,
        "sourced_assisted_proposals": True,
        "audited_proposal_actions": True,
        "no_login_scoped_sessions": True,
        "customer_trip_cockpit": True,
        "supplier_search": False,
        "supplier_dispatch": False,
        "proposal_generation": False,
        "payment_execution": False,
        "booking_execution": False,
        "reservation_execution": False,
        "ticket_issuance": False,
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
    },
    "commercial_intent_store": {
        "schema_version": "1.1.0",
        "installed_schema_version": "1.1.0",
        "tables_ready": True,
        "expected_tables": 3,
        "ready_tables": 3,
        "transactional_tables": 3,
        "required_indexes": 5,
        "ready_indexes": 5,
        "required_indexes_ready": True,
    },
    "assisted_proposal_store": {
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
    },
    "vip_capability_session_store": {
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
    },
    "customer_trip_cockpit_store": {
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
    },
}
QUEUE = {"cases": [], "meta": {"page": 1}}


validate_local_identity(MANIFEST, DEPLOYED)
validate_remote_contract(HEALTH, QUEUE, MANIFEST)


remote_negative_cases = 0


def expect_remote_rejection(candidate: dict, message: str) -> None:
    global remote_negative_cases
    remote_negative_cases += 1
    try:
        validate_remote_contract(candidate, QUEUE, MANIFEST)
        raise AssertionError(message)
    except VerificationError:
        pass


missing_capability = deepcopy(HEALTH)
missing_capability["capabilities"].pop("audited_proposal_actions")
expect_remote_rejection(missing_capability, "A missing audited-proposal capability passed deployment verification.")

missing_no_login_capability = deepcopy(HEALTH)
missing_no_login_capability["capabilities"].pop("no_login_scoped_sessions")
expect_remote_rejection(missing_no_login_capability, "A missing no-login scoped-session capability passed deployment verification.")

false_no_login_capability = deepcopy(HEALTH)
false_no_login_capability["capabilities"]["no_login_scoped_sessions"] = False
expect_remote_rejection(false_no_login_capability, "A false no-login scoped-session capability passed deployment verification.")

missing_customer_cockpit_capability = deepcopy(HEALTH)
missing_customer_cockpit_capability["capabilities"].pop("customer_trip_cockpit")
expect_remote_rejection(missing_customer_cockpit_capability, "A missing Customer Trip Cockpit capability passed deployment verification.")

false_customer_cockpit_capability = deepcopy(HEALTH)
false_customer_cockpit_capability["capabilities"]["customer_trip_cockpit"] = False
expect_remote_rejection(false_customer_cockpit_capability, "A false Customer Trip Cockpit capability passed deployment verification.")

missing_lead_contact_capability = deepcopy(HEALTH)
missing_lead_contact_capability["capabilities"].pop("lead_contact_capture")
expect_remote_rejection(missing_lead_contact_capability, "A missing lead-contact-capture capability passed deployment verification.")

false_lead_contact_capability = deepcopy(HEALTH)
false_lead_contact_capability["capabilities"]["lead_contact_capture"] = False
expect_remote_rejection(false_lead_contact_capability, "A false lead-contact-capture capability passed deployment verification.")

stale_quote_lead_schema = deepcopy(HEALTH)
stale_quote_lead_schema["quote_case_store"]["installed_schema_version"] = "1.0.1"
expect_remote_rejection(stale_quote_lead_schema, "A quote-case schema without the lead-capture columns passed deployment verification.")

stale_commercial_lead_schema = deepcopy(HEALTH)
stale_commercial_lead_schema["commercial_intent_store"]["installed_schema_version"] = "1.0.0"
expect_remote_rejection(stale_commercial_lead_schema, "A commercial-intent schema without the contact column passed deployment verification.")

mismatched_proposal_schema = deepcopy(HEALTH)
mismatched_proposal_schema["assisted_proposal_store"]["ready_indexes"] = 8
expect_remote_rejection(mismatched_proposal_schema, "An incomplete assisted-proposal index set passed deployment verification.")

errored_proposal_inspection = deepcopy(HEALTH)
errored_proposal_inspection["assisted_proposal_store"]["inspection_errors"] = ["wp_tra_vel_assisted_proposals"]
expect_remote_rejection(errored_proposal_inspection, "An assisted-proposal schema inspection error passed deployment verification.")

missing_capability_store = deepcopy(HEALTH)
missing_capability_store.pop("vip_capability_session_store")
expect_remote_rejection(missing_capability_store, "A missing capability-session store passed deployment verification.")

capability_store_negative_values = {
    "schema_version": "1.0.0",
    "installed_schema_version": "1.0.0",
    "grant_retention_days": 29,
    "session_retention_days": 29,
    "idempotency_days": 3,
    "session_ttl_seconds": 1801,
    "expected_tables": 5,
    "ready_tables": 3,
    "transactional_tables": 3,
    "required_indexes": 8,
    "ready_indexes": 6,
    "required_indexes_ready": False,
    "tables_ready": False,
}
for field, invalid_value in capability_store_negative_values.items():
    candidate = deepcopy(HEALTH)
    candidate["vip_capability_session_store"][field] = invalid_value
    expect_remote_rejection(candidate, f"Invalid capability-session {field} passed deployment verification.")

missing_customer_cockpit_store = deepcopy(HEALTH)
missing_customer_cockpit_store.pop("customer_trip_cockpit_store")
expect_remote_rejection(missing_customer_cockpit_store, "A missing Customer Trip Cockpit store passed deployment verification.")

customer_cockpit_store_negative_values = {
    "schema_version": "0.9.0",
    "installed_schema_version": "0.9.0",
    "retention_days": 399,
    "max_projection_bytes": 524287,
    "expected_tables": 4,
    "ready_tables": 2,
    "transactional_tables": 2,
    "required_indexes": 12,
    "ready_indexes": 12,
    "required_indexes_ready": False,
    "inspection_errors": ["wp_tra_vel_customer_trip_cockpits"],
    "tables_ready": False,
}
for field, invalid_value in customer_cockpit_store_negative_values.items():
    candidate = deepcopy(HEALTH)
    candidate["customer_trip_cockpit_store"][field] = invalid_value
    expect_remote_rejection(candidate, f"Invalid Customer Trip Cockpit store {field} passed deployment verification.")

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
    f"({remote_negative_cases} independent remote-contract negative cases; JSON-only no-redirect checks, bounded backoff, stale-version rejection, secret-safe diagnostics, rollback signal)."
)
