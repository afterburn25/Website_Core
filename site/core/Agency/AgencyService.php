<?php
declare(strict_types=1);

namespace Core\Agency;

use Core\Config;
use Core\Database\Connection;
use Core\Security\PublicHttpsUrl;
use Core\Security\SecretBox;

final class AgencyService
{
    private const MAX_RESPONSE_BYTES = 1048576;

    public function __construct(private Connection $db, private Config $config) {}

    public function add(string $label, string $baseUrl, string $apiToken): int
    {
        [$label, $baseUrl] = $this->validatedIdentity($label, $baseUrl);
        $apiToken = $this->validatedToken($apiToken);
        if ($this->findByUrl($baseUrl)) throw new \InvalidArgumentException('That site is already registered. Edit its existing connection instead.');
        $now = date('Y-m-d H:i:s');
        return (int) $this->db->insert('agency_sites', [
            'label' => $label, 'base_url' => $baseUrl,
            'token_cipher' => (new SecretBox($this->config))->seal($apiToken),
            'status' => 'unknown', 'active' => 1, 'token_updated_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function update(int $id, string $label, string $baseUrl, string $apiToken = ''): void
    {
        $site = $this->find($id);
        [$label, $baseUrl] = $this->validatedIdentity($label, $baseUrl);
        $duplicate = $this->findByUrl($baseUrl);
        if ($duplicate && (int) $duplicate['id'] !== $id) throw new \InvalidArgumentException('That site URL belongs to another managed connection.');
        $data = ['label' => $label, 'base_url' => $baseUrl, 'updated_at' => date('Y-m-d H:i:s')];
        if (trim($apiToken) !== '') {
            $data['token_cipher'] = (new SecretBox($this->config))->seal($this->validatedToken($apiToken));
            $data['token_updated_at'] = date('Y-m-d H:i:s');
            $data['status'] = 'unknown';
            $data['last_error'] = null;
            $data['consecutive_failures'] = 0;
        }
        $this->db->update('agency_sites', $data, 'id = ?', [(int) $site['id']]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->find($id);
        $this->db->update('agency_sites', [
            'active' => $active ? 1 : 0,
            'status' => $active ? 'unknown' : 'paused',
            'last_error' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function check(int $id): array
    {
        $site = $this->find($id);
        if (isset($site['active']) && !(bool) $site['active']) throw new \RuntimeException('Enable this connection before checking it.');
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL is required for agency health checks.');
        if (!PublicHttpsUrl::valid((string) $site['base_url'])) throw new \RuntimeException('The saved site URL no longer resolves to a public HTTPS address.');
        $url = rtrim((string) $site['base_url'], '/') . '/index.php?p=/api/v1/health';
        $token = (new SecretBox($this->config))->open((string) $site['token_cipher']);
        $body = '';
        $ch = curl_init($url);
        $started = microtime(true);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token, 'User-Agent: Website-Core-Agency/' . WC_VERSION],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $executed = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        $latency = max(0, (int) round((microtime(true) - $started) * 1000));
        $data = $executed !== false ? json_decode($body, true) : null;
        $ok = $status === 200 && is_array($data) && ($data['ok'] ?? false) === true;
        $message = $ok ? null : $this->failureMessage($error, $status, strlen($body));
        $update = [
            'status' => $ok ? 'healthy' : 'error',
            'last_error' => $message,
            'last_latency_ms' => $latency,
            'consecutive_failures' => $ok ? 0 : ((int) ($site['consecutive_failures'] ?? 0) + 1),
            'last_checked_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($ok) {
            $update['remote_site'] = mb_substr(trim((string) ($data['site'] ?? '')), 0, 190);
            $update['core_version'] = mb_substr(trim((string) ($data['core'] ?? '')), 0, 40);
            $update['industry_versions_json'] = json_encode(array_values((array) ($data['industries'] ?? [])), JSON_UNESCAPED_SLASHES);
        }
        $this->db->update('agency_sites', $update, 'id = ?', [$id]);
        return ['ok' => $ok, 'error' => $message, 'data' => $data];
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT id,label,base_url,active,status,remote_site,core_version,industry_versions_json,last_error,last_checked_at,last_latency_ms,consecutive_failures,token_updated_at,created_at,updated_at FROM `' . $this->db->table('agency_sites') . '` ORDER BY label');
    }

    public function remove(int $id): void
    {
        $this->find($id);
        $this->db->run('DELETE FROM `' . $this->db->table('agency_sites') . '` WHERE id = ?', [$id]);
    }

    private function find(int $id): array
    {
        if ($id < 1) throw new \InvalidArgumentException('Choose a valid agency site.');
        $site = $this->db->fetch('SELECT * FROM `' . $this->db->table('agency_sites') . '` WHERE id = ?', [$id]);
        if (!$site) throw new \RuntimeException('Agency site not found.');
        return $site;
    }

    private function findByUrl(string $baseUrl): ?array
    {
        return $this->db->fetch('SELECT id FROM `' . $this->db->table('agency_sites') . '` WHERE base_url = ?', [$baseUrl]);
    }

    private function validatedIdentity(string $label, string $baseUrl): array
    {
        $label = trim(strip_tags($label));
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($label === '' || mb_strlen($label) > 120 || mb_strlen($baseUrl) > 500 || !PublicHttpsUrl::valid($baseUrl)
            || parse_url($baseUrl, PHP_URL_QUERY) !== null || parse_url($baseUrl, PHP_URL_FRAGMENT) !== null) {
            throw new \InvalidArgumentException('Enter a label up to 120 characters and a public HTTPS base URL without a query or fragment.');
        }
        return [$label, $baseUrl];
    }

    private function validatedToken(string $apiToken): string
    {
        $apiToken = trim($apiToken);
        if (!preg_match('/^wcp_[a-f0-9]{48}$/', $apiToken)) throw new \InvalidArgumentException('Enter a valid Website Core API key.');
        return $apiToken;
    }

    private function failureMessage(string $curlError, int $status, int $bytes): string
    {
        if ($bytes >= self::MAX_RESPONSE_BYTES) return 'Remote response exceeded the 1 MB safety limit.';
        if ($curlError !== '') return 'Secure connection failed. Verify the URL, certificate and hosting firewall.';
        if ($status === 401 || $status === 403) return 'Authentication failed. Rotate the remote health.read key.';
        if ($status === 404) return 'Website Core health endpoint was not found at this URL.';
        if ($status >= 500) return 'The remote site reported a server error (HTTP ' . $status . ').';
        if ($status !== 200) return 'Remote health endpoint returned HTTP ' . $status . '.';
        return 'Remote health response was not valid Website Core JSON.';
    }
}
