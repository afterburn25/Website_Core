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
            } elseif ($action === 'rename') {
                $patternId = (int) $r->string('id');
                $error = $patterns->rename($patternId, $r->string('name'));
                if (!$error) $this->audit('patterns.renamed', ['pattern' => $patternId]);
                $this->flash($error ? 'error' : 'ok', $error ?? 'Pattern renamed.');
            } elseif ($action === 'duplicate') {
                [$copyId, $error] = $patterns->duplicate((int) $r->string('id'), $u->id);
                if (!$error) $this->audit('patterns.duplicated', ['source' => (int) $r->string('id'), 'pattern' => $copyId]);
                $this->flash($error ? 'error' : 'ok', $error ?? 'Pattern duplicated.');
            } elseif ($action === 'delete') {
                $patternId = (int) $r->string('id');
                $deleted = $patterns->delete($patternId);
                if ($deleted) $this->audit('patterns.deleted', ['pattern' => $patternId]);
                $this->flash($deleted ? 'ok' : 'error', $deleted ? 'Pattern removed.' : 'That pattern was already removed.');
            } else {
                $this->flash('error', 'That pattern action is not available.');
            }
            return $this->redirect($r, '/admin/patterns');
        }
        return $this->page($r, 'patterns/index', [
            'patterns' => $patterns->all(),
            'pages' => $this->db()->fetchAll('SELECT id,title,slug FROM `' . $this->db()->table('pages') . '` WHERE deleted_at IS NULL ORDER BY title'),
        ], 'Layout patterns');
    }
}
