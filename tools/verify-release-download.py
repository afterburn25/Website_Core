#!/usr/bin/env python3
"""Read-only offline integrity and shape check of downloaded Core upload ZIPs.

Use checksums from a trusted release channel, preferably independently pinned.
This does NOT verify a publisher's identity, Ed25519 signatures, host security,
or suitability for deployment. No archive member is extracted or executed.
"""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import re
import stat
import sys
import zipfile

ZIP_NAME = re.compile(r"Website-Core-(?P<version>[0-9]+\.[0-9]+\.[0-9]+(?:-dev)?)-(?P<kind>COMPLETE-UPLOAD|UPDATE)\.zip\Z")
SUM_LINE = re.compile(r"([0-9a-fA-F]{64})  (Website-Core-[A-Za-z0-9._-]+\.zip)\Z")
BOOT_VERSION = re.compile(rb"define\('WC_VERSION', '([^']+)'\)")
HEX = re.compile(r"[0-9a-f]{64}\Z")
REQUIRED = {
    ".htaccess", "index.php", "install.php", "install/index.php",
    "includes/bootstrap.php", "core/Install/Installer.php",
    "core/Install/views/installer.php", "themes/base/theme.json",
    "config/.htaccess", "storage/.htaccess", "vendor/.htaccess",
    "vendor/autoload.php",
}
MAX_MEMBERS = 50000
MAX_TOTAL_SIZE = 5 * 1024**3


class VerificationError(ValueError):
    """Fail closed without extracting or logging artifact contents."""


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def checksum_for(path: Path, sums_path: Path) -> str:
    try:
        data = sums_path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise VerificationError("Checksum manifest is unreadable or invalid UTF-8") from exc
    seen: set[str] = set()
    wanted = None
    for line in data.splitlines():
        match = SUM_LINE.fullmatch(line)
        if match is None:
            raise VerificationError("Checksum manifest contains an invalid line")
        digest, filename = match.groups()
        folded = filename.casefold()
        if folded in seen:
            raise VerificationError("Checksum manifest contains a duplicate name")
        seen.add(folded)
        if filename == path.name:
            wanted = digest.lower()
    if wanted is None:
        raise VerificationError("Artifact is not listed in the checksum manifest")
    return wanted


def safe_archive_name(name: str) -> str:
    if not name or name.startswith("/") or "\\" in name or ":" in name or "\x00" in name:
        raise VerificationError("Archive contains an unsafe member path")
    clean = name[:-1] if name.endswith("/") else name
    if not clean or any(part in ("", ".", "..") for part in clean.split("/")):
        raise VerificationError("Archive contains an unsafe member path")
    return clean


