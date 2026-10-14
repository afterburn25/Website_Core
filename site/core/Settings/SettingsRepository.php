<?php
declare(strict_types=1);

namespace Core\Settings;

use Core\Database\Connection;

/**
 * Typed key/value settings store with an in-request cache. Values are stored
 * as JSON so arrays/bools round-trip; secrets live in config.php, not here.
 */
final class SettingsRepository
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private Connection $db) {}

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $row = $this->db->fetch(
            'SELECT value FROM `' . $this->db->table('settings') . '` WHERE `key` = ?',
            [$key]
        );
        $value = $row ? json_decode((string) $row['value'], true) : $default;
        $this->cache[$key] = $value;
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES);
        $table = $this->db->table('settings');
        if ($this->db->driver() === 'sqlite') {
            $this->db->run(
                "INSERT INTO `{$table}` (`key`,`value`,`updated_at`) VALUES (?,?,?)
                 ON CONFLICT(`key`) DO UPDATE SET `value`=excluded.`value`,`updated_at`=excluded.`updated_at`",
                [$key, $json, date('Y-m-d H:i:s')]
            );
        } else {
            $this->db->run(
                "INSERT INTO `{$table}` (`key`,`value`,`updated_at`) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),`updated_at`=VALUES(`updated_at`)",
                [$key, $json, date('Y-m-d H:i:s')]
            );
        }
        $this->cache[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT `key`,`value` FROM `' . $this->db->table('settings') . '`') as $row) {
            $out[$row['key']] = json_decode((string) $row['value'], true);
        }
        return $out;
    }

    public function forget(string $key): void
    {
        $this->db->run(
            'DELETE FROM `' . $this->db->table('settings') . '` WHERE `key` = ?',
            [$key]
        );
        unset($this->cache[$key]);
    }
}
