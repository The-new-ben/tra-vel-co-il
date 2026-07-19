#!/usr/bin/env bash
set -euo pipefail

validate_manifest_only=false
if [[ "${1:-}" == "--validate-manifest" ]]; then
  validate_manifest_only=true
  shift
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
smoke_manifest="${script_dir}/smoke-routes.tsv"
[[ -r "${smoke_manifest}" ]] || { echo "Smoke route manifest is unavailable: ${smoke_manifest}" >&2; exit 1; }

declare -a manifest_paths=()
declare -a manifest_markers=()
while IFS=$'\t' read -r manifest_path manifest_marker extra; do
  [[ -z "${manifest_path}" ]] && continue
  [[ "${manifest_path}" == /* && "${manifest_path}" != *','* && "${manifest_path}" != *[[:space:]]* ]] || { echo "Smoke manifest path is invalid: ${manifest_path}" >&2; exit 1; }
  [[ -n "${manifest_marker}" && -z "${extra:-}" && "${manifest_marker}" != *','* && "${manifest_marker}" != *$'\n'* && "${manifest_marker}" != *$'\r'* ]] || { echo "Smoke manifest marker is invalid for ${manifest_path}" >&2; exit 1; }
  for existing_path in "${manifest_paths[@]}"; do
    [[ "${existing_path}" != "${manifest_path}" ]] || { echo "Smoke manifest contains a duplicate path: ${manifest_path}" >&2; exit 1; }
  done
  manifest_paths+=( "${manifest_path}" )
  manifest_markers+=( "${manifest_marker}" )
done < "${smoke_manifest}"
(( ${#manifest_paths[@]} > 0 )) || { echo "Smoke route manifest is empty." >&2; exit 1; }

default_smoke_paths="$(IFS=,; printf '%s' "${manifest_paths[*]}")"
SITE_URL="${1:-${SITE_URL:-}}"
SMOKE_PATHS="${SMOKE_PATHS:-${default_smoke_paths}}"
EXPECT_THEME_MARKER="${EXPECT_THEME_MARKER:-false}"
EXPECTED_THEME_VERSION="${EXPECTED_THEME_VERSION:-}"
ALLOW_LEGACY_RELEASE_MARKER="${ALLOW_LEGACY_RELEASE_MARKER:-false}"

if [[ "${EXPECT_THEME_MARKER}" != "true" && "${EXPECT_THEME_MARKER}" != "false" ]]; then
  echo "EXPECT_THEME_MARKER must be true or false." >&2
  exit 1
fi
if [[ -n "${EXPECTED_THEME_VERSION}" && ! "${EXPECTED_THEME_VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([+-][A-Za-z0-9.-]+)?$ ]]; then
  echo "EXPECTED_THEME_VERSION is invalid." >&2
  exit 1
fi
if [[ "${ALLOW_LEGACY_RELEASE_MARKER}" != "true" && "${ALLOW_LEGACY_RELEASE_MARKER}" != "false" ]]; then
  echo "ALLOW_LEGACY_RELEASE_MARKER must be true or false." >&2
  exit 1
fi

IFS=',' read -r -a paths <<< "${SMOKE_PATHS}"
declare -a markers=()
if [[ -n "${SMOKE_MARKERS:-}" ]]; then
  IFS=',' read -r -a markers <<< "${SMOKE_MARKERS}"
else
  for path in "${paths[@]}"; do
    matched_marker=""
    for index in "${!manifest_paths[@]}"; do
      if [[ "${manifest_paths[$index]}" == "${path}" ]]; then
        matched_marker="${manifest_markers[$index]}"
        break
      fi
    done
    [[ -n "${matched_marker}" ]] || { echo "No authoritative marker is registered for smoke path ${path}. Add it to smoke-routes.tsv or supply SMOKE_MARKERS explicitly." >&2; exit 1; }
    markers+=( "${matched_marker}" )
  done
fi
if (( ${#paths[@]} == 0 || ${#paths[@]} != ${#markers[@]} )); then
  echo "SMOKE_PATHS and SMOKE_MARKERS must contain the same non-zero number of comma-separated entries." >&2
  exit 1
fi

if [[ "${validate_manifest_only}" == "true" ]]; then
  echo "Smoke route manifest resolved ${#paths[@]} path-specific markers."
  exit 0
fi

if [[ -z "${SITE_URL}" || ! "${SITE_URL}" =~ ^https://[^[:space:]]+$ ]]; then
  echo "A valid HTTPS SITE_URL is required." >&2
  exit 1
fi

work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT
home_body=""

for index in "${!paths[@]}"; do
  path="${paths[$index]}"
  marker="${markers[$index]}"
  [[ "${path}" == /* ]] || { echo "Smoke path must begin with /: ${path}" >&2; exit 1; }
  [[ -n "${marker}" && "${marker}" != *$'\n'* && "${marker}" != *$'\r'* ]] || { echo "Smoke marker is invalid for ${path}" >&2; exit 1; }

  url="${SITE_URL%/}/${path#/}"
  separator='?'
  [[ "${url}" == *\?* ]] && separator='&'
  request_url="${url}${separator}tra_vel_smoke=$(date -u +%s)-${index}"
  body_file="${work_dir}/body-${index}.html"
  header_file="${work_dir}/headers-${index}.txt"

  echo "Smoke testing ${url}"
  status="$(curl --silent --show-error --retry 3 --retry-delay 2 --connect-timeout 8 --max-time 30 --max-redirs 0 \
    --header 'Accept: text/html' \
    --header 'Cache-Control: no-cache, no-store' \
    --dump-header "${header_file}" \
    --output "${body_file}" \
    --write-out '%{http_code}' \
    "${request_url}")" || { echo "Request failed for ${url}" >&2; exit 1; }

  [[ "${status}" == "200" ]] || { echo "Expected HTTP 200 from ${url}; received ${status}. Redirects are not accepted." >&2; exit 1; }
  ! grep -Eqi '^location:' "${header_file}" || { echo "Unexpected redirect response from ${url}." >&2; exit 1; }
  grep -Eqi '^content-type:[[:space:]]*text/html([[:space:]]*;|[[:space:]]*$)' "${header_file}" || { echo "Expected an HTML response from ${url}." >&2; exit 1; }
  [[ -s "${body_file}" ]] || { echo "Empty response from ${url}" >&2; exit 1; }
  grep -Fq -- "${marker}" "${body_file}" || { echo "Route marker '${marker}' was not found at ${url}." >&2; exit 1; }

  if [[ "${EXPECT_THEME_MARKER}" == "true" ]]; then
    grep -Fq 'tra-vel-v2-app-css' "${body_file}" || { echo "Tra-Vel V2 stylesheet marker was not found at ${url}." >&2; exit 1; }
  fi
  if [[ "${path}" == "/" ]]; then
    home_body="${body_file}"
  fi
done

if [[ "${EXPECT_THEME_MARKER}" == "true" ]]; then
  [[ -n "${home_body}" ]] || { echo "The homepage must be included in SMOKE_PATHS when EXPECT_THEME_MARKER=true." >&2; exit 1; }
  asset_url="$(python3 - "${home_body}" "${SITE_URL}" "${EXPECTED_THEME_VERSION}" "${ALLOW_LEGACY_RELEASE_MARKER}" <<'PY'
import html
import re
import sys
from urllib.parse import urljoin, urlparse

source = open(sys.argv[1], encoding="utf-8", errors="replace").read()
site_url = sys.argv[2]
expected_version = sys.argv[3]
allow_legacy_release = sys.argv[4] == "true"
release_match = re.search(r"<meta\b[^>]*\bname=['\"]tra-vel-release['\"][^>]*>", source, re.IGNORECASE)
if not release_match and not allow_legacy_release:
    raise SystemExit("Tra-Vel V2 release marker was not found on the homepage.")
if release_match:
    release_content = re.search(r"\bcontent=['\"]([^'\"]+)['\"]", release_match.group(0), re.IGNORECASE)
    if not release_content:
        raise SystemExit("Tra-Vel V2 release marker has no version.")
    release_version = html.unescape(release_content.group(1))
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?", release_version):
        raise SystemExit("Tra-Vel V2 release marker contains an invalid version.")
    if expected_version and release_version != expected_version:
        raise SystemExit("Tra-Vel V2 release marker does not match the expected release.")
tag_match = re.search(r"<link\b[^>]*\bid=['\"]tra-vel-v2-app-css['\"][^>]*>", source, re.IGNORECASE)
if not tag_match:
    raise SystemExit("Tra-Vel V2 stylesheet tag was not found on the homepage.")
href_match = re.search(r"\bhref=['\"]([^'\"]+)['\"]", tag_match.group(0), re.IGNORECASE)
if not href_match:
    raise SystemExit("Tra-Vel V2 stylesheet URL was not found on the homepage.")
asset_url = urljoin(site_url.rstrip("/") + "/", html.unescape(href_match.group(1)))
site = urlparse(site_url)
asset = urlparse(asset_url)
if asset.scheme != "https" or asset.netloc != site.netloc:
    raise SystemExit("Tra-Vel V2 stylesheet must use the expected HTTPS site origin.")
print(asset_url)
PY
)"
  asset_body="${work_dir}/tra-vel-v2-app.css"
  asset_headers="${work_dir}/asset-headers.txt"
  asset_status="$(curl --silent --show-error --retry 2 --retry-delay 2 --connect-timeout 8 --max-time 30 --max-redirs 0 \
    --header 'Accept: text/css' \
    --header 'Cache-Control: no-cache, no-store' \
    --dump-header "${asset_headers}" \
    --output "${asset_body}" \
    --write-out '%{http_code}' \
    "${asset_url}")" || { echo "Stylesheet request failed." >&2; exit 1; }
  [[ "${asset_status}" == "200" ]] || { echo "Expected HTTP 200 from the Tra-Vel V2 stylesheet; received ${asset_status}." >&2; exit 1; }
  ! grep -Eqi '^location:' "${asset_headers}" || { echo "The Tra-Vel V2 stylesheet unexpectedly redirected." >&2; exit 1; }
  grep -Eqi '^content-type:[[:space:]]*text/css([[:space:]]*;|[[:space:]]*$)' "${asset_headers}" || { echo "The Tra-Vel V2 stylesheet did not return CSS." >&2; exit 1; }
  [[ -s "${asset_body}" ]] || { echo "The Tra-Vel V2 stylesheet response was empty." >&2; exit 1; }
fi

echo "Smoke tests passed without redirects and with route-specific identity and release markers."
