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
                if ($err === null) {
                    $this->c->get(\Core\Audit\AuditLog::class)->record(
                        'section.saved', $this->user()->id, ['key' => $r->string('key')]
                    );
                }
                $this->flash($err ? 'error' : 'ok', $err ?? 'Section saved.');
            } elseif ($action === 'section_delete') {
                $deleted = $sections->delete((int) $r->string('id'));
                if ($deleted) {
                    $this->c->get(\Core\Audit\AuditLog::class)->record(
                        'section.deleted', $this->user()->id, ['section' => (int) $r->string('id')]
                    );
                }
                $this->flash($deleted ? 'ok' : 'error', $deleted ? 'Section removed.' : 'That section no longer exists.');
            }
            return $this->redirect($r, '/admin/pages');
        }
        $trash = $r->string('view') === 'trash';
        $query = mb_substr($r->string('q'), 0, 100);
        $status = in_array($r->string('status'), ['draft', 'published'], true)
            ? $r->string('status') : '';
        $review = in_array($r->string('review'), ['draft', 'pending', 'approved'], true)
            ? $r->string('review') : '';
        $pageNumber = max(1, (int) $r->input('page', 1));
        $perPage = 25;
        $where = ['deleted_at ' . ($trash ? 'IS NOT NULL' : 'IS NULL')];
        $params = [];
        if ($query !== '') {
            $where[] = '(title LIKE ? OR slug LIKE ?)';
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
        }
        if (!$trash && $status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if (!$trash && $review !== '') {
            $where[] = 'review_status = ?';
            $params[] = $review;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db()->table('pages') . '` WHERE ' . $whereSql,
            $params
        );
        $pageCount = max(1, (int) ceil($total / $perPage));
        $pageNumber = min($pageNumber, $pageCount);
        $offset = ($pageNumber - 1) * $perPage;
        $pages = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('pages') . '` WHERE ' . $whereSql
            . ' ORDER BY updated_at DESC, title ASC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
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
            'trash' => $trash, 'canPublish' => $this->canPublish(),
            'filters' => ['q' => $query, 'status' => $status, 'review' => $review],
            'pagination' => ['page' => $pageNumber, 'pages' => $pageCount, 'total' => $total],
            'counts' => $this->pageCounts(),
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
            ? $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ? AND deleted_at IS NULL', [$id])
            : null;
        if ($id > 0 && $page === null) {
            return Response::notFound();
        }
        $error = null;

        if ($r->isPost()) {
            $title = $r->string('title');
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $r->string('slug') !== '' ? $r->string('slug') : $title));
            $slug = trim($slug, '-');
            $body = (new \Core\Content\Sanitizer())->html((string) ($r->post['body'] ?? ''));
            $parentId = (int) $r->input('parent_id', 0);
            $parentId = $parentId > 0 ? $parentId : null;
            $canonical = $r->string('canonical');
            $ogMediaId = (int) $r->input('og_media_id', 0);
            $ogMediaId = $ogMediaId > 0 ? $ogMediaId : null;
            [$schemaJson, $schemaError] = $this->schemaJson($r->string('schema_json'));
            $blocks = new \Core\Content\PageBlocks(
                $db,
                $this->c->get(\Core\Modules\ModuleRegistry::class)
            );
            $submittedBlocks = (string) ($r->post['blocks_json'] ?? '[]');
            [$blocksJson, $blocksError] = $blocks->normalize($submittedBlocks);
            $page = array_merge($page ?? [], [
                'id' => $id, 'title' => $title, 'slug' => $slug, 'body' => $body,
                'blocks_json' => $submittedBlocks, 'meta_title' => $r->string('meta_title'),
                'meta_description' => $r->string('meta_description'),
                'noindex' => $r->string('noindex') !== '' ? 1 : 0,
                'canonical' => $canonical, 'og_media_id' => $ogMediaId,
                'schema_json' => $r->string('schema_json'), 'parent_id' => $parentId,
            ]);

            if ($title === '' || $slug === '') {
                $error = 'Title and slug are required.';
            } elseif (mb_strlen($title) > 190 || mb_strlen($slug) > 190) {
                $error = 'Title and slug must each be 190 characters or fewer.';
            } elseif ($blocksError !== null) {
                $error = $blocksError;
            } elseif ($schemaError !== null) {
                $error = $schemaError;
            } elseif (mb_strlen($r->string('meta_title')) > 190) {
                $error = 'Meta title must be 190 characters or fewer.';
            } elseif (mb_strlen($r->string('meta_description')) > 300) {
                $error = 'Meta description must be 300 characters or fewer.';
            } elseif ($canonical !== '' && !$this->validCanonical($canonical)) {
                $error = 'Canonical URL must be a complete http:// or https:// URL.';
            } elseif ($parentId !== null && (
                $parentId === $id || !$this->activePageExists($parentId) || $this->parentChainContains($parentId, $id)
            )) {
                $error = 'Select a valid parent page other than this page.';
            } elseif ($ogMediaId !== null && !$this->publicImageExists($ogMediaId)) {
                $error = 'Select a public image from the Media library for social sharing.';
            } elseif ($this->slugTaken($slug, $id)) {
                $error = 'That slug is already in use.';
            } elseif ($this->slugShadowed($slug)) {
                $error = 'That slug is reserved or already handled by an installed module — the page would be unreachable.';
            } elseif ($id > 0 && (int) $r->input('lock_version', 0) !== (int) ($page['lock_version'] ?? 1)) {
                $error = 'This page changed after you opened it. Copy any unsaved work, reload the page, and apply your changes again.';
            } else {
                $seo = [
                    'meta_title' => $r->string('meta_title'),
                    'meta_description' => $r->string('meta_description'),
                    'noindex' => $r->string('noindex') !== '' ? 1 : 0,
                    'canonical' => $canonical !== '' ? $canonical : null,
                    'og_media_id' => $ogMediaId,
                    'schema_json' => $schemaJson,
                    'parent_id' => $parentId,
                ];
                $storedPage = $id > 0
                    ? $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ?', [$id])
                    : null;
                if ($storedPage) {
                    $expectedLock = (int) $r->input('lock_version', 0);
                    try {
                        $db->transaction(function () use (
                            $db, $id, $storedPage, $expectedLock, $title, $slug, $body, $blocksJson, $seo
                        ): void {
                            $db->insert('page_revisions', [
                                'page_id' => $id, 'title' => $storedPage['title'], 'body' => $storedPage['body'],
                                'blocks_json' => $storedPage['blocks_json'] ?? null,
                                'snapshot_json' => $this->snapshot($storedPage),
                                'saved_by' => $this->user()->id, 'created_at' => date('Y-m-d H:i:s'),
                            ]);
                            $updated = $db->update('pages', array_merge([
                                'title' => $title, 'slug' => $slug, 'body' => $body,
                                'blocks_json' => $blocksJson, 'status' => 'draft',
                                'review_status' => 'draft', 'review_note' => null,
                                'reviewed_by' => null, 'reviewed_at' => null,
                                'updated_by' => $this->user()->id,
                                'updated_at' => date('Y-m-d H:i:s'),
                                'lock_version' => $expectedLock + 1,
                            ], $seo), 'id = ? AND lock_version = ?', [$id, $expectedLock]);
                            if ($updated !== 1) {
                                throw new \RuntimeException('page-edit-conflict');
                            }
                            $this->syncPageMediaUsages($id, $seo['og_media_id'], $blocksJson);
                        });
                    } catch (\RuntimeException $e) {
                        if ($e->getMessage() !== 'page-edit-conflict') {
                            throw $e;
                        }
                        $error = 'This page changed while you were editing it. Copy any unsaved work, reload the page, and apply your changes again.';
                    }
                } else {
                    $id = (int) $db->insert('pages', array_merge([
                        'title' => $title, 'slug' => $slug, 'body' => $body, 'blocks_json' => $blocksJson,
                        'status' => 'draft',
                        'review_status' => 'draft',
                        'lock_version' => 1,
                        'updated_by' => $this->user()->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], $seo));
                    $this->syncPageMediaUsages($id, $ogMediaId, $blocksJson);
                }
                if ($error === null) {
                    $this->c->get(\Core\Audit\AuditLog::class)->record(
                        $storedPage ? 'page.updated' : 'page.created',
                        $this->user()->id,
                        ['page' => $id, 'slug' => $slug]
                    );
                    $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
                        'page.saved', ['page' => $id, 'slug' => $slug, 'by' => $this->user()->id]);
                    $this->flash('ok', 'Draft saved. Publishing status and schedules were cleared for review.');
                    return $this->redirect($r, '/admin/pages/edit/' . $id);
                }
            }
        }

        $revisions = $page ? $db->fetchAll(
            'SELECT r.*, a.username AS saved_by_name FROM `' . $db->table('page_revisions') . '` r '
            . 'LEFT JOIN `' . $db->table('admins') . '` a ON a.id = r.saved_by '
            . 'WHERE r.page_id = ? ORDER BY r.id DESC LIMIT 20',
            [$id]
        ) : [];

        return $this->page($r, 'pages/edit', [
            'page'      => $page,
            'error'     => $error,
            'revisions' => $revisions,
            'blockDefinitions' => (new \Core\Content\PageBlocks(
                $db, $this->c->get(\Core\Modules\ModuleRegistry::class), $this->csrf()
            ))->definitions(),
            'seoAudit' => (new \Core\Seo\AccessibilityAuditor())->audit($page ?? []),
            'canPublish' => $this->canPublish(),
            'parentPages' => $db->fetchAll(
                'SELECT id,title,slug FROM `' . $db->table('pages')
                . '` WHERE deleted_at IS NULL AND id != ? ORDER BY title', [$id]
            ),
            'ogImages' => $db->fetchAll(
                'SELECT id,original_name FROM `' . $db->table('media')
                . "` WHERE is_private = 0 AND mime LIKE 'image/%' ORDER BY original_name"
            ),
        ], $page ? 'Edit page' : 'New page');
    }

    public function previewLink(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        $id = (int) $r->attributes['id'];
        if (!$this->activePageExists($id)) {
            return Response::notFound();
        }
        $tokens = new \Core\Content\PreviewTokens($this->db());
        $token = $tokens->issue($id, $this->user()->id);
        return $this->redirect($r, '/preview/' . $token);
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
        $page = $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ?', [$id]);
        if (!$page || !empty($page['deleted_at'])) {
            return Response::notFound();
        }
        $state = $this->revisionState($rev, $page);
        $slug = (string) $state['slug'];
        if ($this->slugTaken($slug, $id) || $this->slugShadowed($slug)) {
            $this->flash('error', 'That revision uses a URL which is no longer available. Change the current slug before restoring it.');
            return $this->redirect($r, '/admin/pages/edit/' . $id);
        }
        $parentId = (int) ($state['parent_id'] ?? 0);
        if ($parentId > 0 && (!$this->activePageExists($parentId) || $this->parentChainContains($parentId, $id))) {
            $this->flash('error', 'That revision refers to a missing or circular parent page. Choose a valid parent before restoring it.');
            return $this->redirect($r, '/admin/pages/edit/' . $id);
        }
        $db->transaction(function () use ($db, $id, $page, $state): void {
            $db->insert('page_revisions', [
                'page_id' => $id, 'title' => $page['title'], 'body' => $page['body'],
                'blocks_json' => $page['blocks_json'] ?? null,
                'snapshot_json' => $this->snapshot($page),
                'saved_by' => $this->user()->id, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->update('pages', array_merge($state, [
                'status' => 'draft', 'review_status' => 'draft', 'review_note' => null,
                'reviewed_by' => null, 'reviewed_at' => null,
                'publish_at' => null, 'unpublish_at' => null,
                'updated_by' => $this->user()->id, 'updated_at' => date('Y-m-d H:i:s'),
                'lock_version' => (int) ($page['lock_version'] ?? 1) + 1,
            ]), 'id = ?', [$id]);
            $this->syncPageMediaUsages(
                $id,
                isset($state['og_media_id']) ? (int) $state['og_media_id'] : null,
                (string) ($state['blocks_json'] ?? '[]')
            );
        });
        $this->c->get(\Core\Audit\AuditLog::class)->record(
            'page.revision_restored', $this->user()->id, ['page' => $id, 'revision' => $rid]
        );
        $this->flash('ok', 'Revision restored as draft.');
        return $this->redirect($r, '/admin/pages/edit/' . $id);
    }

    public function publish(Request $r): Response
    {
        if (!$this->canPublish()) {
            return $this->forbidden();
        }
        $id = (int) $r->attributes['id'];
        $page = $this->findPage($id, false);
        if ($page === null) return Response::notFound();
        $action = $r->string('do', 'publish');
        $status = match ($action) {
            'unpublish' => 'draft',
            default     => 'published',
        };
        $data = [
            'status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
            'publish_at' => null, 'unpublish_at' => null,
            'review_status' => $status === 'published' ? 'approved' : 'draft',
            'reviewed_by' => $this->user()->id,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'lock_version' => (int) ($page['lock_version'] ?? 1) + 1,
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
        if (!$this->canPublish()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $page = $this->findPage($id, false);
        if ($page === null) return Response::notFound();
        [$publishAt, $publishError] = $this->dateTime($r->string('publish_at'), 'Publish time');
        [$unpublishAt, $unpublishError] = $this->dateTime($r->string('unpublish_at'), 'Unpublish time');
        if ($publishError || $unpublishError) {
            $this->flash('error', $publishError ?? $unpublishError);
            return $this->redirect($r, '/admin/pages/edit/' . $id);
        }
        if ($publishAt && $unpublishAt && strtotime($unpublishAt) <= strtotime($publishAt)) {
            $this->flash('error', 'Unpublish time must be after the publish time.');
            return $this->redirect($r, '/admin/pages/edit/' . $id);
        }
        $this->db()->update('pages', [
            'publish_at' => $publishAt,
            'unpublish_at' => $unpublishAt,
            'updated_at' => date('Y-m-d H:i:s'),
            'lock_version' => (int) ($page['lock_version'] ?? 1) + 1,
        ], 'id = ? AND deleted_at IS NULL', [$id]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('page.schedule', $this->user()->id, [
            'page' => $id, 'publish_at' => $publishAt, 'unpublish_at' => $unpublishAt,
        ]);
        $this->flash('ok', ($publishAt || $unpublishAt) ? 'Publishing schedule saved.' : 'Publishing schedule cleared.');
        return $this->redirect($r, '/admin/pages/edit/' . $id);
    }

    public function requestReview(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $page = $this->findPage($id, false);
        if ($page === null) return Response::notFound();
        $note = trim($r->string('review_note'));
        $this->db()->update('pages', [
            'status' => 'draft', 'review_status' => 'pending',
            'review_note' => $note !== '' ? mb_substr($note, 0, 1000) : null,
            'reviewed_by' => null, 'reviewed_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
            'lock_version' => (int) ($page['lock_version'] ?? 1) + 1,
        ], 'id = ? AND deleted_at IS NULL', [$id]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('page.review_requested', $this->user()->id, ['page' => $id]);
        $this->c->get(\Core\Events\Dispatcher::class)->dispatch('page.review_requested', ['page' => $id, 'by' => $this->user()->id]);
        $this->flash('ok', 'Page submitted for review.');
        return $this->redirect($r, '/admin/pages/edit/' . $id);
    }

    public function compare(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $a = (int) $r->attributes['a'];
        $b = (int) $r->attributes['b'];
        $db = $this->db();
        $page = $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ? AND deleted_at IS NULL', [$id]);
        $left = $db->fetch('SELECT * FROM `' . $db->table('page_revisions') . '` WHERE id = ? AND page_id = ?', [$a, $id]);
        $right = $db->fetch('SELECT * FROM `' . $db->table('page_revisions') . '` WHERE id = ? AND page_id = ?', [$b, $id]);
        if (!$page || !$left || !$right) return Response::notFound();
        return $this->page($r, 'pages/compare', [
            'page' => $page, 'left' => $left, 'right' => $right,
            'leftState' => $this->revisionState($left, $page),
            'rightState' => $this->revisionState($right, $page),
        ], 'Compare revisions');
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
            'blocks_json' => $page['blocks_json'] ?? null,
            'status' => 'draft', 'meta_title' => $page['meta_title'],
            'meta_description' => $page['meta_description'], 'updated_by' => $this->user()->id,
            'published_at' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            'noindex' => $page['noindex'] ?? 0, 'canonical' => $page['canonical'] ?? null,
            'og_media_id' => $page['og_media_id'] ?? null, 'parent_id' => $page['parent_id'] ?? null,
            'schema_json' => $page['schema_json'] ?? null,
            'review_status' => 'draft', 'review_note' => null,
            'reviewed_by' => null, 'reviewed_at' => null,
            'publish_at' => null, 'unpublish_at' => null, 'deleted_at' => null,
            'lock_version' => 1,
        ];
        $newId = (int) $db->insert('pages', $copy);
        $this->syncPageMediaUsages(
            $newId,
            isset($copy['og_media_id']) ? (int) $copy['og_media_id'] : null,
            (string) ($copy['blocks_json'] ?? '[]')
        );
        $this->c->get(\Core\Audit\AuditLog::class)->record(
            'page.duplicated', $this->user()->id, ['source' => $id, 'page' => $newId]
        );
        $this->flash('ok', 'Page duplicated as a draft.');
        return $this->redirect($r, '/admin/pages/edit/' . $newId);
    }

    public function trash(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $page = $this->findPage($id, false);
        if ($page === null) return Response::notFound();
        $this->db()->update('pages', [
            'deleted_at' => date('Y-m-d H:i:s'), 'status' => 'draft',
            'publish_at' => null, 'unpublish_at' => null, 'updated_at' => date('Y-m-d H:i:s'),
            'lock_version' => (int) ($page['lock_version'] ?? 1) + 1,
        ], 'id = ?', [$id]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('page.trashed', $this->user()->id, ['page' => $id]);
        $this->flash('ok', 'Page moved to trash.');
        return $this->redirect($r, '/admin/pages');
    }

    public function restore(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $page = $this->findPage($id, true);
        if ($page === null) return Response::notFound();
        if ($this->slugShadowed((string) $page['slug'])) {
            $this->flash('error', 'This page cannot be restored until its URL no longer conflicts with a Core or module route.');
            return $this->redirect($r, '/admin/pages?view=trash');
        }
        $this->db()->update('pages', [
            'deleted_at' => null, 'status' => 'draft', 'updated_at' => date('Y-m-d H:i:s'),
            'lock_version' => (int) ($page['lock_version'] ?? 1) + 1,
        ], 'id = ?', [$id]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('page.restored', $this->user()->id, ['page' => $id]);
        $this->flash('ok', 'Page restored as a draft.');
        return $this->redirect($r, '/admin/pages?view=trash');
    }

    public function purge(Request $r): Response
    {
        if (!$this->canPublish()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $page = $this->findPage($id, true);
        if ($page === null) return Response::notFound();
        $db = $this->db();
        $db->transaction(function () use ($db, $id): void {
            foreach (['page_revisions', 'preview_tokens', 'page_translations'] as $table) {
                if ($db->tableExists($table)) {
                    $db->run('DELETE FROM `' . $db->table($table) . '` WHERE page_id = ?', [$id]);
                }
            }
            if ($db->tableExists('menu_items')) {
                $menuIds = array_map(
                    static fn (array $row): int => (int) $row['id'],
                    $db->fetchAll(
                        'SELECT id FROM `' . $db->table('menu_items') . '` WHERE page_id = ?', [$id]
                    )
                );
                if ($menuIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
                    $db->run(
                        'UPDATE `' . $db->table('menu_items')
                        . '` SET parent_id = NULL WHERE parent_id IN (' . $placeholders . ')', $menuIds
                    );
                }
                $db->run('DELETE FROM `' . $db->table('menu_items') . '` WHERE page_id = ?', [$id]);
            }
            if ($db->tableExists('media_usage')) {
                $db->run(
                    'DELETE FROM `' . $db->table('media_usage') . '` WHERE context = ? AND context_id = ?',
                    ['core.page', $id]
                );
            }
            $db->run(
                'UPDATE `' . $db->table('pages')
                . '` SET parent_id = NULL, lock_version = lock_version + 1 WHERE parent_id = ?', [$id]
            );
            $db->run('DELETE FROM `' . $db->table('pages') . '` WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        });
        $this->c->get(\Core\Audit\AuditLog::class)->record(
            'page.deleted', $this->user()->id, ['page' => $id, 'slug' => $page['slug']]
        );
        $this->flash('ok', 'Page and its revisions were permanently deleted.');
        return $this->redirect($r, '/admin/pages?view=trash');
    }

    /** @return array{0:?string,1:?string} */
    private function dateTime(string $value, string $label): array
    {
        if ($value === '') return [null, null];
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        $invalid = $date === false || $date->format('Y-m-d\TH:i') !== $value
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0));
        return $invalid
            ? [null, $label . ' is not a valid date and time.']
            : [$date->format('Y-m-d H:i:s'), null];
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

    private function canPublish(): bool
    {
        $u = $this->user();
        return $u !== null && $this->auth()->has($u, 'content.publish');
    }

    /** @return array{0:?string,1:?string} */
    private function schemaJson(string $json): array
    {
        if (trim($json) === '') return [null, null];
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [null, 'Schema.org JSON is not valid JSON.'];
        }
        if (!is_array($data) || array_is_list($data)) {
            return [null, 'Schema.org JSON must be one object, not a list or scalar value.'];
        }
        unset($data['@context']);
        return [
            json_encode(
                ['@context' => 'https://schema.org'] + $data,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            null,
        ];
    }

    private function validCanonical(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function activePageExists(int $id): bool
    {
        return (bool) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db()->table('pages')
            . '` WHERE id = ? AND deleted_at IS NULL', [$id]
        );
    }

    private function publicImageExists(int $id): bool
    {
        return (bool) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db()->table('media')
            . "` WHERE id = ? AND is_private = 0 AND mime LIKE 'image/%'", [$id]
        );
    }

    private function syncPageMediaUsages(int $pageId, ?int $socialImageId, string $blocksJson): void
    {
        $db = $this->db();
        $db->run(
            'DELETE FROM `' . $db->table('media_usage') . '` WHERE context = ? AND context_id = ?',
            ['core.page', $pageId]
        );
        $ids = (new \Core\Content\PageBlocks(
            $db,
            $this->c->get(\Core\Modules\ModuleRegistry::class)
        ))->mediaIds($blocksJson);
        if (($socialImageId ?? 0) > 0) $ids[] = (int) $socialImageId;
        foreach (array_values(array_unique($ids)) as $mediaId) {
            if (!$this->publicImageExists((int) $mediaId)) continue;
            $db->insert('media_usage', [
                'media_id' => (int) $mediaId,
                'context' => 'core.page',
                'context_id' => $pageId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function parentChainContains(int $parentId, int $pageId): bool
    {
        $seen = [];
        $current = $parentId;
        for ($depth = 0; $depth < 100 && $current > 0; $depth++) {
            if ($current === $pageId || isset($seen[$current])) return true;
            $seen[$current] = true;
            $row = $this->db()->fetch(
                'SELECT parent_id FROM `' . $this->db()->table('pages')
                . '` WHERE id = ? AND deleted_at IS NULL', [$current]
            );
            if ($row === null) return false;
            $current = (int) ($row['parent_id'] ?? 0);
        }
        return $current > 0;
    }

    private function findPage(int $id, bool $trashed): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM `' . $this->db()->table('pages') . '` WHERE id = ? AND deleted_at '
            . ($trashed ? 'IS NOT NULL' : 'IS NULL'), [$id]
        );
    }

    /** @return array{active:int,trash:int,draft:int,published:int,pending:int} */
    private function pageCounts(): array
    {
        $table = '`' . $this->db()->table('pages') . '`';
        return [
            'active' => (int) $this->db()->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL"),
            'trash' => (int) $this->db()->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NOT NULL"),
            'draft' => (int) $this->db()->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL AND status='draft'"),
            'published' => (int) $this->db()->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL AND status='published'"),
            'pending' => (int) $this->db()->fetchColumn("SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL AND review_status='pending'"),
        ];
    }

    private function snapshot(array $page): string
    {
        return json_encode(array_intersect_key($page, array_flip([
            'slug', 'title', 'body', 'blocks_json', 'meta_title', 'meta_description',
            'noindex', 'canonical', 'og_media_id', 'schema_json', 'parent_id',
        ])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function revisionState(array $revision, array $current): array
    {
        $state = null;
        if (!empty($revision['snapshot_json'])) {
            try {
                $decoded = json_decode((string) $revision['snapshot_json'], true, 32, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) $state = $decoded;
            } catch (\JsonException) {
                // A legacy or damaged snapshot falls back to the old columns.
            }
        }
        $state ??= [
            'slug' => $current['slug'], 'title' => $revision['title'],
            'body' => $revision['body'], 'blocks_json' => $revision['blocks_json'] ?? null,
            'meta_title' => $current['meta_title'] ?? null,
            'meta_description' => $current['meta_description'] ?? null,
            'noindex' => $current['noindex'] ?? 0,
            'canonical' => $current['canonical'] ?? null,
            'og_media_id' => $current['og_media_id'] ?? null,
            'schema_json' => $current['schema_json'] ?? null,
            'parent_id' => $current['parent_id'] ?? null,
        ];
        return array_intersect_key($state, array_flip([
            'slug', 'title', 'body', 'blocks_json', 'meta_title', 'meta_description',
            'noindex', 'canonical', 'og_media_id', 'schema_json', 'parent_id',
        ]));
    }
}
