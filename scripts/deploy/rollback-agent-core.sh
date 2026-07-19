#!/usr/bin/env bash
set -euo pipefail

BACKUP_NAME="${1:-}"
: "${WP_SITE_URL:?WP_SITE_URL is required}"
: "${WP_USERNAME:?WP_USERNAME is required}"
: "${WP_APP_PASSWORD:?WP_APP_PASSWORD is required}"
: "${ROLLBACK_CONFIRMATION:?ROLLBACK_CONFIRMATION is required}"
: "${EXPECTED_CURRENT_FINGERPRINT:?EXPECTED_CURRENT_FINGERPRINT is required}"
: "${EXPECTED_RESTORED_FINGERPRINT:?EXPECTED_RESTORED_FINGERPRINT is required}"

if [[ "$ROLLBACK_CONFIRMATION" != "ROLLBACK TRA-VEL AGENT CORE" ]]; then
  echo "Agent Core rollback confirmation did not match." >&2
  exit 1
fi
if [[ ! "$BACKUP_NAME" =~ ^tra-vel-agent-core-[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]+$ ]]; then
  echo "Agent Core backup name is invalid." >&2
  exit 1
fi
if [[ ! "$EXPECTED_CURRENT_FINGERPRINT" =~ ^[a-f0-9]{64}$ ]]; then
  echo "Expected current Agent Core fingerprint is invalid." >&2
  exit 1
fi
if [[ ! "$EXPECTED_RESTORED_FINGERPRINT" =~ ^[a-f0-9]{64}$ ]]; then
  echo "Expected restored Agent Core fingerprint is invalid." >&2
  exit 1
fi
if [[ ! "$WP_SITE_URL" =~ ^https:// ]]; then
  echo "WP_SITE_URL must use HTTPS." >&2
  exit 1
fi

ROLLBACK_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/plugin/agent-core/rollback"
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "$RESPONSE_FILE"' EXIT

curl --fail-with-body --silent --show-error --max-time 180 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  --data-urlencode "backup=${BACKUP_NAME}" \
  --data-urlencode "expected_current_fingerprint=${EXPECTED_CURRENT_FINGERPRINT}" \
  --data-urlencode "expected_restored_fingerprint=${EXPECTED_RESTORED_FINGERPRINT}" \
  --data-urlencode "confirmation=${ROLLBACK_CONFIRMATION}" \
  "$ROLLBACK_URL" > "$RESPONSE_FILE"

python3 - "$RESPONSE_FILE" "$BACKUP_NAME" "$EXPECTED_RESTORED_FINGERPRINT" <<'PY'
import json
import re
import sys

data = json.load(open(sys.argv[1], encoding="utf-8"))
if data.get("ok") is not True:
    raise SystemExit("WordPress did not confirm Agent Core rollback.")
if data.get("restored") != sys.argv[2]:
    raise SystemExit("WordPress did not confirm the exact requested Agent Core backup.")
if data.get("content_sha256") != sys.argv[3] or not re.fullmatch(r"[a-f0-9]{64}", str(data.get("content_sha256", ""))):
    raise SystemExit("WordPress did not confirm the expected restored Agent Core fingerprint.")
if not isinstance(data.get("active"), bool):
    raise SystemExit("WordPress returned an invalid Agent Core activation state after rollback.")
print(f"Restored {data['restored']}; version={data.get('version')}; active={str(data.get('active')).lower()}")
PY
