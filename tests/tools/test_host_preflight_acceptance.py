"""Disposable, credential-free strict hosted preflight acceptance tests."""
from __future__ import annotations

import json
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / "tools" / "verify-host-preflight.php"
GUARDS = ("config", "core", "database", "includes", "modules", "storage",
          "themes", "upload", "vendor")
GUARD = "Require all denied\nDeny from all\n"


class HostedPreflightAcceptanceTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="wc-hosted-static-check-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / "site"
        self.root.mkdir()
        for name in GUARDS:
            path = self.root / name
            path.mkdir()
            (path / ".htaccess").write_text(GUARD, encoding="utf-8")
        (self.root / "modules" / "industries").mkdir()
        (self.root / ".htaccess").write_text("Options -Indexes\n", encoding="utf-8")
        (self.root / "index.php").write_text("<?php\n", encoding="utf-8")
        (self.root / "vendor" / "autoload.php").write_text("<?php\n", encoding="utf-8")
        (self.root / "includes" / "bootstrap.php").write_text(
            "<?php\ndefine('WC_VERSION', '2.6.2');\n", encoding="utf-8"
        )
        self.secret = "DO-NOT-PRINT-THIS-SECRET-2026"
        (self.root / "config" / "config.php").write_text(
            "<?php return ['db_password'=>'" + self.secret + "'];\n",
            encoding="utf-8"
        )
        (self.root / "config" / "installed.lock").write_text("install complete\n")
        self.extra = ["--no-industry"]

    def invoke(self, *args, private_config=None):
        command = ["php", str(SCRIPT), str(self.root), "--expected-core", "2.6.2"]
        command.extend(args or self.extra)
        if private_config is not None:
            command += ["--private-config-dir", str(private_config)]
        result = subprocess.run(command, capture_output=True, text=True, check=False)
        self.assertNotIn(self.secret, result.stdout + result.stderr)
        self.assertNotIn(str(self.root), result.stdout + result.stderr)
        report = json.loads(result.stdout) if result.stdout else None
        return result, report

    def add_industry(self, *, malformed=False):
        directory = self.root / "modules" / "industries" / "industry-catering"
        directory.mkdir(parents=True)
        (directory / "addon.json").write_text(
            "{" if malformed else json.dumps({
                "id": "cajunwebpro/catering",
                "version": "2.1.2",
                "license_key": self.secret,
                "hosting_customer": "private",
            }), encoding="utf-8"
        )

    def test_complete_core_only_inspection(self):
        result, report = self.invoke()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(report["accepted"])
        self.assertEqual(report["core_version"], "2.6.2")
        self.assertEqual(report["industry_count"], 0)
        self.assertIn("actual_webserver_waf_and_php_fpm", report["not_tested"])

    def test_installed_catering_manifest_only_is_not_enabled_proof(self):
        self.add_industry()
        result, report = self.invoke("--expected-industry", "cajunwebpro/catering@2.1.2")
        self.assertEqual(result.returncode, 0, result.stdout)
        self.assertEqual(report["industry_count"], 1)
        self.assertNotIn("license_key", result.stdout)
        self.assertNotIn("hosting_customer", result.stdout)
        self.assertIn("database_schema_or_package_enabled_state", report["not_tested"])

    def test_wrong_core_version_fails_closed(self):
        (self.root / "includes" / "bootstrap.php").write_text(
            "<?php\ndefine('WC_VERSION', '2.6.1');\n"
        )
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("core_version_mismatch", report["failures"])

    def test_wrong_or_missing_industry_fails(self):
        self.add_industry()
        result, report = self.invoke("--expected-industry", "cajunwebpro/catering@2.1.1")
        self.assertEqual(result.returncode, 1)
        self.assertIn("expected_industry_manifest_not_found", report["failures"])

    def test_unexpected_industry_fails_core_only(self):
        self.add_industry()
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("unexpected_industry_manifest", report["failures"])

    def test_malformed_manifest_is_not_silently_omitted(self):
        self.add_industry(malformed=True)
        result, report = self.invoke("--expected-industry", "cajunwebpro/catering@2.1.2")
        self.assertEqual(result.returncode, 1)
        self.assertIn("incomplete_or_unsafe_installation_state", report["failures"])

    def test_public_installer_left_on_installed_site_fails(self):
        (self.root / "install.php").write_text("<?php\n")
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("installer_not_retired", report["failures"])

    def test_missing_installation_lock_fails(self):
        (self.root / "config" / "installed.lock").unlink()
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("missing_or_unverified_installed_lock_valid_format", report["failures"])

    def test_incomplete_private_guard_fails(self):
        (self.root / "core" / ".htaccess").write_text("Allow from all\n")
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("private_guard_invalid_core", report["failures"])

    def test_missing_runtime_autoloader_fails(self):
        (self.root / "vendor" / "autoload.php").unlink()
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("missing_or_unverified_runtime_vendor_present", report["failures"])

    def test_symlinked_private_guard_fails(self):
        source = self.root / "safe-guard.txt"
        source.write_text(GUARD)
        target = self.root / "vendor" / ".htaccess"
        target.unlink()
        try:
            target.symlink_to(source)
        except (NotImplementedError, OSError):
            self.skipTest("filesystem does not permit symlinks")
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("private_guard_invalid_vendor", report["failures"])

    def test_symlinked_config_directory_fails(self):
        old = self.root / "config"
        moved = self.root / "config-real"
        old.rename(moved)
        try:
            old.symlink_to(moved, target_is_directory=True)
        except (NotImplementedError, OSError):
            self.skipTest("filesystem does not permit symlinks")
        result, report = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("linked_private_configuration_directory", report["failures"])

    def test_separate_private_config_directory(self):
        config = Path(self.temp.name) / "private-conf"
        config.mkdir()
        (config / "config.php").write_text(
            "<?php return ['secret' => '" + self.secret + "'];"
        )
        (config / "installed.lock").write_text("install complete\n")
        result, report = self.invoke(private_config=config)
        self.assertEqual(result.returncode, 0, report)
        self.assertTrue(report["accepted"])

    def test_duplicate_or_invalid_option_rejected_without_paths(self):
        result, report = self.invoke("--no-industry", "--no-industry")
        self.assertEqual(result.returncode, 2)
        self.assertIsNone(report)
        result, report = self.invoke("--expected-industry", "../../etc@2.1.2")
        self.assertEqual(result.returncode, 2)
        self.assertIsNone(report)


if __name__ == "__main__":
    unittest.main()
