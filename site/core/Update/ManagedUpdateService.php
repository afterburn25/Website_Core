<?php
declare(strict_types=1);

namespace Core\Update;

use Core\Backup\BackupService;
use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Marketplace\MarketplaceService;
use Core\Modules\ArchiveInspector;
use Core\Settings\SettingsRepository;

final class ManagedUpdateService
{
    public const POLICIES = ['off', 'stage-security', 'stage-all', 'apply-security', 'apply-all'];

    public function __construct(private Connection $db, private Config $config, private SettingsRepository $settings, private MarketplaceService $marketplace) {}

    /** Refresh signed feeds and apply the configured stage/apply policy. */
    public function run(bool $manual = false): array
    {
        foreach ($this->marketplace->sources() as $source) {
            if (!(bool) $source['active']) continue;
            try { $this->marketplace->refresh((int) $source['id']); }
            catch (\Throwable $e) { $this->record((int) $source['id'], null, 'refresh', 'failed', $e->getMessage()); }
        }
        $channel = (string) $this->config->get('updates.channel', 'stable');
        $release = $this->marketplace->latestCore($channel);
        if ($release === null || !version_compare((string) $release['version'], WC_VERSION, '>')) {
            $this->record($release !== null ? (int) $release['source_id'] : null, $release['version'] ?? null, 'check', 'ok', 'No newer trusted release is available.');
            return ['status' => 'current', 'message' => 'No newer trusted release is available.'];
        }
        $policy = (string) $this->settings->get('updates.automation', 'off');
        if (!in_array($policy, self::POLICIES, true)) $policy = 'off';
        $eligible = $manual || str_ends_with($policy, '-all') || (str_ends_with($policy, '-security') && $release['severity'] === 'security');
        if (!$eligible || (!$manual && $policy === 'off')) {
            $this->record((int) $release['source_id'], $release['version'], 'available', 'ok', 'Release available; automation policy did not stage it.');
            return ['status' => 'available', 'release' => $release];
        }
        $storage = (string) $this->config->get('paths.storage', WC_ROOT . '/storage');
        $staged = $storage . '/staged-update.zip';
        $error = $this->download((string) $release['download_url'], (string) $release['sha256'], $staged);
        if ($error !== null) { $this->record((int) $release['source_id'], $release['version'], 'stage', 'failed', $error); throw new \RuntimeException($error); }
        $updater = new CoreUpdater(WC_ROOT, $storage, new ArchiveInspector(), new SignatureVerifier((array) $this->config->get('trust.public_keys', []), false));
        [, $inspectError] = $updater->inspect($staged);
        if ($inspectError !== null) { @unlink($staged); $this->record((int) $release['source_id'], $release['version'], 'stage', 'failed', $inspectError); throw new \RuntimeException($inspectError); }
        $shouldApply = !$manual && str_starts_with($policy, 'apply-');
        if (!$shouldApply) {
            $this->record((int) $release['source_id'], $release['version'], 'stage', 'ok', 'Verified update staged for owner review.');
            return ['status' => 'staged', 'release' => $release];
        }
        [$snapshot, $backupError] = (new BackupService(
            $this->db,
            $storage . '/backups',
            $storage . '/staging',
            WC_ROOT,
            $storage,
            WC_CONFIG,
            (string) $this->config->get('paths.upload', WC_ROOT . '/upload')
        ))->create();
        if ($backupError !== null) {
            $message = 'Automatic apply stopped: safety backup failed. ' . $backupError;
            $this->record((int) $release['source_id'], $release['version'], 'apply', 'failed', $message);
            throw new \RuntimeException($message);
        }
        $flag = $storage . '/maintenance.flag'; $hadFlag = is_file($flag); if (!$hadFlag) file_put_contents($flag, date('c') . ' managed update');
        try {
            [$ok, $applyError] = $updater->apply($staged);
            if (!$ok) throw new \RuntimeException((string) $applyError);
            @unlink($staged); if (function_exists('opcache_reset')) @opcache_reset();
            (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        } catch (\Throwable $e) {
            $this->record((int) $release['source_id'], $release['version'], 'apply', 'failed', $e->getMessage());
            throw $e;
        } finally { if (!$hadFlag) @unlink($flag); }
        $this->record((int) $release['source_id'], $release['version'], 'apply', 'ok', 'Applied after safety backup ' . basename((string) $snapshot));
        return ['status' => 'applied', 'release' => $release, 'backup' => $snapshot];
    }

    public function recent(): array { return $this->db->fetchAll('SELECT c.*,s.label source FROM `' . $this->db->table('managed_update_checks') . '` c LEFT JOIN `' . $this->db->table('marketplace_sources') . '` s ON s.id=c.source_id ORDER BY c.id DESC LIMIT 50'); }

    private function download(string $url, string $hash, string $destination): ?string
    {
        if (!function_exists('curl_init')) return 'cURL is required to download managed updates.';
        @mkdir(dirname($destination), 0750, true); $tmp = $destination . '.tmp-' . bin2hex(random_bytes(4)); $fh = @fopen($tmp, 'wb');
        if (!$fh) return 'Managed update staging directory is not writable.';
        $written = 0; $limit = 256 * 1024 * 1024; $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 300, CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => ['Accept: application/zip', 'User-Agent: Website-Core-Updater/' . WC_VERSION],
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use ($fh, &$written, $limit): int { $written += strlen($chunk); if ($written > $limit) return 0; return (int) fwrite($fh, $chunk); }]);
        $ok = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch); fclose($fh);
        if (!$ok || $status !== 200 || $written > $limit) { @unlink($tmp); return $written > $limit ? 'Managed update exceeded 256 MB.' : ($error !== '' ? $error : 'Update download returned HTTP ' . $status . '.'); }
        if (!hash_equals($hash, hash_file('sha256', $tmp))) { @unlink($tmp); return 'Managed update checksum did not match the signed catalog.'; }
        if (!@rename($tmp, $destination)) { @unlink($tmp); return 'Could not move the managed update into protected staging.'; }
        return null;
    }

    private function record(?int $source, ?string $version, string $action, string $status, string $detail): void
    {
        $this->db->insert('managed_update_checks', ['source_id' => $source, 'version' => $version, 'action' => $action,
            'status' => $status, 'detail' => mb_substr($detail, 0, 1000), 'created_at' => date('Y-m-d H:i:s')]);
    }
}
