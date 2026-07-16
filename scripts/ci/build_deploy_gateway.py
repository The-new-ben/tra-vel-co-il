#!/usr/bin/env python3
"""Build an installable ZIP for the one-time Tra-Vel deploy gateway."""

from __future__ import annotations

import hashlib
import json
import zipfile
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent.parent
PLUGIN_DIR = REPO_ROOT / "plugin" / "tra-vel-deploy-gateway"
DIST_DIR = REPO_ROOT / "dist"
ARCHIVE_PATH = DIST_DIR / "tra-vel-deploy-gateway-0.1.0.zip"

if not (PLUGIN_DIR / "tra-vel-deploy-gateway.php").is_file():
    raise SystemExit(f"Plugin source is missing: {PLUGIN_DIR}")

DIST_DIR.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(ARCHIVE_PATH, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for source in sorted(PLUGIN_DIR.rglob("*")):
        if source.is_file():
            archive.write(source, Path("tra-vel-deploy-gateway") / source.relative_to(PLUGIN_DIR))

digest = hashlib.sha256(ARCHIVE_PATH.read_bytes()).hexdigest()
(DIST_DIR / "deploy-gateway-manifest.json").write_text(
    json.dumps({"plugin": "tra-vel-deploy-gateway", "archive": ARCHIVE_PATH.name, "sha256": digest}, indent=2) + "\n",
    encoding="utf-8",
)
print(f"Built {ARCHIVE_PATH}")
print(f"SHA256 {digest}")
