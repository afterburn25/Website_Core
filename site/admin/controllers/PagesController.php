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
        $trash = $r->string('view') === 'trash';
        $pages = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('pages') . '` WHERE deleted_at '
            . ($trash ? 'IS NOT NULL' : 'IS NULL') . ' ORDER BY slug'
        );
        // Flag slugs the router can never serve — pre-existing data and
        // pages shadowed when a module route appeared after the save.
        $shadowed = [];
        foreach ($pages as $p) {
            if ($this->slugShadowed((string) $p['slug'])) {
                $shadowed[(string) $p['slug']] = true;
            }
        }
        return $this->page($r, 'pages/index', [
            'pages' => $pages, 'sections' => $sections->all(), 'shadowed' => $shadowed,
            'trash' => $trash,
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
            } elseif ($this->slugShadowed($slug)) {
                $error = 'That slug is reserved or already handled by an installed module — the page would be unreachable.';
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
        $this->flash('ok', 'Preview link (24h, single-purpose): ' . $r->routeUrl('/preview/' . $token));
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
        $data = [
            'status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
            'publish_at' => null, 'unpublish_at' => null,
        ];
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

    public function schedule(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $publishAt = $this->dateTime($r->string('publish_at'));
        $unpublishAt = $this->dateTime($r->string('unpublish_at'));
        if ($publishAt && $unpublishAt && strtotime($unpublishAt) <= strtotime($publishAt)) {
            $this->flash('error', 'Unpublish time must be after the publish time.');
            return $this->redirect($r, '/admin/pages/edit/' . $id);
        }
        $this->db()->update('pages', [
            'publish_at' => $publishAt,
            'unpublish_at' => $unpublishAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND deleted_at IS NULL', [$id]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('page.schedule', $this->user()->id, [
            'page' => $id, 'publish_at' => $publishAt, 'unpublish_at' => $unpublishAt,
        ]);
        $this->flash('ok', ($publishAt || $unpublishAt) ? 'Publishing schedule saved.' : 'Publishing schedule cleared.');
        return $this->redirect($r, '/admin/pages/edit/' . $id);
    }

    public function duplicate(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $db = $this->db();
        $page = $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$page) return Response::notFound();
        $slug = (string) $page['slug'] . '-copy';
        $suffix = 2;
        while ($this->slugTaken($slug, 0)) $slug = (string) $page['slug'] . '-copy-' . $suffix++;
        $copy = [
            'slug' => $slug, 'title' => $page['title'] . ' (Copy)', 'body' => $page['body'],
            'status' => 'draft', 'meta_title' => $page['meta_title'],
            'meta_description' => $page['meta_description'], 'updated_by' => $this->user()->id,
            'published_at' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            'noindex' => $page['noindex'] ?? 0, 'canonical' => $page['canonical'] ?? null,
            'og_media_id' => $page['og_media_id'] ?? null, 'parent_id' => $page['parent_id'] ?? null,
            'publish_at' => null, 'unpublish_at' => null, 'deleted_at' => null,
        ];
        $newId = (int) $db->insert('pages', $copy);
        $this->flash('ok', 'Page duplicated as a draft.');
        return $this->redirect($r, '/admin/pages/edit/' . $newId);
    }

    public function trash(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $this->db()->update('pages', [
            'deleted_at' => date('Y-m-d H:i:s'), 'status' => 'draft',
            'publish_at' => null, 'unpublish_at' => null, 'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('page.trashed', $this->user()->id, ['page' => $id]);
        $this->flash('ok', 'Page moved to trash.');
        return $this->redirect($r, '/admin/pages');
    }

    public function restore(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $this->db()->update('pages', [
            'deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        $this->flash('ok', 'Page restored as a draft.');
        return $this->redirect($r, '/admin/pages?view=trash');
    }

    private function dateTime(string $value): ?string
    {
        if ($value === '') return null;
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function slugTaken(string $slug, int $excludeId): bool
    {
        return (bool) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db()->table('pages') . '` WHERE slug = ? AND id != ?',
            [$slug, $excludeId]
        );
    }

    /**
     * A saved page is only useful if the router can reach it: the catch-all
     * excludes core-reserved segments, and any concrete (module) route
     * matching the slug outscores or predates it.
     */
    private function slugShadowed(string $slug): bool
    {
        $first = strtolower((string) (explode('/', $slug)[0] ?? ''));
        if (in_array($first, \Core\Application::RESERVED_SLUGS, true)) {
            return true;
        }
        return $this->c->get(\Core\Routing\Router::class)->isConcreteMatch('/' . $slug);
    }

    private function can(): bool
    {
        $u = $this->user();
        return $u !== null && $this->auth()->has($u, 'content.manage');
    }
}
