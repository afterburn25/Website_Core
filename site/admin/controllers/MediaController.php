<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Media\MediaService;
use Core\Http\Request;
use Core\Http\Response;

final class MediaController extends Controller
{
    private function svc(): MediaService
    {
        return new MediaService($this->db(), $this->c->get(\Core\Config::class));
    }

    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'media.manage')) {
            return $this->forbidden();
        }
        $media = $this->svc();

        if ($r->isPost()) {
            if (($err = $this->discardedPostError($r)) !== null) {
                $this->flash('error', $err);
                return $this->redirect($r, '/admin/media');
            }
            $action = $r->string('action');
            if ($action === 'upload' && isset($r->files['file'])) {
                [$id, $err] = $media->store(
                    $r->files['file'],
                    $u->id,
                    $r->string('private') === '1',
                    $r->string('folder')
                );
                if (!$err) {
                    $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
                        'media.uploaded', ['media' => $id, 'by' => $u->id]);
                    $this->c->get(\Core\Audit\AuditLog::class)->record('media.uploaded', $u->id, ['media' => $id]);
                }
                $this->flash($err ? 'error' : 'ok', $err ?? 'File uploaded.');
            } elseif ($action === 'delete') {
                $id = (int) $r->string('id');
                $usages = $media->usages($id);
                if ($usages !== [] && $r->string('confirm') !== '1') {
                    $this->flash('error', 'File is referenced ' . count($usages) . ' time(s) — resubmit to confirm deletion.');
                    return $this->redirect($r, '/admin/media?confirm=' . $id);
                }
                $deleted = $media->delete($id);
                if ($deleted) $this->c->get(\Core\Audit\AuditLog::class)->record('media.deleted', $u->id, ['media' => $id]);
                $this->flash($deleted ? 'ok' : 'error', $deleted ? 'File deleted.' : 'That media item no longer exists.');
            } elseif ($action === 'meta') {
                $this->db()->update('media', [
                    'alt_text' => $r->string('alt_text'),
                    'caption'  => $r->string('caption'),
                ], 'id = ?', [(int) $r->string('id')]);
                $media->organize((int) $r->string('id'), $r->string('folder'), $r->string('tags'),
                    (float) $r->input('focal_x', 50), (float) $r->input('focal_y', 50));
                $this->c->get(\Core\Audit\AuditLog::class)->record('media.metadata', $u->id, ['media' => (int) $r->string('id')]);
                $this->flash('ok', 'Metadata saved.');
            } elseif ($action === 'bulk') {
                $ids = $r->input('ids', []);
                $count = $media->bulkOrganize(is_array($ids) ? $ids : [], $r->string('folder'), $r->string('tags'));
                if ($count) $this->c->get(\Core\Audit\AuditLog::class)->record('media.bulk_organized', $u->id, ['count' => $count]);
                $this->flash($count ? 'ok' : 'error', $count ? "Updated {$count} media item(s)." : 'Select at least one media item.');
            } elseif ($action === 'replace') {
                if (($err = $this->uploadError($r, 'replacement')) === null) {
                    $err = $media->replace((int) $r->string('id'), $r->files['replacement'], $u->id);
                }
                if (!$err) $this->c->get(\Core\Audit\AuditLog::class)->record('media.replaced', $u->id, ['media' => (int) $r->string('id')]);
                $this->flash($err ? 'error' : 'ok', $err ?? 'File replaced; links and usages were preserved.');
            }
            return $this->redirect($r, '/admin/media');
        }

        $folder = $r->string('folder');
        $search = $r->string('q');
        $tag = $r->string('tag');
        $perPage = 36;
        $total = $media->count($folder, $search, $tag);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) $r->input('page', 1)));
        $summary = $this->db()->fetch(
            'SELECT COUNT(*) AS total, '
            . "SUM(CASE WHEN mime LIKE 'image/%' THEN 1 ELSE 0 END) AS images, "
            . 'SUM(CASE WHEN is_private = 1 THEN 1 ELSE 0 END) AS private_count '
            . 'FROM `' . $this->db()->table('media') . '`'
        ) ?: [];
        return $this->page($r, 'media/index', [
            'items'   => $media->list($folder, $perPage, $search, $tag, ($page - 1) * $perPage),
            'folders' => $this->folders(),
            'tags' => $media->tags(),
            'mediaService' => $media,
            'confirm' => (int) $r->input('confirm', 0),
            'filters' => compact('folder', 'search', 'tag'),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'summary' => [
                'total' => (int) ($summary['total'] ?? 0),
                'images' => (int) ($summary['images'] ?? 0),
                'private' => (int) ($summary['private_count'] ?? 0),
            ],
        ], 'Media');
    }

    private function folders(): array
    {
        return array_column(
            $this->db()->fetchAll(
                'SELECT DISTINCT folder FROM `' . $this->db()->table('media') . "` WHERE folder != '' ORDER BY folder"
            ),
            'folder'
        );
    }

    /**
     * Stream a media file through the resolver. Private files require an
     * authenticated admin; public files are cacheable.
     */
    public function serve(Request $r): Response
    {
        $id = (int) ($r->attributes['id'] ?? 0);
        $media = $this->svc()->find($id);
        if (!$media) {
            return Response::notFound();
        }
        if ((int) $media['is_private'] === 1) {
            $u = $this->user();
            if (!$u || !$this->auth()->has($u, 'media.manage')) {
                return Response::text('Forbidden', 403);
            }
        }
        $path = $this->svc()->pathFor($media);
        if (!is_file($path)) {
            return Response::notFound();
        }
        return Response::html('')
            ->header('Content-Type', $media['mime'])
            ->header('Content-Length', (string) filesize($path))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Disposition', 'inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $media['original_name']) . '"')
            ->header('Cache-Control', $media['is_private'] ? 'private, no-store' : 'public, max-age=86400')
            ->setBody((string) file_get_contents($path));
    }

    public function serveVariant(Request $r): Response
    {
        return $this->variantResponse($r, null);
    }

    public function serveCrop(Request $r): Response
    {
        return $this->variantResponse($r, (int) ($r->attributes['height'] ?? 360));
    }

    private function variantResponse(Request $r, ?int $height): Response
    {
        $id = (int) ($r->attributes['id'] ?? 0);
        $media = $this->svc()->find($id);
        if (!$media) return Response::notFound();
        $private = (int) $media['is_private'] === 1;
        if ($private) {
            $user = $this->user();
            if (!$user || !$this->auth()->has($user, 'media.manage')) {
                return Response::text('Forbidden', 403);
            }
        }
        [$path, $mime] = $this->svc()->variant(
            $media,
            (int) ($r->attributes['width'] ?? 640),
            $height
        );
        if (!is_file($path)) return Response::notFound();
        return Response::html('')->header('Content-Type', $mime)
            ->header('Content-Length', (string) filesize($path))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', $private ? 'private, no-store' : 'public, max-age=604800, immutable')
            ->setBody((string) file_get_contents($path));
    }
}
