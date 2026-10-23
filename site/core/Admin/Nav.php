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

    /** @return list<array{route:string,label:string,cap:?string,group:string,group_key:string,package:?string}> */
    public function all(): array
    {
        $nav = [
            $this->core('/admin',            'Dashboard',      null,                    'Overview',       'core-overview'),
            $this->core('/admin/search',     'Search',         null,                    'Overview',       'core-overview'),
            $this->core('/admin/notices',    'Notifications',  null,                    'Overview',       'core-overview'),
            $this->core('/admin/pages',      'Content',        'content.manage',        'Website',        'core-website'),
            $this->core('/admin/patterns',   'Layout patterns','content.manage',        'Website',        'core-website'),
            $this->core('/admin/forms',      'Forms',          'forms.manage',          'Website',        'core-website'),
            $this->core('/admin/navigation', 'Navigation',     'navigation.manage',     'Website',        'core-website'),
            $this->core('/admin/seo',        'SEO',            'navigation.manage',     'Website',        'core-website'),
            $this->core('/admin/media',      'Media',          'media.manage',          'Website',        'core-website'),
            $this->core('/admin/appearance', 'Appearance',     'appearance.manage',     'Design',         'core-design'),
            $this->core('/admin/users',      'Administrators', 'admins.view',           'Administration', 'core-administration'),
            $this->core('/admin/security',   'Security',       'admins.view',           'Administration', 'core-administration'),
            $this->core('/admin/modules',    'Modules',        'modules.view',          'System',         'core-system'),
            $this->core('/admin/backups',    'Backups',        'backups.manage',        'System',         'core-system'),
            $this->core('/admin/operations', 'Operations',     'system.view',           'System',         'core-system'),
            $this->core('/admin/health',     'Health',         'system.view',           'System',         'core-system'),
            $this->core('/admin/updates',    'Updates',        'system.update',         'System',         'core-system'),
            $this->core('/admin/audit',      'Audit',          'audit.view',            'System',         'core-system'),
            $this->core('/admin/settings',   'Settings',       'settings.manage',       'System',         'core-system'),
        ];
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
                ];
            }
        }
        return $nav;
    }

    /** @return array{route:string,label:string,cap:?string,group:string,group_key:string,package:null} */
    private function core(
        string $route,
        string $label,
        ?string $capability,
        string $group,
        string $groupKey
    ): array {
        return [
            'route' => $route,
            'label' => $label,
            'cap' => $capability,
            'group' => $group,
            'group_key' => $groupKey,
            'package' => null,
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
