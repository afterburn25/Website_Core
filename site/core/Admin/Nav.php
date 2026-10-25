<?php
declare(strict_types=1);

namespace Core\Admin;

use Core\Auth\AdminUser;
use Core\Auth\AuthService;
use Core\Modules\ModuleRegistry;

/**
 * Admin navigation builder shared by core controllers AND packages: core
 * items plus every booted package's adminPage() registrations, filtered by
 * the current user's capabilities (server-side — a hidden item is never a
 * substitute for a capability check on the route).
 */
final class Nav
{
    public function __construct(
        private ?ModuleRegistry $registry,
        private ?AuthService $auth,
    ) {}

    /** @return list<array{route:string,label:string,cap:?string,group:string,group_key:string,package:?string,icon:string}> */
    public function all(): array
    {
        $nav = [
            $this->core('/admin',            'Dashboard',      null,                    'Overview',       'core-overview', 'dashboard'),
            $this->core('/admin/search',     'Search',         null,                    'Overview',       'core-overview', 'search'),
            $this->core('/admin/notices',    'Notifications',  null,                    'Overview',       'core-overview', 'bell'),
            $this->core('/admin/analytics',  'Dashboard',      'analytics.view',        'Analytics',      'core-analytics', 'analytics'),
            $this->core('/admin/analytics/visitors','Visitor Explorer','analytics.view','Analytics',      'core-analytics', 'visitors'),
            $this->core('/admin/analytics/geography','Geography','analytics.view',      'Analytics',      'core-analytics', 'geography'),
            $this->core('/admin/agency',      'Client sites',   'system.update',         'Agency',         'core-agency', 'sites'),
            $this->core('/admin/pages',      'Content',        'content.manage',        'Website',        'core-website', 'content'),
            $this->core('/admin/patterns',   'Layout patterns','content.manage',        'Website',        'core-website', 'patterns'),
            $this->core('/admin/translations','Translations',  'content.manage',        'Website',        'core-website', 'translations'),
            $this->core('/admin/forms',      'Forms',          'forms.manage',          'Website',        'core-website', 'forms'),
            $this->core('/admin/navigation', 'Navigation Menu','navigation.manage',     'Website',        'core-website', 'navigation'),
            $this->core('/admin/seo',        'SEO',            'navigation.manage',     'Website',        'core-website', 'seo'),
            $this->core('/admin/media',      'Media',          'media.manage',          'Website',        'core-website', 'media'),
            $this->core('/admin/appearance', 'Appearance',     'appearance.manage',     'Website',        'core-website', 'appearance'),
            $this->core('/admin/privacy',    'Privacy',        'settings.manage',       'Administration', 'core-administration', 'privacy'),
            $this->core('/admin/integrations','Integrations',  'settings.manage',       'Administration', 'core-administration', 'integrations'),
            $this->core('/admin/users',      'Administrators', 'admins.view',           'Administration', 'core-administration', 'users'),
            $this->core('/admin/roles',      'Roles',          'admins.view',           'Administration', 'core-administration', 'roles'),
            $this->core('/admin/security',   'Security',       'admins.view',           'Administration', 'core-administration', 'security'),
            $this->core('/admin/modules',    'Modules',        'modules.view',          'System',         'core-system', 'modules'),
            $this->core('/admin/marketplace','Marketplace',    'system.update',         'System',         'core-system', 'marketplace'),
            $this->core('/admin/backups',    'Backups',        'backups.manage',        'System',         'core-system', 'backups'),
            $this->core('/admin/operations', 'Operations',     'system.view',           'System',         'core-system', 'operations'),
            $this->core('/admin/health',     'Health',         'system.view',           'System',         'core-system', 'health'),
            $this->core('/admin/updates',    'Updates',        'system.update',         'System',         'core-system', 'updates'),
            $this->core('/admin/audit',      'Audit',          'audit.view',            'System',         'core-system', 'audit'),
            $this->core('/admin/settings',   'Settings',       'settings.manage',       'System',         'core-system', 'settings'),
        ];
        $featureNav = [];
        if ($this->registry?->supportsFeature('commerce.catalog')) {
            $featureNav[] = $this->core('/admin/store/products', 'Products', 'commerce.manage', 'Store', 'core-store', 'products');
        }
        if ($this->registry?->supportsFeature('commerce.orders')) {
            $featureNav[] = $this->core('/admin/store/orders', 'Orders', 'orders.manage', 'Store', 'core-store', 'orders');
        }
        if ($this->registry?->supportsFeature('commerce.checkout')) {
            $featureNav[] = $this->core('/admin/store/settings', 'Store settings', 'commerce.manage', 'Store', 'core-store', 'settings');
        }
        if ($this->registry?->supportsFeature('accounts.customers')) {
            $featureNav[] = $this->core('/admin/customers', 'Customers', 'customers.manage', 'Customers', 'core-customers', 'users');
        }
        if ($featureNav !== []) array_splice($nav, 15, 0, $featureNav);
        if ($this->registry !== null) {
            foreach ($this->registry->adminNavItems() as $item) {
                $package = (string) $item['package'];
                $fallback = str_replace(['-', '_'], ' ', basename(str_replace('\\', '/', $package)));
                $group = trim((string) ($item['category'] ?? '')) ?: ucwords($fallback);
                $nav[] = [
                    'route' => $item['route'],
                    'label' => $item['label'],
                    'cap' => $item['capability'],
                    'group' => $group,
                    'group_key' => 'package-' . substr(hash('sha256', $package), 0, 12),
                    'package' => $package,
                    'icon' => 'modules',
                ];
            }
        }
        return $nav;
    }

    /** @return array{route:string,label:string,cap:?string,group:string,group_key:string,package:null,icon:string} */
    private function core(
        string $route,
        string $label,
        ?string $capability,
        string $group,
        string $groupKey,
        string $icon,
    ): array {
        return [
            'route' => $route,
            'label' => $label,
            'cap' => $capability,
            'group' => $group,
            'group_key' => $groupKey,
            'package' => null,
            'icon' => $icon,
        ];
    }

    /** Items visible to $user (capability-filtered). */
    public function visible(?AdminUser $user): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $item) => $item['cap'] === null
                || ($user !== null && $this->auth?->has($user, (string) $item['cap']))
        ));
    }
}
