<?php
declare(strict_types=1);

/**
 * CI-only commercial bundle acceptance. Uses a throwaway test license and
 * throwaway Ed25519 key. Never load or print any production signing secret.
 *
 * WC_TEST_BUNDLE_PUBLIC=... WC_TEST_BUNDLE_LICENSE=...
 * php tools/verify-bundle-signing.php /tmp/CI-BUNDLE.zip catering
 */
require dirname(__DIR__) . '/site/core/Update/SignatureVerifier.php';
require dirname(__DIR__) . '/site/core/Modules/ArchiveInspector.php';

use Core\Modules\ArchiveInspector;
use Core\Update\SignatureVerifier;

function checkBundle(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Signed commercial bundle contract failed: " . $message . "\n");
        exit(1);
    }
}

checkBundle(count($argv) === 3, 'expected archive and industry');
$path = (string) $argv[1];
$industry = (string) $argv[2];
checkBundle(preg_match('/^[a-z][a-z0-9-]*$/', $industry) === 1, 'unsafe industry id');
$public = getenv('WC_TEST_BUNDLE_PUBLIC');
$license = getenv('WC_TEST_BUNDLE_LICENSE');
checkBundle(is_string($public) && $public !== '' && is_string($license) && strlen($license) >= 8,
    'CI-only verifier requires an ephemeral public key and mock license');
checkBundle((new ArchiveInspector())->inspectSafety($path) === [], 'unsafe or unreadable archive');

$zip = new ZipArchive();
checkBundle($zip->open($path, ZipArchive::RDONLY) === true, 'cannot open archive');
try {
    $root = 'site/modules/industries/industry-' . $industry . '/';
    $manifestJson = $zip->getFromName($root . 'addon.json');
    checkBundle(is_string($manifestJson), 'module manifest missing');
    $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
    checkBundle(is_array($manifest) && ($manifest['license_key'] ?? null) === $license,
        'final manifest did not embed expected mock license');
    checkBundle(is_string($manifest['signature'] ?? null) && $manifest['signature'] !== '',
        'commercial bundle is not signed');
    $files = $manifest['integrity']['files'] ?? null;
    checkBundle(is_array($files) && count($files) > 10, 'signed module has no complete per-file checksum inventory');
    checkBundle(isset($files['src/Provider.php']) && isset($files['assets/css/catering.css']),
        'runtime module source and assets are not signed');
    $verifier = new SignatureVerifier([$public], false);
    [$ok, $error] = $verifier->verify($manifest);
    checkBundle($ok, 'trusted key did not verify the final manifest');

    $expected = [];
    foreach ($files as $relative => $digest) {
        checkBundle(is_string($relative)
            && ArchiveInspector::memberNameOk($relative)
            && is_string($digest) && preg_match('/^[a-f0-9]{64}$/', $digest) === 1,
            'invalid signed member map');
        $member = $root . $relative;
        $source = $zip->getStream($member);
        checkBundle(is_resource($source), 'signed module file missing from archive');
        $ctx = hash_init('sha256');
        $read = hash_update_stream($ctx, $source);
        fclose($source);
        checkBundle($read !== false && hash_equals($digest, hash_final($ctx)),
            'module file bytes do not match signed manifest');
        $expected[$member] = true;
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $member = (string) $zip->getNameIndex($i);
        checkBundle(!str_ends_with(strtolower($member), '.zip'), 'nested ZIP is forbidden');
        if (str_starts_with($member, $root) && $member !== $root . 'addon.json') {
            checkBundle(isset($expected[$member]), 'module file omitted from signed hash map');
        }
    }

    $changedLicense = $manifest;
    $changedLicense['license_key'] = 'TAMPERED-LICENSE-NO-ACCESS';
    [$trustedTampered] = $verifier->verify($changedLicense);
    checkBundle(!$trustedTampered, 'signature accepted a swapped license key');

    [$trustedUnsigned] = (new SignatureVerifier([], false))->verify(
        array_diff_key($manifest, ['signature' => true]));
    checkBundle(!$trustedUnsigned, 'strict trust policy accepted an unsigned package');

    $wrongKey = SignatureVerifier::generateKeypair()['public'];
    [$trustedWrongKey] = (new SignatureVerifier([$wrongKey], false))->verify($manifest);
    checkBundle(!$trustedWrongKey, 'strict trust accepted unrelated signer');
    echo "Signed licensed Catering bundle OK: verified " . count($files)
        . " source/asset members, strict trust, license tamper and signer mismatch checks.\n";
} finally {
    $zip->close();
}
