#!/usr/bin/env bash
set -euo pipefail

ARCHIVE="${1:-}"
: "${WP_SITE_URL:?WP_SITE_URL is required}"
: "${WP_USERNAME:?WP_USERNAME is required}"
: "${WP_APP_PASSWORD:?WP_APP_PASSWORD is required}"
: "${DEPLOYMENT_CONFIRMATION:?DEPLOYMENT_CONFIRMATION is required}"

if [[ "$DEPLOYMENT_CONFIRMATION" != "DEPLOY TRA-VEL AGENT CORE" ]]; then
  echo "Agent Core deployment confirmation did not match." >&2
  exit 1
fi

if [[ -z "$ARCHIVE" || ! -f "$ARCHIVE" || "$ARCHIVE" != *.zip ]]; then
  echo "Usage: deploy-agent-core.sh /path/to/tra-vel-agent-core.zip" >&2
  exit 1
fi
if [[ ! "$WP_SITE_URL" =~ ^https:// ]]; then
  echo "WP_SITE_URL must use HTTPS." >&2
  exit 1
fi

SHA256="$(sha256sum "$ARCHIVE" | cut -d' ' -f1)"
STATUS_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/plugin/agent-core/status"
DEPLOY_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/plugin/agent-core"
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "$RESPONSE_FILE"' EXIT

echo "Checking the authenticated Agent Core deployment gateway."
curl --fail-with-body --silent --show-error --max-time 30 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  "$STATUS_URL" > "$RESPONSE_FILE"
grep -q '"gateway_version"' "$RESPONSE_FILE" || { echo "The deployment gateway did not return Agent Core status." >&2; exit 1; }

echo "Uploading the checksum-verified Agent Core package."
curl --fail-with-body --silent --show-error --max-time 180 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  --header "X-Tra-Vel-SHA256: ${SHA256}" \
  --form "package=@${ARCHIVE};type=application/zip" \
  --form "activate=${ACTIVATE_AGENT_CORE:-true}" \
  --form "deployment_confirmation=${DEPLOYMENT_CONFIRMATION}" \
  --form "activation_confirmation=${AGENT_ACTIVATION_CONFIRMATION:-}" \
  "$DEPLOY_URL" > "$RESPONSE_FILE"

grep -q '"ok":true' "$RESPONSE_FILE" || { echo "WordPress did not confirm Agent Core deployment." >&2; exit 1; }
if [[ -n "${AGENT_DEPLOY_RESULT_FILE:-}" ]]; then
  cp "$RESPONSE_FILE" "$AGENT_DEPLOY_RESULT_FILE"
fi
python3 - "$RESPONSE_FILE" <<'PY'
import json
import sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
print(f"Deployed {data['plugin']} {data['version']}; active={str(data['active']).lower()}; backup={data.get('backup') or 'none'}")
PY
