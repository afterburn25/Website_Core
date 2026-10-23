<?php
declare(strict_types=1);

namespace Core\System;

use Core\Config;
use Core\Database\Connection;
use Core\Modules\ModuleRegistry;

/** Non-destructive installation checks used by health, dashboard and support. */
final class Integrity
{
    private const TABLES = [
        'admins', 'admin_sessions', 'settings', 'pages', 'page_revisions',
        'packages', 'package_journal', 'jobs', 'audit_log', 'media',
        'menus', 'menu_items', 'system_messages', 'admin_notices',
        'not_found_log', 'forms', 'form_submissions', 'page_patterns', 'migrations',
    ];

    public function __construct(
        private Connection $db,
        private Config $config,
        private ModuleRegistry $registry,
    ) {}

    /** @return list<array{name:string,status:string,detail:string}> */
    public function report(): array
    {
        $checks = [];
        $requiredFiles = [
            'Front controller' => WC_ROOT . '/index.php',
            'Bootstrap' => WC_ROOT . '/includes/bootstrap.php',
            'Base theme' => WC_ROOT . '/themes/base/theme.json',
        ];
        foreach ($requiredFiles as $name => $path) {
            $checks[] = $this->check($name, is_file($path), is_file($path) ? 'present' : 'missing');
        }

        foreach (self::TABLES as $table) {
            $exists = $this->db->tableExists($table);
            $checks[] = $this->check('Database: ' . $table, $exists, $exists ? 'present' : 'missing');
        }

        foreach (['storage', 'upload'] as $key) {
            $path = (string) $this->config->get('paths.' . $key, WC_ROOT . '/' . $key);
            $checks[] = $this->check(
                ucfirst($key) . ' writable',
                is_dir($path) && is_writable($path),
                $path
            );
        }

        $base = $this->registry->all()['core/base'] ?? null;
        $checks[] = $this->check(
            'Built-in base package',
            $base !== null && ($base['state'] ?? null) === 'enabled',
            $base === null ? 'not discovered' : (string) ($base['state'] ?? 'unknown')
        );
        return $checks;
    }

    /** @return array{ok:int,warn:int,error:int} */
    public function summary(): array
    {
        $summary = ['ok' => 0, 'warn' => 0, 'error' => 0];
        foreach ($this->report() as $check) {
            $summary[$check['status']]++;
        }
        return $summary;
    }

    /** @return array{name:string,status:string,detail:string} */
    private function check(string $name, bool $ok, string $detail): array
    {
        return ['name' => $name, 'status' => $ok ? 'ok' : 'error', 'detail' => $detail];
    }
}
