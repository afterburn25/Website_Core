"""Offline release archive verification; synthetic ZIP fixtures only."""
from __future__ import annotations

import hashlib
import importlib.util
import json
from pathlib import Path
import stat
import subprocess
import sys
import tempfile
import unittest
import zipfile

SCRIPT = Path(__file__).resolve().parents[2] / "tools" / "verify-release-download.py"
SPEC = importlib.util.spec_from_file_location("release_download_check", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
mod = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(mod)


class ReleaseDownloadVerificationTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="wc-release-verification-")
        self.root = Path(self.temp.name)
        self.path = self.root / "Website-Core-2.6.2-COMPLETE-UPLOAD.zip"
        self.sums = self.root / "SHA256SUMS.txt"

    def tearDown(self):
        self.temp.cleanup()

    def fixture(self, *, kind="COMPLETE-UPLOAD", alterations=None, manifest_override=None,
                extras=None, remove=None):
        self.path = self.root / f"Website-Core-2.6.2-{kind}.zip"
        files = {p: b"test only" for p in mod.REQUIRED}
        files["includes/bootstrap.php"] = b"<?php define('WC_VERSION', '2.6.2');"
        for path in remove or []:
            files.pop(path, None)
        files.update(alterations or {})
        if kind == "UPDATE":
            manifest = {"product": "website-core", "version": "2.6.2",
                        "files": {k: hashlib.sha256(v).hexdigest() for k, v in files.items()}}
            manifest.update(manifest_override or {})
            files["update.json"] = json.dumps(manifest).encode("utf-8")
        with zipfile.ZipFile(self.path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
            for name, data in files.items():
                archive.writestr(name, data)
            for name, data in extras or []:
                archive.writestr(name, data)
        digest = hashlib.sha256(self.path.read_bytes()).hexdigest()
        self.sums.write_text(f"{digest}  {self.path.name}\n", encoding="utf-8")
        return digest

    def test_valid_complete_archive(self):
        digest = self.fixture()
        report = mod.verify(self.path, self.sums, expected_sha256=digest, expected_version="2.6.2")
        self.assertEqual(report["kind"], "COMPLETE-UPLOAD")
        self.assertEqual(report["files"], len(mod.REQUIRED))
        result = subprocess.run([sys.executable, str(SCRIPT), str(self.path),
                                 "--checksums", str(self.sums), "--version", "2.6.2"],
                                capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("PASS:", result.stdout)

    def test_valid_update_checks_all_payload_bytes(self):
        self.fixture(kind="UPDATE")
        self.assertEqual(mod.verify(self.path, self.sums)["kind"], "UPDATE")

    def test_checksum_manifest_includes_runtime_sbom(self):
        self.fixture()
        self.sums.write_text(self.sums.read_text() + "0" * 64 + "  SBOM.json\n")
        self.assertEqual(mod.verify(self.path, self.sums)["version"], "2.6.2")

    def test_wrong_trusted_digest_rejected_even_if_sums_match(self):
        self.fixture()
        with self.assertRaisesRegex(mod.VerificationError, "SHA-256"):
            mod.verify(self.path, self.sums, expected_sha256="0" * 64)

    def test_download_tamper_rejected(self):
        self.fixture()
        with self.path.open("ab") as stream:
            stream.write(b"malicious append")
        with self.assertRaisesRegex(mod.VerificationError, "SHA-256"):
            mod.verify(self.path, self.sums)

    def test_missing_checksum_and_duplicate_rejected(self):
        digest = self.fixture()
        self.sums.write_text(f"{digest}  other.zip\n")
        with self.assertRaisesRegex(mod.VerificationError, "invalid line"):
            mod.verify(self.path, self.sums)
        self.sums.write_text(f"{digest}  {self.path.name}\n{digest}  {self.path.name}\n")
        with self.assertRaisesRegex(mod.VerificationError, "duplicate name"):
            mod.verify(self.path, self.sums)

    def test_missing_required_member_rejected(self):
        self.fixture(remove=["vendor/autoload.php"])
        with self.assertRaisesRegex(mod.VerificationError, "missing required"):
            mod.verify(self.path, self.sums)

    def test_pinned_htmlpurifier_serialized_runtime_tables_are_allowed(self):
        self.fixture(extras=[
            ("vendor/ezyang/htmlpurifier/library/HTMLPurifier/ConfigSchema/schema.ser", b"fixture"),
            ("vendor/ezyang/htmlpurifier/library/HTMLPurifier/EntityLookup/entities.ser", b"fixture"),
        ])
        self.assertEqual(mod.verify(self.path, self.sums)["kind"], "COMPLETE-UPLOAD")
        self.fixture(extras=[("vendor/other/secret.ser", b"private")])
        with self.assertRaises(mod.VerificationError):
            mod.verify(self.path, self.sums)

    def test_shipping_empty_directory_markers_are_allowed(self):
        self.fixture(extras=[
            ("storage/cache/.gitkeep", b""),
            ("storage/backups/.gitkeep", b""),
            ("modules/addons/.gitkeep", b""),
            ("modules/industries/.gitkeep", b""),
        ])
        self.assertEqual(mod.verify(self.path, self.sums)["kind"], "COMPLETE-UPLOAD")

    def test_nested_zip_and_private_configuration_rejected(self):
        for extra in ["assets/hidden.zip", "config/config.php", "storage/secret.txt", "upload/private.png", ".env"]:
            with self.subTest(extra=extra):
                self.fixture(extras=[(extra, b"private value")])
                with self.assertRaises(mod.VerificationError):
                    mod.verify(self.path, self.sums)

    def test_traversal_and_absolute_paths_rejected(self):
        for extra in ["../escape", "/absolute", "a//b", "a/./b", "a/../b", "C:drive/file", "some\\windows"]:
            with self.subTest(extra=extra):
                self.fixture(extras=[(extra, b"test")])
                with self.assertRaisesRegex(mod.VerificationError, "unsafe member"):
                    mod.verify(self.path, self.sums)

    def test_duplicate_names_and_case_collision_rejected(self):
        for extra in ["index.php", "INDEX.PHP"]:
            with self.subTest(extra=extra):
                self.fixture(extras=[(extra, b"different")])
                with self.assertRaisesRegex(mod.VerificationError, "duplicate or case-colliding"):
                    mod.verify(self.path, self.sums)

    def test_symlink_rejected(self):
        link = zipfile.ZipInfo("assets/link")
        link.create_system = 3
        link.external_attr = (stat.S_IFLNK | 0o777) << 16
        self.fixture(extras=[(link, b"/etc/passwd")])
        with self.assertRaisesRegex(mod.VerificationError, "linked or special"):
            mod.verify(self.path, self.sums)

    def test_embedded_version_mismatch_rejected(self):
        self.fixture(alterations={"includes/bootstrap.php": b"<?php define('WC_VERSION', '2.6.1');"})
        with self.assertRaisesRegex(mod.VerificationError, "version does not match"):
            mod.verify(self.path, self.sums)

    def test_update_manifest_wrong_version_or_missing_inventory_rejected(self):
        self.fixture(kind="UPDATE", manifest_override={"version": "2.6.1"})
        with self.assertRaisesRegex(mod.VerificationError, "product/version"):
            mod.verify(self.path, self.sums)
        self.fixture(kind="UPDATE", manifest_override={"files": {}})
        with self.assertRaisesRegex(mod.VerificationError, "inventory"):
            mod.verify(self.path, self.sums)

    def test_update_payload_hash_mismatch_rejected(self):
        self.fixture(kind="UPDATE", manifest_override={"files": {
            k: "0" * 64 for k in mod.REQUIRED}})
        with self.assertRaisesRegex(mod.VerificationError, "payload hash"):
            mod.verify(self.path, self.sums)

    def test_update_only_metadata_absent_from_complete(self):
        self.fixture(extras=[("update.json", b"{}")])
        with self.assertRaisesRegex(mod.VerificationError, "unexpectedly"):
            mod.verify(self.path, self.sums)

    def test_corrupt_zip_rejected_even_with_matching_digest(self):
        self.fixture()
        self.path.write_bytes(b"NOT-A-ZIP")
        self.sums.write_text(f"{hashlib.sha256(self.path.read_bytes()).hexdigest()}  {self.path.name}\n")
        with self.assertRaisesRegex(mod.VerificationError, "ZIP file"):
            mod.verify(self.path, self.sums)


if __name__ == "__main__":
    unittest.main()
