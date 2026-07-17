#!/usr/bin/env bash
set -euo pipefail

BACKUP_NAME="${1:-}"
: "${WP_SITE_URL:?WP_SITE_URL is required}"
: "${WP_USERNAME:?WP_USERNAME is required}"
: "${WP_APP_PASSWORD:?WP_APP_PASSWORD is required}"
: "${EXPECTED_CURRENT_FINGERPRINT:?EXPECTED_CURRENT_FINGERPRINT is required}"
: "${EXPECTED_RESTORED_FINGERPRINT:?EXPECTED_RESTORED_FINGERPRINT is required}"

if [[ ! "$BACKUP_NAME" =~ ^tra-vel-v2-[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]+$ ]]; then
  echo "Backup name is invalid." >&2
  exit 1
fi
if [[ ! "$EXPECTED_CURRENT_FINGERPRINT" =~ ^[a-f0-9]{64}$ ]]; then
  echo "Expected current theme fingerprint is invalid." >&2
  exit 1
fi
if [[ ! "$EXPECTED_RESTORED_FINGERPRINT" =~ ^[a-f0-9]{64}$ ]]; then
  echo "Expected restored theme fingerprint is invalid." >&2
  exit 1
fi
if [[ ! "$WP_SITE_URL" =~ ^https:// ]]; then
  echo "WP_SITE_URL must use HTTPS." >&2
  exit 1
fi
if [[ "${ROLLBACK_CONFIRMATION:-}" != "ROLLBACK TRA-VEL V2" ]]; then
  echo "Rollback requires the exact phrase ROLLBACK TRA-VEL V2." >&2
  exit 1
fi

ROLLBACK_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/theme/rollback"
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
if data.get("ok") is not True or data.get("restored") != sys.argv[2] or data.get("content_sha256") != sys.argv[3]:
    raise SystemExit("WordPress did not confirm the exact theme rollback identity.")
print(f"Restored {data['restored']}; version={data['version']}; content={data['content_sha256']}; active={str(data['active']).lower()}")
PY
