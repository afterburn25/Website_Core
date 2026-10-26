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
        $analytics = null;
        $commerce = null;
        $sales = null;
        if ($this->user() && $this->auth()->has($this->user(), 'analytics.view')) {
            try {
                $analytics = $this->c->get(\Core\Analytics\AnalyticsReport::class)
                    ->dashboard(date('Y-m-d', time() - 6 * 86400), date('Y-m-d'))['summary'];
            } catch (\Throwable) {
                // Analytics migration may still be converging during an update.
            }
        }
        if ($registry->supportsFeature('commerce.orders') && $this->user() && $this->auth()->has($this->user(), 'orders.manage')) {
            $owner=(string)($registry->feature('commerce.orders')['package']??'');
            try {
                $commerce=[
                    'new'=>(int)$db->fetchColumn('SELECT COUNT(*) FROM `'.$db->table('commerce_orders').'` WHERE source_package=? AND status=?',[$owner,'new']),
                    'pending'=>(int)$db->fetchColumn('SELECT COUNT(*) FROM `'.$db->table('commerce_orders').'` WHERE source_package=? AND payment_status=?',[$owner,'pending']),
                    'revenue'=>(int)$db->fetchColumn('SELECT COALESCE(SUM(total_minor),0) FROM `'.$db->table('commerce_orders').'` WHERE source_package=? AND payment_status=?',[$owner,'paid']),
                    'currency'=>(string)($registry->feature('commerce.orders')['options']['currency']??'USD'),
                ];
            } catch (\Throwable) { $commerce=null; }
        }
        if ($registry->supportsFeature('crm.inquiries') && $this->user() && $this->auth()->has($this->user(), 'inquiries.manage')) {
            $owner=(string)($registry->feature('crm.inquiries')['package']??'');
            try {
                $sales=[
                    'new'=>(int)$db->fetchColumn('SELECT COUNT(*) FROM `'.$db->table('crm_inquiries').'` WHERE owner_package=? AND status=?',[$owner,'new']),
                    'urgent'=>(int)$db->fetchColumn('SELECT COUNT(*) FROM `'.$db->table('crm_inquiries').'` WHERE owner_package=? AND priority=? AND status NOT IN (\'won\',\'lost\',\'archived\')',[$owner,'urgent']),
                    'sent'=>$registry->supportsFeature('crm.quotes')?(int)$db->fetchColumn('SELECT COUNT(*) FROM `'.$db->table('crm_quotes').'` WHERE owner_package=? AND status=?',[$owner,'sent']):0,
                ];
            } catch (\Throwable) { $sales=null; }
        }

        return $this->page($r, 'dashboard/index', [
            'pageCount' => $pageCount,
            'jobs'      => $jobs,
            'packages'  => $packages,
            'published' => $published,
            'integrity' => $integrity,
            'notices'   => $notices,
            'moduleWidgets' => $moduleWidgets,
            'analytics' => $analytics,
            'commerce' => $commerce,
            'sales' => $sales,
            'php'       => PHP_VERSION,
            'dbVersion' => $db->driver() === 'sqlite'
                ? 'sqlite ' . $db->pdo()->query('SELECT sqlite_version()')->fetchColumn()
                : $db->fetchColumn('SELECT VERSION()'),
        ], 'Dashboard');
    }
}
