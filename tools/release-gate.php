<?php
declare(strict_types=1);

/**
 * Fast, dependency-free release contract checks.
 *
 * This intentionally runs before Composer install in CI so version drift,
 * malformed manifests and packaging-contract regressions fail early.
 */

$root = dirname(__DIR__);
$errors = [];
$ok = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$read = static function (string $path) use (&$errors): string {
    $body = @file_get_contents($path);
    if ($body === false) {
        $errors[] = 'Missing or unreadable: ' . $path;
        return '';
    }
    return $body;
};

$bootstrap = $read($root . '/site/includes/bootstrap.php');
preg_match("/define\\('WC_VERSION', '([^']+)'\\)/", $bootstrap, $match);
$version = $match[1] ?? '';
$ok($version !== '' && preg_match('/^\\d+\\.\\d+\\.\\d+(?:-dev)?$/', $version) === 1,
    'WC_VERSION must be a semantic release version.');
preg_match("/define\\('WC_SCHEMA_VERSION', '([^']+)'\\)/", $bootstrap, $schemaMatch);
$schemaVersion = $schemaMatch[1] ?? '';

$versionFiles = [
    'site/config/config.example.php',
    'tests/bootstrap.php',
    'tools/verify-artifact.php',
];
foreach ($versionFiles as $file) {
    $ok(str_contains($read($root . '/' . $file), "'{$version}'"),
        "Version drift: {$file} does not contain {$version}.");
}

$theme = json_decode($read($root . '/site/themes/base/theme.json'), true);
$ok(is_array($theme), 'Base theme manifest must be valid JSON.');
$ok(($theme['version'] ?? '') === preg_replace('/-dev$/', '', $version),
    'Base theme version must match the core version without a -dev suffix.');

$manifests = array_merge(
    glob($root . '/examples/*/addon.json') ?: [],
    glob($root . '/examples/*/theme.json') ?: []
);
foreach ($manifests as $manifest) {
    $data = json_decode($read($manifest), true);
    $ok(is_array($data), 'Invalid JSON manifest: ' . $manifest);
    $ok(is_string($data['id'] ?? null) && str_contains((string) $data['id'], '/'),
        'Manifest id must be namespaced: ' . $manifest);
    $ok(is_string($data['version'] ?? null), 'Manifest version missing: ' . $manifest);
}

$ids = [];
foreach (glob($root . '/site/database/migrations/*.php') ?: [] as $migration) {
    $source = $read($migration);
    preg_match("/function\\s+id\\s*\\(\\s*\\)\\s*:\\s*string\\s*\\{\\s*return\\s+'([^']+)'/s", $source, $idMatch);
    $id = $idMatch[1] ?? '';
    $ok($id !== '', 'Migration id not statically discoverable: ' . basename($migration));
    if ($id !== '') {
        $ok(!isset($ids[$id]), 'Duplicate migration id: ' . $id);
        $ids[$id] = true;
        $filename = basename($migration);
        $ok($filename === $id . '.php' || str_starts_with($filename, $id . '_'),
            'Migration filename/id mismatch: ' . basename($migration));
    }
}
$migrationNames = array_keys($ids);
$sortedMigrationNames = $migrationNames;
sort($sortedMigrationNames, SORT_STRING);
$ok($migrationNames === $sortedMigrationNames, 'Migration files are not ordered by id.');
$ok($schemaVersion !== '' && $schemaVersion === ($sortedMigrationNames[array_key_last($sortedMigrationNames)] ?? ''),
    'WC_SCHEMA_VERSION must match the latest bundled core migration.');

foreach ([
    'README.md', 'CHANGELOG.md', 'PROJECT_STATUS.md', 'SESSION_HANDOFF.md',
    'docs/ROADMAP.md', 'docs/RELEASE_CHECKLIST.md', 'docs/COMPATIBILITY.md',
    'docs/UPGRADES.md', 'docs/BRANDING.md', 'docs/SECURITY.md',
    'docs/COMMERCIAL_HARDENING.md',
] as $doc) {
    $ok(is_file($root . '/' . $doc), 'Required release documentation missing: ' . $doc);
}

$attribution = $read($root . '/site/core/Branding/Attribution.php');
$ok(substr_count($attribution, 'https://cajunwebpro.com') >= 1,
    'Required Cajun Web Pro attribution link is missing.');
$ok(str_contains($attribution, 'Website Design &amp; Development by'),
    'Required immutable design credit is missing.');
$ok(str_contains($attribution, 'Let’s build yours.'),
    'Required immutable call to action is missing.');

$application = $read($root . '/site/core/Application.php');
$ok(str_contains($application, "X-WC-Cacheable") && str_contains($application, "private, no-store"),
    'Public HTML must use explicit cache opt-in and private-by-default behavior.');
$adminLayout = $read($root . '/site/admin/views/layouts/admin.php');
$ok(str_contains($adminLayout, '/_a/core/css/admin.css')
    && str_contains($adminLayout, '/_a/core/js/admin.js')
    && is_file($root . '/site/assets/css/admin.css')
    && is_file($root . '/site/assets/js/admin.js'),
    'Versioned Core admin design assets are missing or not wired.');
$pageEditor = $read($root . '/site/admin/views/pages/edit.php');
$ok(str_contains($pageEditor, '/_a/core/js/page-editor.js')
    && is_file($root . '/site/assets/js/page-editor.js')
    && str_contains($application, "/pages/{id:\\d+}/purge"),
    'Commercial page-editor assets or lifecycle routes are missing.');
$ok(is_file($root . '/site/install.php') && is_file($root . '/site/install/index.php'),
    'Deployment source must include the self-removing installer entry points.');
$installerCleanup = $read($root . '/site/includes/installer-cleanup.php');
$ok(str_contains($installerCleanup, "'/install.php'")
    && str_contains($installerCleanup, "'/install'"),
    'Installer cleanup must remove both public installer entry points.');

$builder = $read($root . '/tools/build-packages.php');
$ok(str_contains($builder, 'WC_UPDATE_MIN_CORE'),
    'Update packages must expose a cumulative upgrade floor.');
$ok(str_contains($builder, 'WC_BUILD_VENDOR_DIR'),
    'Offline reproducible vendor-tree input is missing.');

if ($errors !== []) {
    fwrite(STDERR, "Release gate failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "Release gate OK: {$version}; " . count($ids) . ' migrations; '
    . count($manifests) . " example manifests.\n";
