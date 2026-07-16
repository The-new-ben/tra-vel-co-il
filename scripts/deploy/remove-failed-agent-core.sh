#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-}"
SHA256="${2:-}"
: "${WP_SITE_URL:?WP_SITE_URL is required}"
: "${WP_USERNAME:?WP_USERNAME is required}"
: "${WP_APP_PASSWORD:?WP_APP_PASSWORD is required}"
: "${REMOVE_FRESH_CONFIRMATION:?REMOVE_FRESH_CONFIRMATION is required}"

if [[ "$REMOVE_FRESH_CONFIRMATION" != "REMOVE FAILED TRA-VEL AGENT CORE" ]]; then
  echo "Fresh Agent Core recovery confirmation did not match." >&2
  exit 1
fi
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-+][A-Za-z0-9.-]+)?$ ]]; then
  echo "Fresh Agent Core recovery version is invalid." >&2
  exit 1
fi
if [[ ! "$SHA256" =~ ^[a-f0-9]{64}$ ]]; then
  echo "Fresh Agent Core recovery checksum is invalid." >&2
  exit 1
fi
if [[ ! "$WP_SITE_URL" =~ ^https:// ]]; then
  echo "WP_SITE_URL must use HTTPS." >&2
  exit 1
fi

RECOVERY_URL="${WP_SITE_URL%/}/wp-json/tra-vel-deploy/v1/plugin/agent-core/recovery/fresh"
RESPONSE_FILE="$(mktemp)"
trap 'rm -f "$RESPONSE_FILE"' EXIT

curl --fail-with-body --silent --show-error --max-time 180 \
  --user "${WP_USERNAME}:${WP_APP_PASSWORD}" \
  --data-urlencode "version=${VERSION}" \
  --data-urlencode "sha256=${SHA256}" \
  --data-urlencode "confirmation=${REMOVE_FRESH_CONFIRMATION}" \
  "$RECOVERY_URL" > "$RESPONSE_FILE"

python3 - "$RESPONSE_FILE" <<'PY'
import json
import sys

data = json.load(open(sys.argv[1], encoding="utf-8"))
if data.get("ok") is not True or data.get("removed") is not True:
    raise SystemExit("WordPress did not confirm failed fresh Agent Core removal.")
print(f"Removed failed fresh Agent Core {data['version']} ({data['sha256'][:12]}...).")
PY
