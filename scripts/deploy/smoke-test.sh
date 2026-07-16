#!/usr/bin/env bash
set -euo pipefail

SITE_URL="${1:-${SITE_URL:-}}"
SMOKE_PATHS="${SMOKE_PATHS:-/,/travel-map/,/thailand/}"

if [[ -z "${SITE_URL}" || ! "${SITE_URL}" =~ ^https?:// ]]; then
  echo "A valid SITE_URL is required." >&2
  exit 1
fi

IFS=',' read -r -a paths <<< "${SMOKE_PATHS}"
for path in "${paths[@]}"; do
  url="${SITE_URL%/}/${path#/}"
  echo "Smoke testing ${url}"
  body="$(curl --fail --silent --show-error --location --retry 3 --retry-delay 2 --max-time 30 "${url}")"
  [[ -n "${body}" ]] || { echo "Empty response from ${url}" >&2; exit 1; }
done

if [[ "${EXPECT_THEME_MARKER:-false}" == "true" ]]; then
  home="$(curl --fail --silent --show-error --location --max-time 30 "${SITE_URL%/}/")"
  grep -q 'tra-vel-v2-app-css' <<< "${home}" || {
    echo "Tra-Vel V2 stylesheet marker was not found on the homepage." >&2
    exit 1
  }
fi

echo "Smoke tests passed."
