<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Jobs\JobQueue;

final class OperationsController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.view')) return $this->forbidden();
        $canOperate = $this->auth()->has($u, 'system.update');
        $config = $this->c->get(\Core\Config::class);
        $storage = (string) $config->get('paths.storage', WC_ROOT . '/storage');
        $queue = $this->c->get(JobQueue::class);

        if ($r->isPost()) {
            if (!$canOperate) return $this->forbidden();
            $action = $r->string('action');
            if ($action === 'maintenance') {
                $flag = $storage . '/maintenance.flag';
                if ($r->string('mode') === 'on') {
                    if (!is_dir($storage)) @mkdir($storage, 0750, true);
                    file_put_contents($flag, date('c') . ' by admin ' . $u->id);
                    $message = 'Maintenance mode enabled. Signed-in administrators remain available.';
                } else {
                    @unlink($flag);
                    $message = 'Maintenance mode disabled; the public site is live.';
                }
                $this->c->get(\Core\Audit\AuditLog::class)->record('operations.maintenance', $u->id, ['mode' => $r->string('mode')]);
                $this->flash('ok', $message);
            } elseif ($action === 'retry') {
                $ok = $queue->retry((int) $r->string('id'));
                $this->flash($ok ? 'ok' : 'error', $ok ? 'Job queued for retry.' : 'Only failed or uncertain jobs can be retried.');
            } elseif ($action === 'prune') {
                $count = $queue->pruneCompleted(30);
                $this->flash('ok', "Pruned {$count} completed job(s) older than 30 days.");
            } elseif ($action === 'clear_cache') {
                $count = $this->clearCaches($storage);
                if (function_exists('opcache_reset')) @opcache_reset();
                $this->flash('ok', "Cleared {$count} cached file(s) and reset OPcache when available.");
            } elseif ($action === 'flush_mail') {
                $sent = $this->c->get(\Core\Mail\SystemMail::class)->flush(25);
                $this->flash('ok', "Mail queue processed; {$sent} message(s) accepted by the configured transport.");
            } elseif ($action === 'queue_backup') {
                $queue->dispatch('core.backup', ['requested_by' => $u->id]);
                $this->flash('ok', 'Backup queued. Run cron to process it.');
            } elseif ($action === 'backup_schedule') {
                $days = (int) $r->string('days', '0');
                if (!in_array($days, [0, 1, 7, 30], true)) $days = 0;
                $this->settings()->set('backup.auto_days', $days);
                $this->flash('ok', $days ? "Automatic backup interval set to {$days} day(s)." : 'Automatic backups disabled.');
            }
            return $this->redirect($r, '/admin/operations');
        }

        return $this->page($r, 'operations/index', [
            'jobs' => $queue->recent(), 'counts' => $queue->counts(),
            'maintenance' => is_file($storage . '/maintenance.flag'),
            'canOperate' => $canOperate,
            'autoDays' => (int) $this->settings()->get('backup.auto_days', 0),
        ], 'Operations');
    }

    private function clearCaches(string $storage): int
    {
        $count = 0;
        foreach ([$storage . '/cache', $storage . '/media/variants'] as $dir) {
            if (!is_dir($dir)) continue;
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                if ($file->isFile() && @unlink($file->getPathname())) $count++;
                elseif ($file->isDir()) @rmdir($file->getPathname());
            }
        }
        return $count;
    }
}
