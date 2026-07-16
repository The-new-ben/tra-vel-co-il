#!/usr/bin/env bash
set -euo pipefail

BACKUP_NAME="${1:-latest}"
: "${WP_SITE_URL:?WP_SITE_URL is required}"
: "${WP_USERNAME:?WP_USERNAME is required}"
: "${WP_APP_PASSWORD:?WP_APP_PASSWORD is required}"

if [[ "$BACKUP_NAME" != "latest" && ! "$BACKUP_NAME" =~ ^tra-vel-v2-[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]+$ ]]; then
  echo "Backup name is invalid." >&2
  exit 1
fi
if [[ ! "$WP_SITE_URL" =~ ^https:// ]]; then
  echo "WP_SITE_URL must use HTTPS." >&2
  exit 1
fi

ROLLBACK_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/theme/rollback"
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "$RESPONSE_FILE"' EXIT

curl --fail-with-body --silent --show-error --max-time 180 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  --data-urlencode "backup=${BACKUP_NAME}" \
  "$ROLLBACK_URL" > "$RESPONSE_FILE"

grep -q '"ok":true' "$RESPONSE_FILE" || { echo "WordPress did not confirm the rollback." >&2; exit 1; }
python3 - "$RESPONSE_FILE" <<'PY'
import json
import sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
print(f"Restored {data['restored']}; version={data['version']}; active={str(data['active']).lower()}")
PY
