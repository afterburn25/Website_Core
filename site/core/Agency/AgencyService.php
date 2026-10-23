<?php
declare(strict_types=1);

namespace Core\Agency;

use Core\Config;
use Core\Database\Connection;
use Core\Security\PublicHttpsUrl;
use Core\Security\SecretBox;

final class AgencyService
{
    public function __construct(private Connection $db, private Config $config) {}

    public function add(string $label, string $baseUrl, string $apiToken): int
    {
        $label = trim(strip_tags($label)); $baseUrl = rtrim(trim($baseUrl), '/'); $apiToken = trim($apiToken);
        if ($label === '' || !PublicHttpsUrl::valid($baseUrl) || parse_url($baseUrl, PHP_URL_QUERY) !== null
            || parse_url($baseUrl, PHP_URL_FRAGMENT) !== null || !preg_match('/^wcp_[a-f0-9]{48}$/', $apiToken)) {
            throw new \InvalidArgumentException('Enter a label, public HTTPS site URL and valid Website Core API key.');
        }
        return (int) $this->db->insert('agency_sites', ['label' => mb_substr($label, 0, 120), 'base_url' => $baseUrl,
            'token_cipher' => (new SecretBox($this->config))->seal($apiToken), 'status' => 'unknown', 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function check(int $id): array
    {
        $site = $this->db->fetch('SELECT * FROM `' . $this->db->table('agency_sites') . '` WHERE id = ?', [$id]);
        if (!$site) throw new \RuntimeException('Agency site not found.');
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL is required for agency health checks.');
        $url = rtrim((string) $site['base_url'], '/') . '/index.php?p=/api/v1/health';
        $token = (new SecretBox($this->config))->open((string) $site['token_cipher']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token, 'User-Agent: Website-Core-Agency/' . WC_VERSION]]);
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        $ok = $status === 200 && is_array($data) && ($data['ok'] ?? false) === true;
        $message = $ok ? null : ($error !== '' ? $error : 'Remote health endpoint returned HTTP ' . $status . '.');
        $this->db->update('agency_sites', ['status' => $ok ? 'healthy' : 'error', 'remote_site' => $ok ? mb_substr((string) ($data['site'] ?? ''), 0, 190) : null,
            'core_version' => $ok ? mb_substr((string) ($data['core'] ?? ''), 0, 40) : null,
            'industry_versions_json' => $ok ? json_encode((array) ($data['industries'] ?? []), JSON_UNESCAPED_SLASHES) : null,
            'last_error' => $message,
            'last_checked_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        return ['ok' => $ok, 'error' => $message, 'data' => $data];
    }

    public function all(): array { return $this->db->fetchAll('SELECT id,label,base_url,status,remote_site,core_version,industry_versions_json,last_error,last_checked_at,created_at FROM `' . $this->db->table('agency_sites') . '` ORDER BY label'); }
    public function remove(int $id): void { $this->db->run('DELETE FROM `' . $this->db->table('agency_sites') . '` WHERE id = ?', [$id]); }
}
