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


def fail(message: str) -> None:
    print(message, file=sys.stderr)
    raise SystemExit(1)


if not (THEME_DIR / "style.css").is_file():
    fail(f"Theme source is missing: {THEME_DIR}")

style = (THEME_DIR / "style.css").read_text(encoding="utf-8")
version_match = re.search(r"^Version:\s*(.+?)\s*$", style, re.MULTILINE)
version = version_match.group(1) if version_match else "0.0.0"

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
    for source in sorted(THEME_DIR.rglob("*")):
        if not source.is_file():
            continue
        relative = source.relative_to(THEME_DIR)
        if any(part in excluded_parts for part in relative.parts) or source.name in excluded_names:
            continue
        archive.write(source, Path("tra-vel-v2") / relative)

digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
manifest = {
    "theme": "tra-vel-v2",
    "version": version,
    "revision": revision,
    "archive": archive_name,
    "sha256": digest,
}
(DIST_DIR / "manifest.json").write_text(
    json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
    encoding="utf-8",
)

print(f"Built {archive_path}")
print(f"SHA256 {digest}")
