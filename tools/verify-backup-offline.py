#!/usr/bin/env python3
"""Verify a privately exported Website Core backup without restoring it.

This command reads the ZIP in place. It does NOT unpack files, contact a
server, import SQL, or print the database, configuration or filenames.
Python 3.10+ standard library only.

Usage:
    python tools/verify-backup-offline.py "D:\Private Backups\backup-....zip"
    python tools/verify-backup-offline.py backup.zip --expect-files 1210 --expect-tables 73
"""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import re
import sys
import zipfile


class BackupVerificationError(Exception):
    """A backup must not be trusted or restored."""


def sha256_member(archive: zipfile.ZipFile, name: str) -> str:
    digest = hashlib.sha256()
    with archive.open(name, "r") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def safe_relative_path(name: str) -> bool:
    if not name or "\0" in name or "\\" in name or name.startswith("/") or name.endswith("/"):
        return False
    if re.match(r"^[A-Za-z]:", name):
        return False
    segments = name.split("/")
    return all(part not in ("", ".", "..") for part in segments)


def verify_backup(path: Path) -> dict[str, object]:
    """Validate the complete backup on disk; never print/extract its secrets."""
    if not path.is_file():
        raise BackupVerificationError("Backup archive not found.")
    try:
        with zipfile.ZipFile(path, "r", allowZip64=True) as archive:
            infos = archive.infolist()
            if len(infos) > 250000:
                raise BackupVerificationError("Unreasonable number of archive members.")
            inventory: set[str] = set()
            lower_names: set[str] = set()
            for info in infos:
                name = info.filename
                relative = name[:-1] if info.is_dir() else name
                if not safe_relative_path(relative):
                    raise BackupVerificationError("Unsafe archive member path.")
                if name in inventory or name.lower() in lower_names:
                    raise BackupVerificationError("Duplicate or case-colliding archive member.")
                inventory.add(name)
                lower_names.add(name.lower())
                file_type = (info.external_attr >> 16) & 0o170000
                if file_type not in (0, 0o100000, 0o040000):
                    raise BackupVerificationError("Non-regular archive member.")
                if info.is_dir():
                    if file_type not in (0, 0o040000) or not name.startswith("files/"):
                        raise BackupVerificationError("Unexpected directory member.")
                elif file_type == 0o040000:
                    raise BackupVerificationError("Directory disguised as a file.")

            if "meta.json" not in inventory or "db.sql" not in inventory:
                raise BackupVerificationError("Missing required backup metadata or SQL.")
            metaInfo = archive.getinfo("meta.json")
            if metaInfo.file_size > 16 * 1024 * 1024:
                raise BackupVerificationError("Backup metadata is unexpectedly large.")
            try:
                meta = json.loads(archive.read("meta.json"))
            except (ValueError, UnicodeDecodeError):
                raise BackupVerificationError("Backup metadata is invalid.") from None
            if not isinstance(meta, dict) or type(meta.get("format")) is not int or meta["format"] != 1:
                raise BackupVerificationError("Unsupported backup format.")
            files = meta.get("files")
            tables = meta.get("tables")
            expected_sql = meta.get("db_sha256")
            if not isinstance(files, dict) or not isinstance(tables, list):
                raise BackupVerificationError("Incomplete backup manifest.")
            if not isinstance(expected_sql, str) or re.fullmatch(r"[a-f0-9]{64}", expected_sql) is None:
                raise BackupVerificationError("Invalid database checksum in manifest.")

            expected: set[str] = {"meta.json", "db.sql"}
            for relative, digest in files.items():
                if not isinstance(relative, str) or not safe_relative_path(relative):
                    raise BackupVerificationError("Unsafe protected-file path in manifest.")
                if not isinstance(digest, str) or re.fullmatch(r"[a-f0-9]{64}", digest) is None:
                    raise BackupVerificationError("Invalid protected-file checksum.")
                expected.add("files/" + relative)
            files_present = {info.filename for info in infos if not info.is_dir()}
            if files_present != expected:
                raise BackupVerificationError("Missing or undeclared protected archive members.")
            if sha256_member(archive, "db.sql") != expected_sql:
                raise BackupVerificationError("Database checksum mismatch.")
            for relative, digest in files.items():
                if sha256_member(archive, "files/" + relative) != digest:
                    raise BackupVerificationError("Protected-file checksum mismatch.")

            return {
                "verified": True,
                "format": meta["format"],
                "tables": len(tables),
                "protected_files": len(files),
                "archive_sha256": sha256_file(path),
            }
    except (OSError, EOFError, zipfile.BadZipFile, RuntimeError) as error:
        raise BackupVerificationError("Unreadable or corrupted backup ZIP.") from error


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("archive", type=Path, help="Privately exported backup ZIP on this computer")
    parser.add_argument("--expect-files", type=int, default=None)
    parser.add_argument("--expect-tables", type=int, default=None)
    parser.add_argument("--expect-sha256", default=None, help="Independently recorded ZIP digest")
    parser.add_argument("--json", action="store_true", help="Print non-sensitive verification summary as JSON")
    args = parser.parse_args()
    try:
        result = verify_backup(args.archive)
        if args.expect_files is not None and result["protected_files"] != args.expect_files:
            raise BackupVerificationError("Protected-file count differs from the expected snapshot.")
        if args.expect_tables is not None and result["tables"] != args.expect_tables:
            raise BackupVerificationError("Database-table count differs from the expected snapshot.")
        if args.expect_sha256 is not None and result["archive_sha256"] != args.expect_sha256.lower():
            raise BackupVerificationError("ZIP checksum differs from the independent expected digest.")
    except BackupVerificationError as error:
        print("FAIL: " + str(error), file=sys.stderr)
        return 1
    if args.json:
        print(json.dumps(result, sort_keys=True))
    else:
        print("PASS: complete backup member checksums verified")
        print("Database tables: " + str(result["tables"]))
        print("Protected files: " + str(result["protected_files"]))
        print("Archive SHA-256: " + str(result["archive_sha256"]))
        print("NOTE: Integrity verification is not a tested restore or an offsite backup.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
