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

python3 - "$RESPONSE_FILE" "$SHA256" "${AGENT_DEPLOY_RESULT_FILE:-}" <<'PY'
import json
import os
import re
import sys

data = json.load(open(sys.argv[1], encoding="utf-8"))
expected_sha256 = sys.argv[2]
result_target = sys.argv[3]
if data.get("ok") is not True or data.get("plugin") != "tra-vel-agent-core":
    raise SystemExit("WordPress did not confirm the expected Agent Core deployment identity.")
if not re.fullmatch(r"\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?", str(data.get("version", ""))):
    raise SystemExit("WordPress returned an invalid Agent Core version.")
if data.get("sha256") != expected_sha256:
    raise SystemExit("WordPress returned an Agent Core checksum that differs from the uploaded archive.")
content_sha256 = str(data.get("content_sha256", ""))
if not re.fullmatch(r"[a-f0-9]{64}", content_sha256):
    raise SystemExit("WordPress did not confirm the installed Agent Core content fingerprint.")
backup = data.get("backup")
if backup not in (None, "") and not re.fullmatch(r"tra-vel-agent-core-\d{8}T\d{6}Z-[A-Za-z0-9]+", str(backup)):
    raise SystemExit("WordPress returned an invalid Agent Core backup identity.")
previous_content_sha256 = data.get("previous_content_sha256")
if backup not in (None, "") and not re.fullmatch(r"[a-f0-9]{64}", str(previous_content_sha256 or "")):
    raise SystemExit("WordPress did not confirm the previous Agent Core content fingerprint.")
if not isinstance(data.get("active"), bool):
    raise SystemExit("WordPress returned an invalid Agent Core activation state.")
unchanged = data.get("unchanged", False)
if not isinstance(unchanged, bool) or (unchanged and backup not in (None, "")):
    raise SystemExit("WordPress returned an invalid Agent Core mutation state.")
if result_target:
    receipt = {
        "ok": True,
        "plugin": data["plugin"],
        "version": data["version"],
        "sha256": data["sha256"],
        "content_sha256": content_sha256,
        "previous_content_sha256": previous_content_sha256,
        "backup": backup,
        "active": data["active"],
        "unchanged": unchanged,
    }
    descriptor = os.open(result_target, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as result_file:
        json.dump(receipt, result_file, ensure_ascii=False, indent=2)
        result_file.write("\n")
    os.chmod(result_target, 0o600)
print(f"Deployed {data['plugin']} {data['version']}; active={str(data['active']).lower()}; backup={data.get('backup') or 'none'}")
PY
