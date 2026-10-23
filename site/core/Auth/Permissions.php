<?php
declare(strict_types=1);

namespace Core\Auth;

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
            'content.manage', 'forms.manage', 'media.manage', 'navigation.manage',
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
        return isset(self::ROLES[$role]);
    }

    /** @return list<string> */
    public function roles(): array
    {
        return array_keys(self::ROLES);
    }

    public function allows(string $role, string $capability): bool
    {
        $caps = self::ROLES[$role] ?? [];
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
}
