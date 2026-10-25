<?php
declare(strict_types=1);

namespace Core\Api;

use Core\Config;
use Core\Database\Connection;
use Core\Jobs\JobQueue;
use Core\Security\SecretBox;
use Core\Security\PublicHttpsUrl;

final class WebhookService
{
    public const EVENTS = ['page.saved', 'page.published', 'page.draft', 'page.review_requested', 'backup.created'];

    public function __construct(private Connection $db, private JobQueue $jobs, private Config $config) {}

    /** @return array{id:int,secret:string} */
    public function create(string $label, string $url, array $events): array
    {
        $label = trim(strip_tags($label));
        if ($label === '') throw new \InvalidArgumentException('A webhook label is required.');
        if (!PublicHttpsUrl::valid($url)) throw new \InvalidArgumentException('Webhook URLs must be public HTTPS URLs.');
        $events = array_values(array_intersect(self::EVENTS, array_map('strval', $events)));
        if ($events === []) throw new \InvalidArgumentException('Select at least one event.');
        $secret = bin2hex(random_bytes(24));
        $id = (int) $this->db->insert('webhooks', [
            'label' => mb_substr($label, 0, 120), 'url' => $url,
            'events_json' => json_encode($events, JSON_UNESCAPED_SLASHES),
            'secret_hash' => hash('sha256', $secret),
            'secret_cipher' => (new SecretBox($this->config))->seal($secret), 'active' => 1,
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
        $secret = (new SecretBox($this->config))->open((string) $row['secret_cipher']);
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
    public function setActive(int $id, bool $active): bool
    {
        return $this->db->update('webhooks', ['active' => $active ? 1 : 0], 'id = ?', [$id]) > 0;
    }

    public function disable(int $id): void { $this->setActive($id, false); }

    public function update(int $id, string $label, string $url, array $events): bool
    {
        $label = trim(strip_tags($label));
        if ($label === '') throw new \InvalidArgumentException('A webhook label is required.');
        if (!PublicHttpsUrl::valid($url)) throw new \InvalidArgumentException('Webhook URLs must be public HTTPS URLs.');
        $events = array_values(array_intersect(self::EVENTS, array_map('strval', $events)));
        if ($events === []) throw new \InvalidArgumentException('Select at least one event.');
        return $this->db->update('webhooks', ['label' => mb_substr($label, 0, 120), 'url' => $url, 'events_json' => json_encode($events, JSON_UNESCAPED_SLASHES)], 'id = ?', [$id]) > 0;
    }

    /** @return array{id:int,secret:string} */
    public function rotateSecret(int $id): array
    {
        $row = $this->db->fetch('SELECT id FROM `' . $this->db->table('webhooks') . '` WHERE id = ?', [$id]);
        if (!$row) throw new \InvalidArgumentException('Webhook not found.');
        $secret = bin2hex(random_bytes(24));
        $this->db->update('webhooks', ['secret_hash' => hash('sha256', $secret), 'secret_cipher' => (new SecretBox($this->config))->seal($secret)], 'id = ?', [$id]);
        return ['id' => $id, 'secret' => $secret];
    }

    public function test(int $id): int
    {
        $hook = $this->db->fetch('SELECT id,active FROM `' . $this->db->table('webhooks') . '` WHERE id = ?', [$id]);
        if (!$hook || !(bool) $hook['active']) throw new \InvalidArgumentException('Enable the webhook before sending a test.');
        $body = ['event' => 'webhook.test', 'created_at' => date('c'), 'data' => ['message' => 'Website Core test delivery']];
        $delivery = (int) $this->db->insert('webhook_deliveries', ['webhook_id' => $id, 'event' => 'webhook.test', 'payload_json' => json_encode($body, JSON_UNESCAPED_SLASHES), 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')]);
        $this->jobs->dispatch('webhook.deliver', ['delivery_id' => $delivery]);
        return $delivery;
    }

    public function retry(int $deliveryId): bool
    {
        $row = $this->db->fetch(
            'SELECT d.id,w.active FROM `' . $this->db->table('webhook_deliveries') . '` d JOIN `'
            . $this->db->table('webhooks') . '` w ON w.id=d.webhook_id WHERE d.id = ?',
            [$deliveryId],
        );
        if (!$row || !(bool) $row['active']) return false;
        $this->db->update('webhook_deliveries', [
            'status' => 'pending', 'http_status' => null, 'last_error' => null, 'attempted_at' => null,
        ], 'id = ?', [$deliveryId]);
        $this->jobs->dispatch('webhook.deliver', ['delivery_id' => $deliveryId]);
        return true;
    }

}