def inspect_archive(path: Path, kind: str, version: str) -> int:
    try:
        archive = zipfile.ZipFile(path, "r")
    except (OSError, zipfile.BadZipFile) as exc:
        raise VerificationError("ZIP file cannot be opened") from exc
    with archive:
        infos = archive.infolist()
        if not infos or len(infos) > MAX_MEMBERS:
            raise VerificationError("Archive has an invalid member count")
        seen: set[str] = set()
        members: dict[str, zipfile.ZipInfo] = {}
        total = 0
        for info in infos:
            name = safe_archive_name(info.filename)
            folded = name.casefold()
            if folded in seen:
                raise VerificationError("Archive has duplicate or case-colliding paths")
            seen.add(folded)
            # Unix ZIPs describe file types in the upper mode bits. A symlink,
            # device or FIFO must never become a deployable member.
            if info.create_system == 3:
                file_type = stat.S_IFMT(info.external_attr >> 16)
                if file_type not in (0, stat.S_IFREG, stat.S_IFDIR):
                    raise VerificationError("Archive contains a linked or special member")
            if info.is_dir():
                continue
            total += info.file_size
            if total > MAX_TOTAL_SIZE:
                raise VerificationError("Archive has excessive uncompressed size")
            lower = name.lower()
            if lower.endswith(".zip"):
                raise VerificationError("Archive contains a nested ZIP")
            if (lower == ".env" or lower.endswith("/.env")
                    or lower.endswith((".pem", ".key", ".ser"))
                    or lower == "config/config.php" or lower == "config/installed.lock"
                    or lower.startswith(".git/") or "/.git/" in lower
                    or (lower.startswith("storage/") and lower not in {"storage/.htaccess", "storage/.gitkeep"})
                    or (lower.startswith("upload/") and lower not in {"upload/.htaccess", "upload/.gitkeep", "upload/.html"})
                    or lower.startswith("modules/industries/")
                    or lower.startswith("modules/addons/")):
                raise VerificationError("Archive contains runtime/private or unexpected package files")
            members[name] = info
        missing = REQUIRED - members.keys()
        if missing:
            raise VerificationError("Archive is missing required deployable members")
        expected = "update.json" if kind == "UPDATE" else None
        if kind == "COMPLETE-UPLOAD" and "update.json" in members:
            raise VerificationError("Complete upload unexpectedly contains update metadata")
        if expected is not None and expected not in members:
            raise VerificationError("Update ZIP is missing update.json")
        try:
            with archive.open("includes/bootstrap.php") as reader:
                bootstrap = reader.read(16384)
            match = BOOT_VERSION.search(bootstrap)
            if match is None or match.group(1).decode("ascii") != version:
                raise VerificationError("Bundled Core version does not match ZIP version")
            if kind == "UPDATE":
                with archive.open("update.json") as reader:
                    manifest = json.loads(reader.read(2 * 1024 * 1024 + 1))
                if not isinstance(manifest, dict) or manifest.get("product") != "website-core" or manifest.get("version") != version:
                    raise VerificationError("Update metadata product/version mismatch")
                file_hashes = manifest.get("files")
                if not isinstance(file_hashes, dict) or set(file_hashes) != (members.keys() - {"update.json"}):
                    raise VerificationError("Update manifest file inventory differs from ZIP")
                if not all(isinstance(value, str) and HEX.fullmatch(value) for value in file_hashes.values()):
                    raise VerificationError("Update manifest contains malformed file hashes")
                for filename, expected_hash in file_hashes.items():
                    digest = hashlib.sha256()
                    with archive.open(filename) as stream:
                        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                            digest.update(chunk)
                    if digest.hexdigest() != expected_hash:
                        raise VerificationError("Update manifest payload hash mismatch")
            if archive.testzip() is not None:
                raise VerificationError("Archive contains corrupted ZIP member data")
        except (zipfile.BadZipFile, OSError, UnicodeError, json.JSONDecodeError) as exc:
            raise VerificationError("Archive content is corrupt or invalid") from exc
    return len(members)


def verify(path: Path, sums_path: Path, *, expected_sha256: str | None = None,
           expected_version: str | None = None) -> dict[str, str | int]:
    match = ZIP_NAME.fullmatch(path.name)
    if match is None:
        raise VerificationError("Unsupported artifact filename (expected COMPLETE-UPLOAD or UPDATE)")
    version, kind = match.group("version", "kind")
    if expected_version is not None and version != expected_version:
        raise VerificationError("Artifact version differs from requested release version")
    if expected_sha256 is not None and HEX.fullmatch(expected_sha256.lower()) is None:
        raise VerificationError("Expected SHA-256 must be 64 hexadecimal characters")
    checksum = checksum_for(path, sums_path)
    try:
        actual = sha256_file(path)
    except OSError as exc:
        raise VerificationError("Artifact cannot be read") from exc
    if checksum != actual or (expected_sha256 is not None and actual != expected_sha256.lower()):
        raise VerificationError("Artifact SHA-256 does not match expected checksum")
    files = inspect_archive(path, kind, version)
    return {"version": version, "kind": kind, "files": files, "sha256": actual}


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("artifact", type=Path)
    parser.add_argument("--checksums", required=True, type=Path, help="trusted SHA256SUMS.txt")
    parser.add_argument("--expected-sha256", help="digest pinned independently of downloaded manifest")
    parser.add_argument("--version", help="expected version (for example 2.6.2)")
    args = parser.parse_args(argv)
    try:
        report = verify(args.artifact, args.checksums, expected_sha256=args.expected_sha256,
                        expected_version=args.version)
    except VerificationError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    print(f"PASS: {report['kind']} v{report['version']} — {report['files']} files — SHA-256 {report['sha256']}")
    print("Checksum/structure only; publisher signature and host acceptance are separate gates.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
