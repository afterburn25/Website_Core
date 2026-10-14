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

        return $this->page($r, 'dashboard/index', [
            'pageCount' => $pageCount,
            'jobs'      => $jobs,
            'packages'  => $packages,
            'php'       => PHP_VERSION,
            'dbVersion' => $db->driver() === 'sqlite'
                ? 'sqlite ' . $db->pdo()->query('SELECT sqlite_version()')->fetchColumn()
                : $db->fetchColumn('SELECT VERSION()'),
        ], 'Dashboard');
    }
}
