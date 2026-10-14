<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

final class PagesController extends Controller
{
    public function index(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        $sections = new \Core\Content\SectionService($this->db());
        if ($r->isPost()) {
            $action = $r->string('action');
            if ($action === 'section_save') {
                [$ok, $err] = $sections->save(
                    $r->string('key'), $r->string('label'),
                    (string) ($r->post['body'] ?? ''), $this->user()->id
                );
                $this->flash($err ? 'error' : 'ok', $err ?? 'Section saved.');
            } elseif ($action === 'section_delete') {
                $sections->delete((int) $r->string('id'));
                $this->flash('ok', 'Section removed.');
            }
            return $this->redirect($r, '/admin/pages');
        }
        $pages = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('pages') . '` ORDER BY slug'
        );
        return $this->page($r, 'pages/index', [
            'pages' => $pages, 'sections' => $sections->all(),
        ], 'Content');
    }

    public function edit(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        $db = $this->db();
        $id = isset($r->attributes['id']) ? (int) $r->attributes['id'] : (int) $r->input('id', 0);
        $page = $id > 0
            ? $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ?', [$id])
            : null;
        $error = null;

        if ($r->isPost()) {
            $title = $r->string('title');
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $r->string('slug') !== '' ? $r->string('slug') : $title));
            $slug = trim($slug, '-');
            $body = (string) ($r->post['body'] ?? '');
            $body = (new \Core\Content\Sanitizer())->html($body);

            if ($title === '' || $slug === '') {
                $error = 'Title and slug are required.';
            } elseif ($this->slugTaken($slug, $id)) {
                $error = 'That slug is already in use.';
            } else {
                $seo = [
                    'meta_title' => $r->string('meta_title'),
                    'meta_description' => $r->string('meta_description'),
                    'noindex' => $r->string('noindex') !== '' ? 1 : 0,
                    'canonical' => $r->string('canonical'),
                    'og_media_id' => ($og = $r->string('og_media_id')) !== '' ? (int) $og : null,
                ];
                if ($page) {
                    // Save a revision before overwriting (revision recovery).
                    $db->insert('page_revisions', [
                        'page_id' => $id, 'title' => $page['title'], 'body' => $page['body'],
                        'saved_by' => $this->user()->id, 'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $db->update('pages', array_merge([
                        'title' => $title, 'slug' => $slug, 'body' => $body,
                        'updated_by' => $this->user()->id,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], $seo), 'id = ?', [$id]);
                } else {
                    $id = (int) $db->insert('pages', array_merge([
                        'title' => $title, 'slug' => $slug, 'body' => $body,
                        'status' => 'draft',
                        'updated_by' => $this->user()->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], $seo));
                }
                $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
                    'page.saved', ['page' => $id, 'slug' => $slug, 'by' => $this->user()->id]);
                $this->flash('ok', 'Saved as draft.');
                return $this->redirect($r, '/admin/pages/edit/' . $id);
            }
        }

        $revisions = $page ? $db->fetchAll(
            'SELECT * FROM `' . $db->table('page_revisions') . '` WHERE page_id = ? ORDER BY id DESC LIMIT 20',
            [$id]
        ) : [];

        return $this->page($r, 'pages/edit', [
            'page'      => $page,
            'error'     => $error,
            'revisions' => $revisions,
        ], $page ? 'Edit page' : 'New page');
    }

    public function previewLink(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        $id = (int) $r->attributes['id'];
        $tokens = new \Core\Content\PreviewTokens($this->db());
        $token = $tokens->issue($id, $this->user()->id);
        $this->flash('ok', 'Preview link (24h, single-purpose): ' . $r->basePath . '/preview/' . $token);
        return $this->redirect($r, '/admin/pages/edit/' . $id);
    }

    public function restoreRevision(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        $id = (int) $r->attributes['id'];
        $rid = (int) $r->attributes['rid'];
        $db = $this->db();
        $rev = $db->fetch(
            'SELECT * FROM `' . $db->table('page_revisions') . '` WHERE id = ? AND page_id = ?',
            [$rid, $id]
        );
        if (!$rev) {
            return Response::notFound();
        }
        // Keep the current body recoverable too, then restore as draft.
        $page = $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ?', [$id]);
        if ($page) {
            $db->insert('page_revisions', [
                'page_id' => $id, 'title' => $page['title'], 'body' => $page['body'],
                'saved_by' => $this->user()->id, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->update('pages', [
                'title' => $rev['title'], 'body' => $rev['body'], 'status' => 'draft',
                'updated_by' => $this->user()->id, 'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
        }
        $this->flash('ok', 'Revision restored as draft.');
        return $this->redirect($r, '/admin/pages/edit/' . $id);
    }

    public function publish(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        $id = (int) $r->attributes['id'];
        $action = $r->string('do', 'publish');
        $status = match ($action) {
            'unpublish' => 'draft',
            default     => 'published',
        };
        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($status === 'published') {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        $this->db()->update('pages', $data, 'id = ?', [$id]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('page.' . $status, $this->user()->id, ['page' => $id]);
        $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
            'page.' . $status, ['page' => $id, 'by' => $this->user()->id]);
        $this->flash('ok', $status === 'published' ? 'Page published.' : 'Page unpublished.');
        return $this->redirect($r, '/admin/pages');
    }

    private function slugTaken(string $slug, int $excludeId): bool
    {
        return (bool) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db()->table('pages') . '` WHERE slug = ? AND id != ?',
            [$slug, $excludeId]
        );
    }

    private function can(): bool
    {
        $u = $this->user();
        return $u !== null && $this->auth()->has($u, 'content.manage');
    }
}
