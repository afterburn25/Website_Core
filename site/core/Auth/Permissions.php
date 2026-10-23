<?php
declare(strict_types=1);

namespace Core\Auth;

use Core\Database\Connection;

/**
 * Role → capability map plus a registry modules use to declare additional
 * capabilities. Capability checks are always server-side; navigation
 * visibility is derived from them, never a substitute for them.
 */
final class Permissions
{
    /** Core capabilities by role. 'owner' implicitly has all capabilities. */
    private const ROLES = [
        'owner'  => ['*'],
        'admin'  => [
            'content.manage', 'content.publish', 'forms.manage', 'media.manage', 'navigation.manage',
            'appearance.manage', 'settings.manage', 'modules.view', 'modules.install',
            'system.view', 'audit.view', 'backups.manage',
            'admins.view', 'admins.manage_nonowner',
        ],
        'editor' => [
            'content.manage', 'forms.manage', 'media.manage',
        ],
    ];

    /** @var array<string, string> extra capability => description (module-declared) */
    private array $extensions = [];

    public function __construct(private ?Connection $db = null) {}

    public function register(string $capability, string $description = ''): void
    {
        $this->extensions[$capability] = $description;
    }

    /** @return list<string> */
    public function allCapabilities(): array
    {
        $core = array_values(array_unique(array_merge(...array_values(
            array_filter(self::ROLES, fn ($c) => $c !== ['*'])
        ))));
        return array_merge($core, array_keys($this->extensions));
    }

    public function roleExists(string $role): bool
    {
        return isset(self::ROLES[$role]) || $this->custom($role) !== null;
    }

    /** @return list<string> */
    public function roles(): array
    {
        $roles = array_keys(self::ROLES);
        if ($this->db && $this->db->tableExists('custom_roles')) {
            $rows = $this->db->fetchAll(
                'SELECT slug FROM `' . $this->db->table('custom_roles') . '` ORDER BY label'
            );
            $roles = array_merge($roles, array_column($rows, 'slug'));
        }
        return $roles;
    }

    public function allows(string $role, string $capability): bool
    {
        $caps = self::ROLES[$role] ?? ($this->custom($role) ?? []);
        if (in_array('*', $caps, true)) {
            return true;
        }
        if (in_array($capability, $caps, true)) {
            return true;
        }
        // Extension capabilities are available to admin and owner by default.
        if ($role === 'admin' && isset($this->extensions[$capability])) {
            return true;
        }
        return false;
    }

    private function custom(string $role): ?array
    {
        if (!$this->db || !$this->db->tableExists('custom_roles')) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT capabilities_json FROM `' . $this->db->table('custom_roles') . '` WHERE slug = ?',
            [$role]
        );
        if (!$row) {
            return null;
        }
        $caps = json_decode((string) $row['capabilities_json'], true);
        return is_array($caps) ? array_values(array_map('strval', $caps)) : [];
    }
}
