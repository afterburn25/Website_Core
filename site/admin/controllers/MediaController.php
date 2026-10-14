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
            $action = $r->string('action');
            if ($action === 'upload' && isset($r->files['file'])) {
                [$id, $err] = $media->store(
                    $r->files['file'],
                    $u->id,
                    $r->string('private') === '1',
                    $r->string('folder')
                );
                $this->flash($err ? 'error' : 'ok', $err ?? 'File uploaded.');
            } elseif ($action === 'delete') {
                $id = (int) $r->string('id');
                $usages = $media->usages($id);
                if ($usages !== [] && $r->string('confirm') !== '1') {
                    $this->flash('error', 'File is referenced ' . count($usages) . ' time(s) — resubmit to confirm deletion.');
                    return $this->redirect($r, '/admin/media?confirm=' . $id);
                }
                $media->delete($id);
                $this->flash('ok', 'File deleted.');
            } elseif ($action === 'meta') {
                $this->db()->update('media', [
                    'alt_text' => $r->string('alt_text'),
                    'caption'  => $r->string('caption'),
                ], 'id = ?', [(int) $r->string('id')]);
                $this->flash('ok', 'Metadata saved.');
            }
            return $this->redirect($r, '/admin/media');
        }

        return $this->page($r, 'media/index', [
            'items'   => $media->list($r->string('folder')),
            'folders' => $this->folders(),
            'confirm' => (int) $r->input('confirm', 0),
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
}
