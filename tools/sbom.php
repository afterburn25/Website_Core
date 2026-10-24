<?php
declare(strict_types=1);

/**
 * Generate a CycloneDX 1.5 SBOM (JSON) for the shipped runtime dependency
 * set — composer.lock `packages` only, never dev packages (they never ship
 * in artifacts). Emitted as SBOM.json next to the release zips.
 *
 * Usage: php tools/sbom.php <outfile>
 */

$root = dirname(__DIR__);
$lock = json_decode((string) @file_get_contents($root . '/composer.lock'), true);
if (!is_array($lock) || !isset($lock['packages'])) {
    fwrite(STDERR, "composer.lock missing or unreadable\n");
    exit(1);
}

$version = '0.0.0-dev';
$bootstrap = (string) @file_get_contents($root . '/site/includes/bootstrap.php');
if (preg_match("/define\\('WC_VERSION', '([^']+)'\\)/", $bootstrap, $m)) {
    $version = $m[1];
}

$components = [];
foreach ($lock['packages'] as $p) {
    $licenses = [];
    foreach ((array) ($p['license'] ?? []) as $lic) {
        $licenses[] = ['license' => ['id' => $lic]];
    }
    $components[] = array_filter([
        'type' => 'library',
        'bom-ref' => 'pkg:composer/' . $p['name'] . '@' . $p['version'],
        'name' => $p['name'],
        'version' => $p['version'],
        'purl' => 'pkg:composer/' . $p['name'] . '@' . $p['version'],
        'description' => $p['description'] ?? null,
        'licenses' => $licenses !== [] ? $licenses : null,
        'externalReferences' => isset($p['source']['url'])
            ? [['type' => 'vcs', 'url' => $p['source']['url']]] : null,
    ], fn ($v) => $v !== null);
}
usort($components, fn ($a, $b) => $a['name'] <=> $b['name']);

$sbom = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.5',
    'serialNumber' => 'urn:uuid:' . sprintf(
        '%08x-%04x-%04x-%04x-%012x',
        random_int(0, 0xffffffff), random_int(0, 0xffff),
        random_int(0x4000, 0x4fff), random_int(0x8000, 0xbfff), random_int(0, 0xffffffffffff)
    ),
    'version' => 1,
    'metadata' => [
        'timestamp' => gmdate('c'),
        'tools' => [['name' => 'website-core sbom.php', 'version' => '1.0']],
        'component' => [
            'type' => 'application',
            'bom-ref' => 'pkg:composer/afterburn/website-core@' . $version,
            'name' => 'afterburn/website-core',
            'version' => $version,
            'description' => 'Website Core — reusable PHP business-website platform (core + packages + themes).',
        ],
    ],
    'components' => $components,
    // Dev dependencies are declared explicitly so consumers can verify
    // scope — they are not part of the shipped artifact.
    'externalReferences' => [[
        'type' => 'build-system',
        'url' => 'https://github.com/afterburn25/Website_Core/blob/devin/website-core/.github/workflows/ci.yml',
    ]],
];

$out = $argv[1] ?? null;
if ($out === null) {
    fwrite(STDERR, "usage: php tools/sbom.php <outfile>\n");
    exit(1);
}
file_put_contents($out, json_encode($sbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDOUT, 'SBOM ' . count($components) . " runtime components -> {$out}\n");
