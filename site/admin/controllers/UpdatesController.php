<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Core update workspace isolated from the broader System controller.
 *
 * GET never opens or parses a staged ZIP. Shared hosts can therefore render
 * the page even when an abandoned upload is corrupt, huge, or unreadable.
 * Archive inspection happens only during the explicit stage/apply POST.
 */
final class UpdatesController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.update')) {
            return $this->forbidden();
        }

        $staged = null;
        $loadError = null;
        if (is_file($this->stagedPath())) {
            $staged = $this->readReview();
            if ($staged === null) {
                $loadError = 'An unreviewed staged update file is present. Upload the update again to replace and inspect it safely.';
            }
        }

        return $this->page($r, 'system/updates', [
            'current' => defined('WC_VERSION') ? WC_VERSION : '?',
            'staged' => $staged,
            'journal' => $this->readJournalTail(),
            'loadError' => $loadError,
        ], 'Core updates');
    }

    public function stage(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.update')) {
            return $this->forbidden();
        }
        if (($err = $this->uploadError($r, 'update')) !== null) {
            $this->flash('error', $err);
            return Response::redirect($r->routeUrl('/admin/updates'));
        }

        $file = $r->files['update'];
        $staged = $this->stagedPath();
        @mkdir(dirname($staged), 0750, true);
        @unlink($this->reviewPath());
        if (!move_uploaded_file((string) $file['tmp_name'], $staged)
            && !rename((string) $file['tmp_name'], $staged)) {
            $this->flash('error', 'Could not stage the upload.');
            return Response::redirect($r->routeUrl('/admin/updates'));
        }

        try {
            [$info, $err] = $this->updater()->inspect($staged);
        } catch (\Throwable $e) {
            $info = null;
            $err = 'The uploaded archive could not be inspected: ' . $e->getMessage();
        }
        if ($err !== null || !is_array($info)) {
            @unlink($staged);
            $this->flash('error', $err ?? 'The uploaded update is invalid.');
        } else {
            $review = [
                'version' => (string) $info['version'],
                'members' => (int) $info['members'],
                'signed' => (bool) $info['signed'],
            ];
            @file_put_contents($this->reviewPath(), json_encode($review, JSON_PRETTY_PRINT), LOCK_EX);
            $this->audit('core.update_staged', ['file' => (string) ($file['name'] ?? '')]);
            $this->flash('ok', 'Update staged — review and apply.');
        }
        return Response::redirect($r->routeUrl('/admin/updates'));
    }

    public function apply(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.update')) {
            return $this->forbidden();
        }
        $staged = $this->stagedPath();
        if (!is_file($staged) || $this->readReview() === null) {
            $this->flash('error', 'No reviewed update is staged — upload it again first.');
            return Response::redirect($r->routeUrl('/admin/updates'));
        }

        $config = $this->c->get(\Core\Config::class);
        $storage = (string) $config->get('paths.storage', WC_ROOT . '/storage');
        if ((bool) $config->get('updates.require_window', false)
            && !is_file($storage . '/maintenance.flag')) {
            $this->flash('error', 'Maintenance window required — create ' . $storage . '/maintenance.flag first.');
            return Response::redirect($r->routeUrl('/admin/updates'));
        }

        [$ok, $err, $backup] = $this->updater()->apply($staged);
        if (!$ok) {
            $this->flash('error', 'Update failed: ' . $err
                . ($backup ? ' Replaced files were backed up to ' . basename($backup) : ''));
            return Response::redirect($r->routeUrl('/admin/updates'));
        }

        @unlink($staged);
        @unlink($this->reviewPath());
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        (new \Core\Database\Migrator($this->db(), WC_ROOT . '/database/migrations'))->migrate();
        $this->c->get(\Core\Audit\AuditLog::class)->record('core.update', $u->id, [
            'backup' => $backup ? basename($backup) : null,
        ]);
        $this->c->get(\Core\Events\Dispatcher::class)->dispatch('core.update', [
            'by' => $u->id, 'backup' => $backup ? basename($backup) : null,
        ]);
        $this->flash('ok', 'Update applied; migrations run; replaced files backed up.');
        return Response::redirect($r->routeUrl('/admin/updates'));
    }

    private function updater(): \Core\Update\CoreUpdater
    {
        $config = $this->c->get(\Core\Config::class);
        return new \Core\Update\CoreUpdater(
            WC_ROOT,
            (string) $config->get('paths.storage', WC_ROOT . '/storage'),
            new \Core\Modules\ArchiveInspector(),
            new \Core\Update\SignatureVerifier(
                (array) $config->get('trust.public_keys', []),
                (bool) $config->get('trust.allow_unsigned', false)
            ),
        );
    }

    private function stagedPath(): string
    {
        return $this->storagePath() . '/staged-update.zip';
    }

    private function reviewPath(): string
    {
        return $this->storagePath() . '/staged-update-review.json';
    }

    private function storagePath(): string
    {
        return rtrim((string) $this->c->get(\Core\Config::class)
            ->get('paths.storage', WC_ROOT . '/storage'), '/\\');
    }

    private function readReview(): ?array
    {
        $path = $this->reviewPath();
        if (!is_file($path) || !is_readable($path) || (int) @filesize($path) > 16384) {
            return null;
        }
        $review = json_decode((string) @file_get_contents($path), true);
        if (!is_array($review) || !is_string($review['version'] ?? null)
            || !is_int($review['members'] ?? null) || !is_bool($review['signed'] ?? null)) {
            return null;
        }
        return $review;
    }

    /** @return list<string> */
    private function readJournalTail(int $lines = 25): array
    {
        $path = $this->storagePath() . '/core-update-journal.log';
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }
        $size = (int) @filesize($path);
        if ($size > 65536) {
            @fseek($handle, -65536, SEEK_END);
        }
        $contents = stream_get_contents($handle);
        fclose($handle);
        if (!is_string($contents) || $contents === '') {
            return [];
        }
        return array_values(array_slice(array_filter(explode("\n", trim($contents))), -$lines));
    }
}
