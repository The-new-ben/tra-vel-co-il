#!/usr/bin/env python3
"""Build an installable ZIP for the one-time Tra-Vel deploy gateway."""

from __future__ import annotations

import hashlib
import json
import re
import zipfile
from pathlib import Path


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

DIST_DIR.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(ARCHIVE_PATH, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for source in sorted(PLUGIN_DIR.rglob("*")):
        if source.is_file():
            archive.write(source, Path("tra-vel-deploy-gateway") / source.relative_to(PLUGIN_DIR))

digest = hashlib.sha256(ARCHIVE_PATH.read_bytes()).hexdigest()
(DIST_DIR / "deploy-gateway-manifest.json").write_text(
    json.dumps({"plugin": "tra-vel-deploy-gateway", "version": version, "archive": ARCHIVE_PATH.name, "sha256": digest}, indent=2) + "\n",
    encoding="utf-8",
)
print(f"Built {ARCHIVE_PATH}")
print(f"SHA256 {digest}")
