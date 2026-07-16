#!/usr/bin/env bash
set -euo pipefail

ARCHIVE="${1:-}"
: "${WP_SITE_URL:?WP_SITE_URL is required}"
: "${WP_USERNAME:?WP_USERNAME is required}"
: "${WP_APP_PASSWORD:?WP_APP_PASSWORD is required}"

if [[ -z "$ARCHIVE" || ! -f "$ARCHIVE" || "$ARCHIVE" != *.zip ]]; then
  echo "Usage: deploy-theme.sh /path/to/tra-vel-v2.zip" >&2
  exit 1
fi
if [[ ! "$WP_SITE_URL" =~ ^https:// ]]; then
  echo "WP_SITE_URL must use HTTPS." >&2
  exit 1
fi
if [[ "${DEPLOYMENT_CONFIRMATION:-}" != "DEPLOY TRA-VEL V2" ]]; then
  echo "Deployment requires the exact phrase DEPLOY TRA-VEL V2." >&2
  exit 1
fi
if [[ "${ACTIVATE_THEME:-false}" != "true" && "${ACTIVATE_THEME:-false}" != "false" ]]; then
  echo "ACTIVATE_THEME must be true or false." >&2
  exit 1
fi
if [[ "${ACTIVATE_THEME:-false}" == "true" && "${ACTIVATION_CONFIRMATION:-}" != "ACTIVATE TRA-VEL V2" ]]; then
  echo "Activation requires the exact phrase ACTIVATE TRA-VEL V2." >&2
  exit 1
fi

SHA256="$(sha256sum "$ARCHIVE" | cut -d' ' -f1)"
STATUS_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/theme/status"
DEPLOY_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/theme"
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "$RESPONSE_FILE"' EXIT

echo "Checking the authenticated deployment gateway."
curl --fail-with-body --silent --show-error --max-time 30 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  "$STATUS_URL" > "$RESPONSE_FILE"
grep -q '"gateway_version"' "$RESPONSE_FILE" || { echo "The deployment gateway did not return a valid status response." >&2; exit 1; }

echo "Uploading the checksum-verified Tra-Vel V2 package."
curl --fail-with-body --silent --show-error --max-time 180 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  --header "X-Tra-Vel-SHA256: ${SHA256}" \
  --form "package=@${ARCHIVE};type=application/zip" \
  --form "activate=${ACTIVATE_THEME:-false}" \
  --form "deployment_confirmation=${DEPLOYMENT_CONFIRMATION}" \
  --form "activation_confirmation=${ACTIVATION_CONFIRMATION:-}" \
  "$DEPLOY_URL" > "$RESPONSE_FILE"

grep -q '"ok":true' "$RESPONSE_FILE" || { echo "WordPress did not confirm the deployment." >&2; exit 1; }
python3 - "$RESPONSE_FILE" <<'PY'
import json
import sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
print(f"Deployed {data['theme']} {data['version']}; active={str(data['active']).lower()}; backup={data.get('backup') or 'none'}")
PY
