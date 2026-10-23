<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Content\RedirectService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Seo\NotFoundLog;

final class SeoController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'navigation.manage')) return $this->forbidden();
        $log = $this->c->get(NotFoundLog::class);
        if ($r->isPost()) {
            if ($r->string('action') === 'redirect') {
                [$ok, $error] = (new RedirectService($this->db()))->save(
                    null, $r->string('source'), $r->string('target'), 301, $u->id
                );
                $this->flash($ok ? 'ok' : 'error', $error ?? 'Permanent redirect created.');
            } elseif ($r->string('action') === 'clear') {
                $count = $log->clear();
                $this->flash('ok', "Cleared {$count} logged path(s).");
            }
            return $this->redirect($r, '/admin/seo');
        }
        return $this->page($r, 'seo/index', [
            'missing' => $log->top(),
            'pages' => $this->db()->fetchAll(
                'SELECT slug, title FROM `' . $this->db()->table('pages')
                . "` WHERE deleted_at IS NULL AND status = 'published' ORDER BY title"
            ),
        ], 'SEO');
    }
}
