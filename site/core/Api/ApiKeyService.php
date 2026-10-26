<?php
declare(strict_types=1);

namespace Core\Api;

use Core\Database\Connection;
use Core\Http\Request;

final class ApiKeyService
{
    public const CAPABILITIES = ['health.read', 'content.read'];

    public function __construct(private Connection $db) {}

    /** @return array{id:int,token:string} */
    public function create(string $label, array $capabilities): array
    {
        $label = trim(strip_tags($label));
        if ($label === '') throw new \InvalidArgumentException('An API key label is required.');
        $caps = array_values(array_intersect(self::CAPABILITIES, array_map('strval', $capabilities)));
        if ($caps === []) throw new \InvalidArgumentException('Select at least one API capability.');
        $token = 'wcp_' . bin2hex(random_bytes(24));
        $id = (int) $this->db->insert('api_keys', [
            'label' => mb_substr($label, 0, 120),
            'token_hash' => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 12),
            'capabilities_json' => json_encode($caps, JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['id' => $id, 'token' => $token];
    }

    public function authenticate(Request $request, string $capability): ?array
    {
        if (!preg_match('/^Bearer\s+(wcp_[a-f0-9]{48})$/i', $request->header('Authorization'), $match)) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('api_keys') . '` WHERE token_hash = ? AND revoked_at IS NULL',
            [hash('sha256', $match[1])]
        );
        if (!$row) return null;
        $caps = json_decode((string) $row['capabilities_json'], true);
        if (!is_array($caps) || !in_array($capability, $caps, true)) return null;
        $this->db->update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
        return $row;
    }

    public function revoke(int $id): void
    {
        $this->db->update('api_keys', ['revoked_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    /** @return array{id:int,token:string} */
    public function rotate(int $id): array
    {
        return $this->db->transaction(function () use ($id): array {
            $row = $this->db->fetch('SELECT * FROM `' . $this->db->table('api_keys') . '` WHERE id = ? AND revoked_at IS NULL', [$id]);
            if (!$row) throw new \InvalidArgumentException('Active API key not found.');
            $capabilities = json_decode((string) $row['capabilities_json'], true);
            $created = $this->create((string) $row['label'], is_array($capabilities) ? $capabilities : []);
            $this->revoke($id);
            return $created;
        });
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM `' . $this->db->table('api_keys') . '` ORDER BY id DESC');
    }
}
