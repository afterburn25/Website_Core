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
    private const CAPABILITY_LABELS = [
        'content.manage' => ['Content', 'Manage content', 'Create and edit pages, translations, and reusable layouts.'],
        'content.publish' => ['Content', 'Publish content', 'Publish, schedule, and approve public pages.'],
        'forms.manage' => ['Website', 'Manage forms', 'Create forms and work with visitor submissions.'],
        'analytics.view' => ['Analytics', 'View analytics', 'Review privacy-first traffic, visitor journeys, geography, and exports.'],
        'media.manage' => ['Website', 'Manage media', 'Upload, organize, replace, and remove media.'],
        'navigation.manage' => ['Website', 'Manage navigation', 'Change header/footer links and redirects.'],
        'appearance.manage' => ['Website', 'Manage appearance', 'Change brand assets, design tokens, and themes.'],
        'commerce.manage' => ['Store', 'Manage products', 'Create products, pricing, inventory, and store settings.'],
        'orders.manage' => ['Store', 'Manage orders', 'Review orders and update fulfillment and payment status.'],
        'customers.manage' => ['Customers', 'Manage customers', 'Review and disable public customer accounts.'],
        'settings.manage' => ['System', 'Manage settings', 'Change site-wide configuration and security policy.'],
        'modules.view' => ['Extensions', 'View modules', 'Review installed modules, add-ons, and themes.'],
        'modules.install' => ['Extensions', 'Manage modules', 'Install, update, enable, disable, and remove packages.'],
        'system.view' => ['System', 'View system tools', 'Review health, operations, updates, and diagnostics.'],
        'audit.view' => ['System', 'View audit log', 'Inspect administrator and system activity evidence.'],
        'backups.manage' => ['System', 'Manage backups', 'Create, restore, replicate, and remove recovery points.'],
        'admins.view' => ['Access', 'View administrators', 'Review administrator accounts and security posture.'],
        'admins.manage_nonowner' => ['Access', 'Manage non-owner accounts', 'Create, update, disable, and reset non-owner administrators.'],
    ];
    /** Core capabilities by role. 'owner' implicitly has all capabilities. */
    private const ROLES = [
        'owner'  => ['*'],
        'admin'  => [
            'content.manage', 'content.publish', 'forms.manage', 'media.manage', 'navigation.manage',
            'appearance.manage', 'commerce.manage', 'orders.manage', 'customers.manage', 'settings.manage', 'modules.view', 'modules.install',
            'system.view', 'audit.view', 'backups.manage',
            'admins.view', 'admins.manage_nonowner',
        ],
        'editor' => [
            'content.manage', 'forms.manage', 'media.manage',
        ],
    ];

    /** @var array<string, string> extra capability => description (module-declared) */
    private array $extensions = [];
    /** @var array<string,true> Core capabilities made relevant by enabled modules. */
    private array $optionalActive = [];
    private const OPTIONAL = ['commerce.manage', 'orders.manage', 'customers.manage'];

    public function __construct(private ?Connection $db = null) {}

    public function register(string $capability, string $description = ''): void
    {
        $this->extensions[$capability] = $description;
    }

    public function activateOptional(string $capability): void
    {
        if (in_array($capability, self::OPTIONAL, true)) $this->optionalActive[$capability] = true;
    }

    /** @return list<string> */
    public function allCapabilities(): array
    {
        $core = array_values(array_unique(array_merge(...array_values(
            array_filter(self::ROLES, fn ($c) => $c !== ['*'])
        ))));
        $core = array_values(array_filter($core, fn(string $capability): bool => !in_array($capability, self::OPTIONAL, true) || isset($this->optionalActive[$capability])));
        return array_merge($core, array_keys($this->extensions));
    }

    /** @return array<string,array{group:string,label:string,description:string}> */
    public function capabilityCatalog(): array
    {
        $catalog = [];
        foreach ($this->allCapabilities() as $capability) {
            [$group, $label, $description] = self::CAPABILITY_LABELS[$capability]
                ?? ['Module permissions', ucwords(str_replace(['.', '_'], ' ', $capability)), $this->extensions[$capability] ?? 'Permission supplied by an installed package.'];
            $catalog[$capability] = compact('group', 'label', 'description');
        }
        uasort($catalog, static fn (array $a, array $b): int => [$a['group'], $a['label']] <=> [$b['group'], $b['label']]);
        return $catalog;
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
        if (in_array($capability, self::OPTIONAL, true) && !isset($this->optionalActive[$capability])) return false;
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
