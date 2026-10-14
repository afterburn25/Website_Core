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

    /** @return list<array{route:string,label:string,cap:?string}> */
    public function all(): array
    {
        $nav = [
            ['route' => '/admin',           'label' => 'Dashboard',     'cap' => null],
            ['route' => '/admin/pages',     'label' => 'Content',       'cap' => 'content.manage'],
            ['route' => '/admin/navigation','label' => 'Navigation',    'cap' => 'navigation.manage'],
            ['route' => '/admin/media',     'label' => 'Media',         'cap' => 'media.manage'],
            ['route' => '/admin/modules',   'label' => 'Modules',       'cap' => 'modules.view'],
            ['route' => '/admin/appearance','label' => 'Appearance',    'cap' => 'appearance.manage'],
            ['route' => '/admin/users',     'label' => 'Administrators','cap' => 'admins.view'],
            ['route' => '/admin/backups',   'label' => 'Backups',       'cap' => 'backups.manage'],
            ['route' => '/admin/health',    'label' => 'Health',        'cap' => 'system.view'],
            ['route' => '/admin/updates',   'label' => 'Updates',       'cap' => 'system.update'],
            ['route' => '/admin/audit',     'label' => 'Audit',         'cap' => 'audit.view'],
            ['route' => '/admin/settings',  'label' => 'Settings',      'cap' => 'settings.manage'],
        ];
        if ($this->registry !== null) {
            foreach ($this->registry->adminNavItems() as $item) {
                $nav[] = ['route' => $item['route'], 'label' => $item['label'], 'cap' => $item['capability']];
            }
        }
        return $nav;
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
