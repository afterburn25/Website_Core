<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Jobs\JobQueue;
use Core\Modules\ModuleRegistry;

final class DashboardController extends Controller
{
    public function index(Request $r): Response
    {
        $db = $this->db();
        $pageCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('pages') . '`');
        $jobs = $this->c->get(JobQueue::class)->counts();
        $registry = $this->c->get(ModuleRegistry::class);
        $packages = $registry->all();
        $moduleWidgets = array_values(array_filter(
            $registry->dashboardWidgets(),
            fn (array $widget): bool => empty($widget['capability'])
                || $this->auth()->has($this->user(), (string) $widget['capability'])
        ));
        $integrity = $this->c->get(\Core\System\Integrity::class)->summary();
        $notices = $this->c->get(\Core\Admin\NoticeCenter::class)->openFor($this->user()->id, 5);
        $published = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $db->table('pages') . '` WHERE status = ?', ['published']
        );

        return $this->page($r, 'dashboard/index', [
            'pageCount' => $pageCount,
            'jobs'      => $jobs,
            'packages'  => $packages,
            'published' => $published,
            'integrity' => $integrity,
            'notices'   => $notices,
            'moduleWidgets' => $moduleWidgets,
            'php'       => PHP_VERSION,
            'dbVersion' => $db->driver() === 'sqlite'
                ? 'sqlite ' . $db->pdo()->query('SELECT sqlite_version()')->fetchColumn()
                : $db->fetchColumn('SELECT VERSION()'),
        ], 'Dashboard');
    }
}
