"""Isolated backup verifier tests. No real customer data, credentials or host."""
from __future__ import annotations

import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
import zipfile

SCRIPT = Path(__file__).resolve().parents[2] / "tools" / "verify-backup-offline.py"
SPEC = importlib.util.spec_from_file_location("offline_backup_check", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
mod = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(mod)


class OfflineBackupVerifierTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory(prefix="wc-offline-backup-")
        self.path = Path(self.temp.name) / "fixture.zip"
        self.sql = b"CREATE TABLE wc_test (id INT);\n"
        self.files = {
            "config/config.php": b"NOT-REAL-CONFIG-SECRET",
            "upload/image.jpg": b"FICTITIOUS-IMAGE-BYTES",
        }

    def tearDown(self) -> None:
        self.temp.cleanup()

    def write_zip(self, *, corrupt_sql=False, corrupt_file=False, missing_file=False,
                  unlisted=False, traversal=False, symlink=False, collision=False) -> None:
        manifest = {
            "format": 1, "driver": "mysql", "prefix": "wc_",
            "tables": ["wc_test"],
            "db_sha256": hashlib.sha256(self.sql).hexdigest(),
            "files": {name: hashlib.sha256(data).hexdigest() for name, data in self.files.items()},
        }
        with zipfile.ZipFile(self.path, "w", compression=zipfile.ZIP_DEFLATED) as z:
            z.writestr("meta.json", json.dumps(manifest))
            z.writestr("db.sql", b"BROKEN" if corrupt_sql else self.sql)
            for name, data in self.files.items():
                if missing_file and name == "upload/image.jpg":
                    continue
                if corrupt_file and name == "upload/image.jpg":
                    data = b"CORRUPT"
                z.writestr("files/" + name, data)
            if unlisted:
                z.writestr("files/config/undeclared.php", b"not in manifest")
            if traversal:
                z.writestr("files/../../escaped.txt", b"escape")
            if collision:
                z.writestr("files/CONFIG/config.php", b"case collision")
            if symlink:
                item = zipfile.ZipInfo("files/link")
                item.create_system = 3
                item.external_attr = 0o120777 << 16
                z.writestr(item, b"/etc")

    def test_valid_archive_streamed_with_private_content_not_printed(self):
        self.write_zip()
        report = mod.verify_backup(self.path)
        self.assertTrue(report["verified"])
        self.assertEqual(report["tables"], 1)
        self.assertEqual(report["protected_files"], 2)
        self.assertEqual(report["archive_sha256"], hashlib.sha256(self.path.read_bytes()).hexdigest())
        output = subprocess.run([sys.executable, str(SCRIPT), str(self.path),
                                 "--expect-tables", "1", "--expect-files", "2"],
                                capture_output=True, text=True, check=False)
        self.assertEqual(output.returncode, 0, output.stderr)
        self.assertIn("PASS:", output.stdout)
        self.assertNotIn("NOT-REAL-CONFIG-SECRET", output.stdout)
        self.assertNotIn("config/config.php", output.stdout)

    def test_corrupted_sql_fails(self):
        self.write_zip(corrupt_sql=True)
        with self.assertRaisesRegex(mod.BackupVerificationError, "Database checksum"):
            mod.verify_backup(self.path)

    def test_corrupted_protected_file_fails(self):
        self.write_zip(corrupt_file=True)
        with self.assertRaisesRegex(mod.BackupVerificationError, "Protected-file checksum"):
            mod.verify_backup(self.path)

    def test_missing_file_fails(self):
        self.write_zip(missing_file=True)
        with self.assertRaisesRegex(mod.BackupVerificationError, "Missing or undeclared"):
            mod.verify_backup(self.path)

    def test_unlisted_file_fails(self):
        self.write_zip(unlisted=True)
        with self.assertRaisesRegex(mod.BackupVerificationError, "Missing or undeclared"):
            mod.verify_backup(self.path)

    def test_traversal_rejected_without_extraction(self):
        self.write_zip(traversal=True)
        with self.assertRaisesRegex(mod.BackupVerificationError, "Unsafe archive member"):
            mod.verify_backup(self.path)
        self.assertFalse((Path(self.temp.name) / "escaped.txt").exists())

    def test_symlink_member_rejected(self):
        self.write_zip(symlink=True)
        with self.assertRaisesRegex(mod.BackupVerificationError, "Non-regular"):
            mod.verify_backup(self.path)

    def test_expected_counts_mismatch_fails_closed(self):
        self.write_zip()
        output = subprocess.run([sys.executable, str(SCRIPT), str(self.path),
                                 "--expect-files", "1210"],
                                capture_output=True, text=True, check=False)
        self.assertEqual(output.returncode, 1)
        self.assertIn("FAIL:", output.stderr)

    def test_wrong_zip_sha_fails(self):
        self.write_zip()
        output = subprocess.run([sys.executable, str(SCRIPT), str(self.path),
                                 "--expect-sha256", "0" * 64],
                                capture_output=True, text=True, check=False)
        self.assertEqual(output.returncode, 1)
        self.assertIn("checksum", output.stderr)

    def test_case_colliding_file_rejected(self):
        self.write_zip(collision=True)
        # Either the unlisted-file inventory check or a case collision must
        # stop such an archive; neither can be labeled verified.
        with self.assertRaises(mod.BackupVerificationError):
            mod.verify_backup(self.path)


if __name__ == "__main__":
    unittest.main()
