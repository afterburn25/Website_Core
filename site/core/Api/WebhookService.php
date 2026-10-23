<?php
declare(strict_types=1);

namespace Core\Api;

use Core\Config;
use Core\Database\Connection;
use Core\Jobs\JobQueue;

final class WebhookService
{
    public const EVENTS = ['page.saved', 'page.published', 'page.draft', 'page.review_requested', 'backup.created'];

    public function __construct(private Connection $db, private JobQueue $jobs, private Config $config) {}

    /** @return array{id:int,secret:string} */
    public function create(string $label, string $url, array $events): array
    {
        $label = trim(strip_tags($label));
        if ($label === '') throw new \InvalidArgumentException('A webhook label is required.');
        if (!$this->validHttpsUrl($url)) throw new \InvalidArgumentException('Webhook URLs must be public HTTPS URLs.');
        $events = array_values(array_intersect(self::EVENTS, array_map('strval', $events)));
        if ($events === []) throw new \InvalidArgumentException('Select at least one event.');
        $secret = bin2hex(random_bytes(24));
        $id = (int) $this->db->insert('webhooks', [
            'label' => mb_substr($label, 0, 120), 'url' => $url,
            'events_json' => json_encode($events, JSON_UNESCAPED_SLASHES),
            'secret_hash' => hash('sha256', $secret),
            'secret_cipher' => $this->seal($secret), 'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['id' => $id, 'secret' => $secret];
    }

    public function queue(string $event, array $payload): void
    {
        if (!in_array($event, self::EVENTS, true)) return;
        foreach ($this->db->fetchAll('SELECT * FROM `' . $this->db->table('webhooks') . '` WHERE active = 1') as $hook) {
            $events = json_decode((string) $hook['events_json'], true);
            if (!is_array($events) || !in_array($event, $events, true)) continue;
            $body = ['event' => $event, 'created_at' => date('c'), 'data' => $payload];
            $delivery = (int) $this->db->insert('webhook_deliveries', [
                'webhook_id' => (int) $hook['id'], 'event' => $event,
                'payload_json' => json_encode($body, JSON_UNESCAPED_SLASHES),
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->jobs->dispatch('webhook.deliver', ['delivery_id' => $delivery]);
        }
    }

    public function deliver(int $deliveryId): void
    {
        $row = $this->db->fetch('SELECT d.*,w.url,w.secret_cipher,w.active FROM `' . $this->db->table('webhook_deliveries') . '` d JOIN `' . $this->db->table('webhooks') . '` w ON w.id=d.webhook_id WHERE d.id = ?', [$deliveryId]);
        if (!$row || !(bool) $row['active']) return;
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL is required for webhook delivery.');
        $secret = $this->open((string) $row['secret_cipher']);
        $body = (string) $row['payload_json'];
        $signature = hash_hmac('sha256', $body, $secret);
        $ch = curl_init((string) $row['url']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: Website-Core/' . WC_VERSION, 'X-WC-Signature: sha256=' . $signature]]);
        curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        $ok = $status >= 200 && $status < 300;
        $this->db->update('webhook_deliveries', ['status' => $ok ? 'delivered' : 'failed', 'http_status' => $status ?: null,
            'last_error' => $ok ? null : mb_substr($error !== '' ? $error : 'HTTP ' . $status, 0, 1000), 'attempted_at' => date('Y-m-d H:i:s')], 'id = ?', [$deliveryId]);
        if (!$ok) throw new \RuntimeException($error !== '' ? $error : 'Webhook returned HTTP ' . $status);
    }

    public function all(): array { return $this->db->fetchAll('SELECT * FROM `' . $this->db->table('webhooks') . '` ORDER BY id DESC'); }
    public function recent(): array { return $this->db->fetchAll('SELECT d.*,w.label FROM `' . $this->db->table('webhook_deliveries') . '` d JOIN `' . $this->db->table('webhooks') . '` w ON w.id=d.webhook_id ORDER BY d.id DESC LIMIT 50'); }
    public function disable(int $id): void { $this->db->update('webhooks', ['active' => 0], 'id = ?', [$id]); }

    private function validHttpsUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') return false;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || strtolower($host) === 'localhost') return false;
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) return false;
        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        }
        return true;
    }

    private function key(): string
    {
        return hash('sha256', (string) $this->config->get('security.app_key', ''), true);
    }

    private function seal(string $plain): string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 'sodium:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key()));
        }
        throw new \RuntimeException('Sodium is required to protect webhook secrets.');
    }

    private function open(string $cipher): string
    {
        $raw = base64_decode(substr($cipher, 7), true);
        if (!str_starts_with($cipher, 'sodium:') || $raw === false) throw new \RuntimeException('Webhook secret is unreadable.');
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key());
        if ($plain === false) throw new \RuntimeException('Webhook secret could not be decrypted.');
        return $plain;
    }
}
