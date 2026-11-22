"""Strict signed UPDATE download verifier tests using CI-only ephemeral Ed25519 keys.

Fixtures contain only synthetic package data; no production keys, domains,
customer content, or network calls.
"""
from __future__ import annotations

import base64
import hashlib
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "tools" / "verify-signed-update-offline.php"
SIGNER = ROOT / "site" / "core" / "Update" / "SignatureVerifier.php"

GENERATOR = r"""<?php
declare(strict_types=1);
require $argv[1];
$base=$argv[2];
$expired=($argv[3]??'no')==='yes';
$files=[
 'index.php'=>'<?php echo "synthetic";',
 '.htaccess'=>'Options -Indexes',
 'install.php'=>'<?php echo "synthetic";',
 'install/index.php'=>'<?php echo "synthetic";',
 'includes/bootstrap.php'=>"<?php define('WC_VERSION', '2.6.2');",
 'core/Install/Installer.php'=>'<?php echo "synthetic";',
 'core/Install/views/installer.php'=>'<?php echo "synthetic";',
 'vendor/autoload.php'=>'<?php echo "synthetic";',
 'vendor/.htaccess'=>'Require all denied',
];
$kp=Core\Update\SignatureVerifier::generateKeypair();
file_put_contents($base.'/trusted-public.txt',$kp['public']."\n");
$hashes=[];
foreach($files as $name=>$data){$hashes[$name]=hash('sha256',$data);}
ksort($hashes,SORT_STRING);
$manifest=[
 'product'=>'website-core', 'version'=>'2.6.2', 'files'=>$hashes,
 'expires'=>gmdate('Y-m-d\TH:i:s\Z',time()+($expired?-100:3600)),
 'min_core'=>'0.1.14-dev',
];
$manifest=Core\Update\SignatureVerifier::signManifest($manifest,$kp['secret']);
$z=new ZipArchive();
if($z->open($base.'/Website-Core-2.6.2-UPDATE.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){exit(3);}
foreach($files as $name=>$bytes){$z->addFromString($name,$bytes);}
$z->addFromString('update.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
$z->close();
"""


class OfflineSignedUpdateTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="wc-signed-update-proof-")
        self.addCleanup(self.temp.cleanup)
        self.dir = Path(self.temp.name)
        php_script = self.dir / "fixture.php"
        php_script.write_text(GENERATOR, encoding="utf-8")
        result = subprocess.run(["php", str(php_script), str(SIGNER), str(self.dir)],
                                capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.archive = self.dir / "Website-Core-2.6.2-UPDATE.zip"
        self.key = self.dir / "trusted-public.txt"

    def invoke(self, *, expected_sha256=None, archive=None, key=None):
        command = ["php", str(SCRIPT), str(archive or self.archive), str(key or self.key),
                   "--version", "2.6.2"]
        if expected_sha256 is not None:
            command += ["--expected-sha256", expected_sha256]
        result = subprocess.run(command, capture_output=True, text=True)
        self.assertNotIn(str(self.dir), result.stderr + result.stdout)
        return result, json.loads(result.stdout) if result.stdout else None

    def change_zip(self, alter):
        items = []
        with zipfile.ZipFile(self.archive) as z:
            for item in z.infolist():
                items.append((item.filename, z.read(item.filename)))
        items = alter(items)
        with zipfile.ZipFile(self.archive, "w", compression=zipfile.ZIP_DEFLATED) as z:
            for name, data in items:
                z.writestr(name, data)

    def test_genuine_signed_update(self):
        digest = hashlib.sha256(self.archive.read_bytes()).hexdigest()
        result, report = self.invoke(expected_sha256=digest)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(report["verified"])
        self.assertEqual(report["version"], "2.6.2")
        self.assertEqual(report["files"], 9)
        self.assertIn("offline_download_signature", report["scope"])

    def test_wrong_trust_root_rejected(self):
        self.key.write_text(base64.b64encode(b"\x00" * 32).decode("ascii"))
        result, _ = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("untrusted_update_signature", result.stderr)

    def test_unsigned_manifest_rejected(self):
        def alter(items):
            out = []
            for name, data in items:
                if name == "update.json":
                    obj = json.loads(data)
                    del obj["signature"]
                    data = json.dumps(obj).encode("utf-8")
                out.append((name, data))
            return out
        self.change_zip(alter)
        result, _ = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("update_manifest_identity_or_signature_invalid", result.stderr)

    def test_tampered_payload_rejected_after_signature_acceptance(self):
        self.change_zip(lambda items: [(n, b"ATTACKER-REPLACEMENT" if n == "index.php" else d)
                                       for n, d in items])
        result, _ = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("update_payload_sha256_mismatch", result.stderr)

    def test_added_member_rejected(self):
        self.change_zip(lambda items: items + [("assets/injected.js", b"bad")])
        result, _ = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("update_inventory_mismatch", result.stderr)

    def test_unsafe_traversal_member_rejected(self):
        self.change_zip(lambda items: items + [("../outside.txt", b"bad")])
        result, _ = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("unsafe_zip_member_path", result.stderr)

    def test_runtime_private_file_rejected(self):
        self.change_zip(lambda items: items + [("config/config.php", b"PASSWORD=fake")])
        result, _ = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("runtime_or_private_member", result.stderr)
        self.assertNotIn("PASSWORD", result.stderr)

    def test_wrong_pinned_digest_rejected(self):
        result, _ = self.invoke(expected_sha256="0" * 64)
        self.assertEqual(result.returncode, 1)
        self.assertIn("independent_sha256_mismatch", result.stderr)

    def test_key_file_cannot_be_symlink(self):
        link = self.dir / "linked-key"
        try:
            link.symlink_to(self.key)
        except (NotImplementedError, OSError):
            self.skipTest("filesystem does not permit symlinks")
        result, _ = self.invoke(key=link)
        self.assertEqual(result.returncode, 1)
        self.assertIn("trusted_public_key_file_invalid", result.stderr)

    def test_expired_manifest_rejected(self):
        result = subprocess.run(["php", str(self.dir / "fixture.php"),
                                 str(SIGNER), str(self.dir), "yes"],
                                capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        result, _ = self.invoke()
        self.assertEqual(result.returncode, 1)
        self.assertIn("update_manifest_expired_or_invalid", result.stderr)


if __name__ == "__main__":
    unittest.main()
