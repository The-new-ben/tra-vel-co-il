#!/usr/bin/env bash
set -euo pipefail

OUTPUT_FILE="${1:-}"
: "${WP_SITE_URL:?WP_SITE_URL is required}"
: "${WP_USERNAME:?WP_USERNAME is required}"
: "${WP_APP_PASSWORD:?WP_APP_PASSWORD is required}"

if [[ -z "$OUTPUT_FILE" ]]; then
  echo "Usage: get-theme-status.sh /path/to/status.json" >&2
  exit 1
fi
if [[ ! "$WP_SITE_URL" =~ ^https:// ]]; then
  echo "WP_SITE_URL must use HTTPS." >&2
  exit 1
fi

ATTEMPTS="${THEME_STATUS_ATTEMPTS:-6}"
if [[ ! "$ATTEMPTS" =~ ^[1-9][0-9]*$ ]] || (( ATTEMPTS > 12 )); then
  echo "THEME_STATUS_ATTEMPTS must be between 1 and 12." >&2
  exit 1
fi

STATUS_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/theme/status"
TEMP_FILE="$(mktemp)"
trap 'rm -f "$TEMP_FILE"' EXIT

for (( attempt = 1; attempt <= ATTEMPTS; attempt++ )); do
  : > "$TEMP_FILE"
  if curl --fail-with-body --silent --show-error \
    --connect-timeout 8 --max-time 15 \
    --header "Accept: application/json" \
    --header "Cache-Control: no-cache" \
    --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
    "$STATUS_URL" > "$TEMP_FILE" \
    && python3 - "$TEMP_FILE" <<'PY'
import json
import re
import sys

try:
    data = json.load(open(sys.argv[1], encoding="utf-8"))
except (OSError, ValueError, TypeError):
    raise SystemExit(1)
if not isinstance(data, dict) or data.get("theme") != "tra-vel-v2" or not re.fullmatch(r"\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?", str(data.get("gateway_version", ""))):
    raise SystemExit(1)
PY
  then
    mv -f "$TEMP_FILE" "$OUTPUT_FILE"
    chmod 600 "$OUTPUT_FILE"
    trap - EXIT
    exit 0
  fi

  if (( attempt < ATTEMPTS )); then
    echo "Theme status check ${attempt}/${ATTEMPTS} failed; retrying after a fixed two-second delay." >&2
    sleep 2
  fi
done

echo "The authenticated theme status endpoint did not return valid JSON after ${ATTEMPTS} bounded attempts." >&2
exit 1
