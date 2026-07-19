#!/usr/bin/env python3
"""Build a deterministic, installable Tra-Vel V2 WordPress theme archive."""

from __future__ import annotations

import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent.parent
THEME_DIR = REPO_ROOT / "theme" / "tra-vel-v2"
DIST_DIR = REPO_ROOT / "dist"
REQUIREMENTS_PATH = THEME_DIR / "release-requirements.json"
SEO_REGISTRY_PATH = REPO_ROOT / "content" / "seo" / "content-opportunity-registry.json"
ZIP_EPOCH = (1980, 1, 1, 0, 0, 0)


def fail(message: str) -> None:
    print(message, file=sys.stderr)
    raise SystemExit(1)


if not (THEME_DIR / "style.css").is_file():
    fail(f"Theme source is missing: {THEME_DIR}")

if os.environ.get("TRA_VEL_ALLOW_DIRTY_BUILD") != "1":
    try:
        dirty = subprocess.check_output(
            ["git", "-C", str(REPO_ROOT), "status", "--porcelain", "--untracked-files=all"],
            text=True,
            stderr=subprocess.DEVNULL,
        ).strip()
    except (OSError, subprocess.CalledProcessError):
        fail("Unable to verify that the release source is clean.")
    if dirty:
        fail("Refusing to build a release archive from a dirty working tree.")

style = (THEME_DIR / "style.css").read_text(encoding="utf-8")
version_match = re.search(r"^Version:\s*(.+?)\s*$", style, re.MULTILINE)
version = version_match.group(1) if version_match else "0.0.0"
if not REQUIREMENTS_PATH.is_file():
    fail(f"Theme release requirements are missing: {REQUIREMENTS_PATH}")
try:
    release_requirements = json.loads(REQUIREMENTS_PATH.read_text(encoding="utf-8"))
except (OSError, ValueError, TypeError) as error:
    fail(f"Theme release requirements are invalid: {error}")
if release_requirements.get("theme", {}).get("slug") != "tra-vel-v2" or release_requirements.get("theme", {}).get("version") != version:
    fail("Theme release requirements do not match the packaged theme identity.")
requirements_sha256 = hashlib.sha256(REQUIREMENTS_PATH.read_bytes()).hexdigest()
if not SEO_REGISTRY_PATH.is_file():
    fail(f"SEO opportunity registry is missing: {SEO_REGISTRY_PATH}")
try:
    seo_registry_bytes = SEO_REGISTRY_PATH.read_bytes()
    seo_registry = json.loads(seo_registry_bytes.decode("utf-8"))
except (OSError, UnicodeDecodeError, ValueError, TypeError) as error:
    fail(f"SEO opportunity registry is invalid: {error}")
if seo_registry.get("schemaVersion") != 1 or seo_registry.get("locale") != "he-IL" or not isinstance(seo_registry.get("entries"), list):
    fail("SEO opportunity registry schemaVersion, locale or entries are invalid.")
seo_registry_sha256 = hashlib.sha256(seo_registry_bytes).hexdigest()

revision = os.environ.get("GITHUB_SHA", "")
if not revision:
    try:
        revision = subprocess.check_output(
            ["git", "-C", str(REPO_ROOT), "rev-parse", "HEAD"],
            text=True,
            stderr=subprocess.DEVNULL,
        ).strip()
    except (OSError, subprocess.CalledProcessError):
        revision = "local"

archive_name = f"tra-vel-v2-{version}-{revision[:7]}.zip"
archive_path = DIST_DIR / archive_name

if DIST_DIR != REPO_ROOT / "dist":
    fail("Refusing to clean an unexpected dist path.")

if DIST_DIR.exists():
    shutil.rmtree(DIST_DIR)
DIST_DIR.mkdir(parents=True)

excluded_parts = {".git", "node_modules", "dist", "output"}
excluded_names = {".DS_Store", "Thumbs.db"}

with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    registry_archive_name = "tra-vel-v2/content/seo/content-opportunity-registry.json"
    for source in sorted(THEME_DIR.rglob("*")):
        if not source.is_file():
            continue
        relative = source.relative_to(THEME_DIR)
        if any(part in excluded_parts for part in relative.parts) or source.name in excluded_names:
            continue
        archive_name_in_zip = (Path("tra-vel-v2") / relative).as_posix()
        if archive_name_in_zip == registry_archive_name:
            continue
        info = zipfile.ZipInfo(archive_name_in_zip, ZIP_EPOCH)
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = (0o100644 & 0xFFFF) << 16
        archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    registry_info = zipfile.ZipInfo(registry_archive_name, ZIP_EPOCH)
    registry_info.compress_type = zipfile.ZIP_DEFLATED
    registry_info.external_attr = (0o100644 & 0xFFFF) << 16
    archive.writestr(registry_info, seo_registry_bytes, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
manifest = {
    "theme": "tra-vel-v2",
    "version": version,
    "revision": revision,
    "archive": archive_name,
    "sha256": digest,
    "release_requirements": "tra-vel-v2/release-requirements.json",
    "release_requirements_sha256": requirements_sha256,
    "seo_registry": "tra-vel-v2/content/seo/content-opportunity-registry.json",
    "seo_registry_sha256": seo_registry_sha256,
}
(DIST_DIR / "manifest.json").write_text(
    json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
)

print(f"Built {archive_path}")
print(f"SHA256 {digest}")
