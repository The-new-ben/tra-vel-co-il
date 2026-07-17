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
python3 - "$RESPONSE_FILE" <<'PY'
import json
import re
import sys

data = json.load(open(sys.argv[1], encoding="utf-8"))
version = str(data.get("gateway_version", ""))
match = re.fullmatch(r"(\d+)\.(\d+)\.(\d+)", version)
if not match or tuple(map(int, match.groups())) < (0, 3, 0):
    raise SystemExit("Agent Core deployment requires deploy gateway 0.3.0 or newer.")
fingerprint = data.get("installed_fingerprint")
if data.get("installed") is True and not re.fullmatch(r"[a-f0-9]{64}", str(fingerprint or "")):
    raise SystemExit("The deployment gateway did not return a valid installed content fingerprint.")
PY

echo "Uploading the checksum-verified Agent Core package."
curl --fail-with-body --silent --show-error --max-time 180 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  --header "X-Tra-Vel-SHA256: ${SHA256}" \
  --form "package=@${ARCHIVE};type=application/zip" \
  --form "activate=${ACTIVATE_AGENT_CORE:-true}" \
  --form "deployment_confirmation=${DEPLOYMENT_CONFIRMATION}" \
  --form "activation_confirmation=${AGENT_ACTIVATION_CONFIRMATION:-}" \
  "$DEPLOY_URL" > "$RESPONSE_FILE"

if [[ -n "${AGENT_DEPLOY_RESULT_FILE:-}" ]]; then
  cp "$RESPONSE_FILE" "$AGENT_DEPLOY_RESULT_FILE"
fi
python3 - "$RESPONSE_FILE" <<'PY'
import json
import re
import sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
if data.get("ok") is not True or not re.fullmatch(r"[a-f0-9]{64}", str(data.get("content_sha256", ""))):
    raise SystemExit("WordPress did not confirm the installed Agent Core content fingerprint.")
if data.get("backup") and not re.fullmatch(r"[a-f0-9]{64}", str(data.get("previous_content_sha256", ""))):
    raise SystemExit("WordPress did not confirm the previous Agent Core content fingerprint.")
print(f"Deployed {data['plugin']} {data['version']}; active={str(data['active']).lower()}; backup={data.get('backup') or 'none'}")
PY
