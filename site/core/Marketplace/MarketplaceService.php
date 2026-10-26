<?php
declare(strict_types=1);

namespace Core\Marketplace;

use Core\Database\Connection;
use Core\Security\PublicHttpsUrl;
use Core\Update\SignatureVerifier;

final class MarketplaceService
{
    public function __construct(private Connection $db, private SignatureVerifier $verifier) {}

    public function addSource(string $label, string $url): int
    {
        $label = trim(strip_tags($label)); $url = trim($url);
        if ($label === '' || !PublicHttpsUrl::valid($url)) throw new \InvalidArgumentException('Catalog sources require a label and public HTTPS URL.');
        if ($this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('marketplace_sources') . '` WHERE catalog_url = ?', [$url])) {
            throw new \InvalidArgumentException('That catalog source is already registered.');
        }
        return (int) $this->db->insert('marketplace_sources', ['label' => mb_substr($label, 0, 120), 'catalog_url' => $url, 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function refresh(int $id): array
    {
        $source = $this->db->fetch('SELECT * FROM `' . $this->db->table('marketplace_sources') . '` WHERE id = ? AND active = 1', [$id]);
        if (!$source) throw new \RuntimeException('Marketplace source not found.');
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL is required to refresh catalogs.');
        $ch = curl_init((string) $source['catalog_url']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXFILESIZE => 2 * 1024 * 1024,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: Website-Core-Marketplace/' . WC_VERSION]]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        if (!is_string($body) || $status !== 200) {
            $message = $error !== '' ? $error : 'Catalog returned HTTP ' . $status . '.';
            $this->failed($id, $message); throw new \RuntimeException($message);
        }
        try { $catalog = json_decode($body, true, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { $this->failed($id, 'Catalog JSON is malformed.'); throw new \RuntimeException('Catalog JSON is malformed.'); }
        try {
            return $this->ingest($id, $catalog);
        } catch (\Throwable $e) {
            $this->failed($id, $e->getMessage());
            throw $e;
        }
    }

    /** Validate and persist a catalog already obtained from its configured source. */
    public function ingest(int $sourceId, array $catalog): array
    {
        if (($catalog['product'] ?? '') !== 'website-core-catalog' || (int) ($catalog['format'] ?? 0) !== 1) throw new \InvalidArgumentException('Unsupported marketplace catalog.');
        [$verified, $error] = $this->verifier->verify($catalog);
        if (!$verified) throw new \RuntimeException('Catalog signature check failed: ' . $error);
        $packages = $this->normalizeEntries((array) ($catalog['packages'] ?? []), false);
        $updates = $this->normalizeEntries((array) ($catalog['core_updates'] ?? []), true);
        $normalized = ['product' => 'website-core-catalog', 'format' => 1, 'generated_at' => $catalog['generated_at'] ?? null,
            'packages' => $packages, 'core_updates' => $updates, 'signature' => $catalog['signature'] ?? null];
        $this->db->update('marketplace_sources', ['catalog_json' => json_encode($normalized, JSON_UNESCAPED_SLASHES),
            'last_error' => null, 'last_checked_at' => date('Y-m-d H:i:s')], 'id = ?', [$sourceId]);
        return $normalized;
    }

    public function sources(): array { return $this->db->fetchAll('SELECT * FROM `' . $this->db->table('marketplace_sources') . '` ORDER BY label'); }
    public function disable(int $id): void { $this->db->update('marketplace_sources', ['active' => 0], 'id = ?', [$id]); }
    public function enable(int $id): bool { return $this->db->update('marketplace_sources', ['active' => 1], 'id = ?', [$id]) > 0; }
    public function remove(int $id): bool
    {
        $source = $this->db->fetch('SELECT active FROM `' . $this->db->table('marketplace_sources') . '` WHERE id = ?', [$id]);
        if (!$source) return false;
        if ((bool) $source['active']) throw new \RuntimeException('Disable the catalog source before removing it.');
        return $this->db->run('DELETE FROM `' . $this->db->table('marketplace_sources') . '` WHERE id = ?', [$id])->rowCount() > 0;
    }

    public function packages(): array
    {
        $out = [];
        foreach ($this->sources() as $source) {
            if (!(bool) $source['active']) continue;
            $catalog = json_decode((string) ($source['catalog_json'] ?? ''), true);
            foreach ((array) ($catalog['packages'] ?? []) as $package) $out[] = $package + ['source_id' => (int) $source['id'], 'source' => $source['label']];
        }
        return $out;
    }

    public function latestCore(string $channel = 'stable'): ?array
    {
        $best = null;
        foreach ($this->sources() as $source) {
            if (!(bool) $source['active']) continue;
            $catalog = json_decode((string) ($source['catalog_json'] ?? ''), true);
            foreach ((array) ($catalog['core_updates'] ?? []) as $release) {
                if (($release['channel'] ?? 'stable') !== $channel) continue;
                $candidate = $release + ['source_id' => (int) $source['id'], 'source' => $source['label']];
                if ($best === null || version_compare($candidate['version'], $best['version'], '>')) $best = $candidate;
            }
        }
        return $best;
    }

    private function normalizeEntries(array $entries, bool $core): array
    {
        $out = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', (string) ($entry['version'] ?? ''))) continue;
            $url = (string) ($entry['download_url'] ?? ''); $hash = strtolower((string) ($entry['sha256'] ?? ''));
            if (!PublicHttpsUrl::valid($url) || !preg_match('/^[a-f0-9]{64}$/', $hash)) continue;
            if ($core) $out[] = ['version' => $entry['version'], 'download_url' => $url, 'sha256' => $hash,
                'channel' => in_array(($entry['channel'] ?? 'stable'), ['stable', 'beta'], true) ? $entry['channel'] : 'stable',
                'severity' => ($entry['severity'] ?? '') === 'security' ? 'security' : 'feature'];
            else {
                $id = (string) ($entry['package_id'] ?? '');
                if (!preg_match('/^[a-z0-9][a-z0-9._\/-]+$/', $id)) continue;
                $out[] = ['package_id' => $id, 'name' => mb_substr(strip_tags((string) ($entry['name'] ?? $id)), 0, 120),
                    'type' => in_array(($entry['type'] ?? ''), ['industry', 'addon', 'theme'], true) ? $entry['type'] : 'addon',
                    'version' => $entry['version'], 'description' => mb_substr(strip_tags((string) ($entry['description'] ?? '')), 0, 500),
                    'download_url' => $url, 'sha256' => $hash];
            }
        }
        return $out;
    }

    private function failed(int $id, string $message): void
    {
        $this->db->update('marketplace_sources', ['last_error' => mb_substr($message, 0, 1000), 'last_checked_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }
}
