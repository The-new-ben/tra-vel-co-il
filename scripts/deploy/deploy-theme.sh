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
python3 - "$RESPONSE_FILE" "${THEME_DEPLOY_PRESTATE_FILE:-}" "${REQUIRE_EXISTING_THEME:-false}" <<'PY'
import json
import os
import re
import sys

data = json.load(open(sys.argv[1], encoding="utf-8"))
prestate_target = sys.argv[2]
require_existing = sys.argv[3] == "true"
if not isinstance(data.get("gateway_version"), str) or data.get("theme") != "tra-vel-v2":
    raise SystemExit("The deployment gateway did not return a valid status response.")
if not isinstance(data.get("installed"), bool) or not isinstance(data.get("active"), bool):
    raise SystemExit("The deployment gateway returned an invalid installed state.")
installed_version = data.get("installed_version")
installed_fingerprint = data.get("installed_fingerprint")
if data["installed"]:
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?", str(installed_version or "")):
        raise SystemExit("The installed theme version is invalid.")
    if not re.fullmatch(r"[a-f0-9]{64}", str(installed_fingerprint or "")):
        raise SystemExit("The installed theme fingerprint is invalid.")
elif installed_version is not None or installed_fingerprint is not None:
    raise SystemExit("The deployment gateway returned an inconsistent installed identity.")
if require_existing and not data["installed"]:
    raise SystemExit("This deployment path requires an existing Tra-Vel V2 release so rollback is available.")
if prestate_target:
    prestate = {
        "theme": data["theme"],
        "installed": data["installed"],
        "installed_version": installed_version,
        "installed_fingerprint": installed_fingerprint,
        "active": data["active"],
    }
    descriptor = os.open(prestate_target, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as prestate_file:
        json.dump(prestate, prestate_file, ensure_ascii=False, indent=2)
        prestate_file.write("\n")
    os.chmod(prestate_target, 0o600)
PY

echo "Uploading the checksum-verified Tra-Vel V2 package."
curl --fail-with-body --silent --show-error --max-time 180 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  --header "X-Tra-Vel-SHA256: ${SHA256}" \
  --form "package=@${ARCHIVE};type=application/zip" \
  --form "activate=${ACTIVATE_THEME:-false}" \
  --form "deployment_confirmation=${DEPLOYMENT_CONFIRMATION}" \
  --form "activation_confirmation=${ACTIVATION_CONFIRMATION:-}" \
  "$DEPLOY_URL" > "$RESPONSE_FILE"

python3 - "$RESPONSE_FILE" "$SHA256" "${THEME_DEPLOY_RESULT_FILE:-}" <<'PY'
import json
import os
import re
import sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
expected_sha256 = sys.argv[2]
result_target = sys.argv[3]
if data.get("ok") is not True:
    raise SystemExit("WordPress did not confirm the deployment.")
if data.get("theme") != "tra-vel-v2":
    raise SystemExit("WordPress returned an unexpected theme identity.")
if not re.fullmatch(r"\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?", str(data.get("version", ""))):
    raise SystemExit("WordPress returned an invalid theme version.")
if data.get("sha256") != expected_sha256:
    raise SystemExit("WordPress returned a checksum that does not match the uploaded archive.")
if not re.fullmatch(r"[a-f0-9]{64}", str(data.get("content_sha256", ""))):
    raise SystemExit("WordPress returned an invalid installed content fingerprint.")
if not isinstance(data.get("active"), bool):
    raise SystemExit("WordPress returned an invalid activation state.")
backup = data.get("backup")
if backup not in (None, "") and not re.fullmatch(r"tra-vel-v2-\d{8}T\d{6}Z-[A-Za-z0-9]+", str(backup)):
    raise SystemExit("WordPress returned an invalid rollback identifier.")
backup_content_sha256 = data.get("backup_content_sha256")
if backup not in (None, "") and not re.fullmatch(r"[a-f0-9]{64}", str(backup_content_sha256 or "")):
    raise SystemExit("WordPress returned an invalid backup content fingerprint.")
if backup in (None, "") and backup_content_sha256 not in (None, ""):
    raise SystemExit("WordPress returned backup content identity without a backup.")
unchanged = data.get("unchanged", False)
if not isinstance(unchanged, bool) or (unchanged and backup not in (None, "")):
    raise SystemExit("WordPress returned an invalid deployment mutation state.")
if result_target:
    receipt = {
        "ok": True,
        "theme": data["theme"],
        "version": data["version"],
        "sha256": data["sha256"],
        "content_sha256": data["content_sha256"],
        "backup": backup,
        "backup_content_sha256": backup_content_sha256,
        "active": data["active"],
        "unchanged": unchanged,
    }
    descriptor = os.open(result_target, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as result_file:
        json.dump(receipt, result_file, ensure_ascii=False, indent=2)
        result_file.write("\n")
    os.chmod(result_target, 0o600)
print(f"Deployed {data['theme']} {data['version']}; active={str(data['active']).lower()}; backup={data.get('backup') or 'none'}")
PY
