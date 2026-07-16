#!/usr/bin/env python3
"""Build a deterministic, installable Tra-Vel Agent Core plugin archive."""

from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
import zipfile
from pathlib import Path, PurePosixPath


SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent.parent
PLUGIN_SLUG = "tra-vel-agent-core"
PLUGIN_DIR = REPO_ROOT / "plugin" / PLUGIN_SLUG
PLUGIN_MAIN = PLUGIN_DIR / f"{PLUGIN_SLUG}.php"
DIST_DIR = REPO_ROOT / "dist"
MANIFEST_PATH = DIST_DIR / "agent-core-manifest.json"

EXCLUDED_PARTS = {".git", ".idea", ".vscode", "__pycache__", "node_modules"}
EXCLUDED_NAMES = {".DS_Store", "Thumbs.db"}
EXCLUDED_SUFFIXES = {".log", ".pyc", ".pyo", ".swp", ".tmp"}
SEMVER_PATTERN = re.compile(r"^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$")


def fail(message: str) -> None:
    print(message, file=sys.stderr)
    raise SystemExit(1)


def plugin_header(source: str, field: str) -> str:
    match = re.search(rf"^\s*\*\s*{re.escape(field)}:\s*(.+?)\s*$", source, re.MULTILINE)
    return match.group(1).strip() if match else ""


def revision() -> str:
    candidate = os.environ.get("GITHUB_SHA", "").strip()
    if not candidate:
        try:
            candidate = subprocess.check_output(
                ["git", "-C", str(REPO_ROOT), "rev-parse", "HEAD"],
                text=True,
                stderr=subprocess.DEVNULL,
            ).strip()
        except (OSError, subprocess.CalledProcessError):
            candidate = "local"
    if re.fullmatch(r"[0-9a-fA-F]{7,64}", candidate):
        return candidate.lower()
    return "local"


def include_file(source: Path) -> bool:
    relative = source.relative_to(PLUGIN_DIR)
    if any(part in EXCLUDED_PARTS for part in relative.parts):
        return False
    if source.name in EXCLUDED_NAMES or source.name.startswith(".env"):
        return False
    if source.suffix.lower() in EXCLUDED_SUFFIXES:
        return False
    return source.is_file()


def archive_info(path: PurePosixPath) -> zipfile.ZipInfo:
    info = zipfile.ZipInfo(str(path), date_time=(1980, 1, 1, 0, 0, 0))
    info.compress_type = zipfile.ZIP_DEFLATED
    info.create_system = 3
    info.external_attr = (0o100644 & 0xFFFF) << 16
    return info


if not PLUGIN_MAIN.is_file():
    fail(f"Agent Core plugin entrypoint is missing: {PLUGIN_MAIN}")

main_source = PLUGIN_MAIN.read_text(encoding="utf-8")
plugin_name = plugin_header(main_source, "Plugin Name")
version = plugin_header(main_source, "Version")
if plugin_name != "Tra-Vel Agent Core":
    fail(f"Unexpected Agent Core plugin name: {plugin_name or 'missing'}")
if not SEMVER_PATTERN.fullmatch(version):
    fail(f"Agent Core plugin version is missing or invalid: {version or 'missing'}")

revision_value = revision()
revision_label = revision_value[:7] if revision_value != "local" else "local"
archive_name = f"{PLUGIN_SLUG}-{version}-{revision_label}.zip"
archive_path = DIST_DIR / archive_name

DIST_DIR.mkdir(parents=True, exist_ok=True)
sources = [source for source in sorted(PLUGIN_DIR.rglob("*")) if include_file(source)]
if not sources:
    fail(f"Agent Core plugin contains no packageable files: {PLUGIN_DIR}")

with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for source in sources:
        relative = PurePosixPath(PLUGIN_SLUG) / PurePosixPath(source.relative_to(PLUGIN_DIR).as_posix())
        archive.writestr(archive_info(relative), source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

with zipfile.ZipFile(archive_path, "r") as archive:
    entries = archive.namelist()
    expected_main = f"{PLUGIN_SLUG}/{PLUGIN_SLUG}.php"
    if expected_main not in entries:
        fail(f"Built package is missing its plugin entrypoint: {expected_main}")
    if any(
        not entry.startswith(f"{PLUGIN_SLUG}/")
        or entry.startswith("/")
        or ".." in PurePosixPath(entry).parts
        for entry in entries
    ):
        fail("Built package contains an invalid ZIP path.")
    bad_file = archive.testzip()
    if bad_file:
        fail(f"Built package failed its CRC check: {bad_file}")

digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
manifest = {
    "plugin": PLUGIN_SLUG,
    "plugin_file": f"{PLUGIN_SLUG}/{PLUGIN_SLUG}.php",
    "version": version,
    "revision": revision_value,
    "archive": archive_name,
    "sha256": digest,
    "files": len(sources),
    "bytes": archive_path.stat().st_size,
}
MANIFEST_PATH.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

print(f"Built {archive_path}")
print(f"Manifest {MANIFEST_PATH}")
print(f"SHA256 {digest}")
