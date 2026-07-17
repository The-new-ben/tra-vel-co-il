#!/usr/bin/env python3
"""Build a deterministic installable ZIP for the Tra-Vel deploy gateway."""

from __future__ import annotations

import hashlib
import json
import re
import zipfile
from pathlib import Path, PurePosixPath


SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent.parent
PLUGIN_DIR = REPO_ROOT / "plugin" / "tra-vel-deploy-gateway"
DIST_DIR = REPO_ROOT / "dist"
MAIN_FILE = PLUGIN_DIR / "tra-vel-deploy-gateway.php"
if not MAIN_FILE.is_file():
    raise SystemExit(f"Plugin source is missing: {PLUGIN_DIR}")

header = MAIN_FILE.read_text(encoding="utf-8")
match = re.search(r"^\s*\*\s*Version:\s*([^\s]+)\s*$", header, re.MULTILINE)
if not match:
    raise SystemExit("Deploy gateway Version header is missing")
version = match.group(1)
ARCHIVE_PATH = DIST_DIR / f"tra-vel-deploy-gateway-{version}.zip"


def archive_info(path: PurePosixPath) -> zipfile.ZipInfo:
    info = zipfile.ZipInfo(str(path), date_time=(1980, 1, 1, 0, 0, 0))
    info.compress_type = zipfile.ZIP_DEFLATED
    info.create_system = 3
    info.external_attr = (0o100644 & 0xFFFF) << 16
    return info


all_entries = sorted(PLUGIN_DIR.rglob("*"))
unsafe = [entry for entry in all_entries if entry.is_symlink() or entry.name.startswith(".env")]
if unsafe:
    raise SystemExit(f"Deploy gateway contains an unsafe package entry: {unsafe[0].relative_to(PLUGIN_DIR)}")
sources = [entry for entry in all_entries if entry.is_file()]
if not sources:
    raise SystemExit("Deploy gateway contains no packageable files")

DIST_DIR.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(ARCHIVE_PATH, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for source in sources:
        relative = PurePosixPath("tra-vel-deploy-gateway") / PurePosixPath(source.relative_to(PLUGIN_DIR).as_posix())
        archive.writestr(archive_info(relative), source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)

with zipfile.ZipFile(ARCHIVE_PATH, "r") as archive:
    entries = archive.namelist()
    expected_main = "tra-vel-deploy-gateway/tra-vel-deploy-gateway.php"
    if expected_main not in entries or any(
        not entry.startswith("tra-vel-deploy-gateway/")
        or entry.startswith("/")
        or ".." in PurePosixPath(entry).parts
        for entry in entries
    ):
        raise SystemExit("Built deploy gateway package has an invalid layout")
    bad_file = archive.testzip()
    if bad_file:
        raise SystemExit(f"Built deploy gateway package failed its CRC check: {bad_file}")

digest = hashlib.sha256(ARCHIVE_PATH.read_bytes()).hexdigest()
(DIST_DIR / "deploy-gateway-manifest.json").write_text(
    json.dumps({"plugin": "tra-vel-deploy-gateway", "version": version, "archive": ARCHIVE_PATH.name, "sha256": digest, "files": len(sources), "bytes": ARCHIVE_PATH.stat().st_size}, indent=2) + "\n",
    encoding="utf-8",
)
print(f"Built {ARCHIVE_PATH}")
print(f"SHA256 {digest}")
