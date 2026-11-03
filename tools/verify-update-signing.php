<?php
declare(strict_types=1);

/**
 * CI-only signed-update acceptance. Both keys are generated per job: this does
 * not provision, store or exercise a real production trust root.
 *
 * php tools/verify-update-signing.php unsigned-update.zip signed-update.zip
 * WC_TEST_SIGN_PUBLIC must contain the ephemeral base64 public key.
 */
require dirname(__DIR__) . '/tests/bootstrap.php';

use Core\Modules\ArchiveInspector;
use Core\Update\CoreUpdater;
use Core\Update\SignatureVerifier;

function ensure(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "Signed-update acceptance failed: {$message}\n");
        exit(1);
    }
}

function readManifest(string $path): array
{
    $zip = new ZipArchive();
    ensure($zip->open($path, ZipArchive::RDONLY) === true, "Cannot open {$path}");
    $manifest = json_decode((string) $zip->getFromName('update.json'), true);
    ensure(is_array($manifest), 'Missing or invalid update.json');
    $files = $manifest['files'] ?? null;
    ensure(is_array($files) && $files !== [], 'Missing update member integrity map');
    foreach ($files as $member => $digest) {
        $bytes = $zip->getFromName((string) $member);
        ensure(is_string($bytes), 'Missing update payload member: ' . $member);
        ensure(hash_equals((string) $digest, hash('sha256', $bytes)),
            'Payload hash mismatch: ' . $member);
    }
    $zip->close();
    return $manifest;
}

ensure(count($argv) === 3, 'Usage: unsigned-update.zip signed-update.zip');
[$script, $unsigned, $signed] = $argv;
$public = getenv('WC_TEST_SIGN_PUBLIC');
ensure(is_string($public) && $public !== '', 'Ephemeral trusted public key missing');
$unsignedManifest = readManifest($unsigned);
$signedManifest = readManifest($signed);
ensure(($unsignedManifest['version'] ?? null) === WC_VERSION, 'Wrong unsigned Core version');
ensure(($signedManifest['version'] ?? null) === WC_VERSION, 'Wrong signed Core version');
ensure($unsignedManifest['files'] === $signedManifest['files'], 'Signing changed payload bytes');
ensure(!isset($unsignedManifest['signature']), 'Unsigned CI baseline unexpectedly signed');
ensure(is_string($signedManifest['signature'] ?? null), 'Signed build lacks a signature');

$trust = new SignatureVerifier([$public], false);
[$ok, $error] = $trust->verify($signedManifest);
ensure($ok, (string) $error);
[$ok] = $trust->verify($unsignedManifest);
ensure(!$ok, 'Production unsigned policy accepted unsigned update');

$wrongKey = SignatureVerifier::generateKeypair()['public'];
[$ok] = (new SignatureVerifier([$wrongKey], false))->verify($signedManifest);
ensure(!$ok, 'A different signer was trusted');

$tamperedManifest = $signedManifest;
$tamperedManifest['version'] = '9.9.9';
[$ok] = $trust->verify($tamperedManifest);
ensure(!$ok, 'Changed signed manifest was accepted');

$tmp = \WcProcessTmpDirs::make('wc-signing-');
mkdir($tmp . '/site/includes', 0777, true);
mkdir($tmp . '/site/config', 0777, true);
mkdir($tmp . '/storage', 0777, true);
file_put_contents($tmp . '/site/index.php', 'CUSTOMER-ORIGINAL');
file_put_contents($tmp . '/site/config/config.php', 'CUSTOMER-OWNED');
file_put_contents($tmp . '/site/includes/bootstrap.php', "<?php define('WC_VERSION', '2.6.2');");
$updater = new CoreUpdater($tmp . '/site', $tmp . '/storage',
    new ArchiveInspector(), $trust);
[$info, $error] = $updater->inspect($signed);
ensure($info !== null && $error === null && ($info['signed'] ?? false),
    'Actual signed builder artifact failed strict CoreUpdater inspection: ' . (string) $error);
[$info, $error] = $updater->inspect($unsigned);
ensure($info === null && str_contains((string) $error, 'Signature check failed'),
    'Unsigned builder artifact was accepted by production trust policy');

// Tampering with an actual signed payload must abort before any file write.
// inspect() checks the signature; apply() also checks every payload digest.
$corrupt = $tmp . '/tampered-update.zip';
ensure(copy($signed, $corrupt), 'Cannot stage corrupted copy');
$zip = new ZipArchive();
ensure($zip->open($corrupt) === true, 'Cannot modify staged corrupted copy');
ensure($zip->addFromString('index.php', 'ATTACKER-PAYLOAD'), 'Failed to corrupt index.php');
$zip->close();
[$ok, $error] = $updater->apply($corrupt);
ensure(!$ok, 'A tampered payload was applied');
ensure(file_get_contents($tmp . '/site/index.php') === 'CUSTOMER-ORIGINAL',
    'Rejected tampered update overwrote the website');
ensure(file_get_contents($tmp . '/site/config/config.php') === 'CUSTOMER-OWNED',
    'Rejected tampered update overwrote private configuration');

echo "Signed-update acceptance OK: real builder payload, ephemeral trust, unsigned/wrong-key/manifest/payload refusal.\n";
