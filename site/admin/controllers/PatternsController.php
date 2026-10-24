<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Content\LayoutPatterns;
use Core\Http\Request;
use Core\Http\Response;

final class PatternsController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'content.manage')) return $this->forbidden();
        $patterns = new LayoutPatterns($this->db());
        if ($r->isPost()) {
            $action = $r->string('action');
            if ($action === 'create') {
                [$patternId, $error] = $patterns->saveFromPage($r->string('name'), (int) $r->string('page_id'), $u->id);
                if (!$error) $this->audit('patterns.created', ['pattern' => $patternId, 'page' => (int) $r->string('page_id')]);
                $this->flash($error ? 'error' : 'ok', $error ?? 'Reusable pattern created.');
            } elseif ($action === 'apply') {
                $error = $patterns->apply((int) $r->string('pattern_id'), (int) $r->string('page_id'), $u->id);
                if (!$error) $this->audit('patterns.applied', ['pattern' => (int) $r->string('pattern_id'), 'page' => (int) $r->string('page_id')]);
                $this->flash($error ? 'error' : 'ok', $error ?? 'Pattern applied as a draft; the prior layout is in revisions.');
            } elseif ($action === 'delete') {
                $patterns->delete((int) $r->string('id')); $this->audit('patterns.deleted', ['pattern' => (int) $r->string('id')]); $this->flash('ok', 'Pattern removed.');
            }
            return $this->redirect($r, '/admin/patterns');
        }
        return $this->page($r, 'patterns/index', [
            'patterns' => $patterns->all(),
            'pages' => $this->db()->fetchAll('SELECT id,title,slug FROM `' . $this->db()->table('pages') . '` WHERE deleted_at IS NULL ORDER BY title'),
        ], 'Layout patterns');
    }
}
