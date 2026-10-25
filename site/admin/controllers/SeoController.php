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
        $redirects = new RedirectService($this->db());
        if ($r->isPost()) {
            $action = $r->string('action');
            if ($action === 'redirect') {
                $source = $r->string('source');
                [$ok, $error] = $redirects->save(
                    null, $source, $r->string('target'), 301, $u->id
                );
                if ($ok) {
                    $log->forget($source);
                    $this->audit('seo.redirect_created', ['source' => $source, 'target' => $r->string('target')]);
                }
                $this->flash($ok ? 'ok' : 'error', $error ?? 'Permanent redirect created.');
            } elseif ($action === 'redirect_save') {
                $id = (int) $r->string('id');
                [$ok, $error] = $redirects->save(
                    $id > 0 ? $id : null, $r->string('source'), $r->string('target'),
                    (int) $r->string('status_code', '301'), $u->id
                );
                if ($ok) $this->audit($id > 0 ? 'seo.redirect_updated' : 'seo.redirect_created', [
                    'redirect' => $id ?: null, 'source' => $r->string('source'), 'target' => $r->string('target'),
                ]);
                $this->flash($ok ? 'ok' : 'error', $error ?? ($id > 0 ? 'Redirect updated.' : 'Redirect created.'));
            } elseif ($action === 'redirect_delete') {
                $id = (int) $r->string('id');
                $deleted = $redirects->delete($id);
                if ($deleted) $this->audit('seo.redirect_deleted', ['redirect' => $id]);
                $this->flash($deleted ? 'ok' : 'error', $deleted ? 'Redirect removed.' : 'That redirect was already removed.');
            } elseif ($action === 'dismiss_missing') {
                $source = $r->string('source');
                $count = $log->forget($source);
                $this->audit('seo.not_found_path_dismissed', ['source' => $source]);
                $this->flash('ok', $count > 0 ? 'Missing path dismissed.' : 'That path was already removed.');
            } elseif ($action === 'clear') {
                $count = $log->clear();
                $this->audit('seo.not_found_log_cleared', ['count' => $count]);
                $this->flash('ok', "Cleared {$count} logged path(s).");
            } elseif ($action === 'import' && isset($r->files['csv'])) {
                $result=$this->importRedirects($r->files['csv'],$u->id);
                $this->audit('seo.redirects_imported', $result);
                $detail = $result['first_error'] ? ' First issue: ' . $result['first_error'] : '';
                $this->flash($result['errors'] ? 'error' : 'ok', "Imported {$result['saved']} redirect(s); {$result['errors']} row(s) skipped.{$detail}");
            } else {
                $this->flash('error', 'That SEO action is not available.');
            }
            return $this->redirect($r, '/admin/seo');
        }
        return $this->page($r, 'seo/index', [
            'missing' => $log->top(),
            'pages' => $this->db()->fetchAll(
                'SELECT slug, title FROM `' . $this->db()->table('pages')
                . "` WHERE deleted_at IS NULL AND status = 'published' ORDER BY title"
            ),
            'redirects' => $redirects->all(),
            'audits' => array_map(function(array $page):array{$page['issues']=(new \Core\Seo\AccessibilityAuditor())->audit($page);return $page;},$this->db()->fetchAll('SELECT * FROM `'.$this->db()->table('pages')."` WHERE deleted_at IS NULL AND status='published' ORDER BY title")),
        ], 'SEO');
    }

    public function export(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'navigation.manage')) return $this->forbidden();
        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, ['source', 'target', 'status_code']);
        foreach ((new RedirectService($this->db()))->all() as $row) fputcsv($fp, [$row['source'], $row['target'], $row['status_code']]);
        rewind($fp); $csv = (string) stream_get_contents($fp); fclose($fp);
        return Response::html('')->header('Content-Type','text/csv; charset=utf-8')->header('Content-Disposition','attachment; filename="redirects.csv"')->setBody($csv);
    }

    /** @return array{saved:int,errors:int,first_error:?string} */
    private function importRedirects(array $file, int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['saved' => 0, 'errors' => 1, 'first_error' => 'The CSV upload did not complete.'];
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) return ['saved' => 0, 'errors' => 1, 'first_error' => 'The CSV is larger than 2 MB.'];
        $fp = fopen((string) $file['tmp_name'], 'rb');
        if (!$fp) return ['saved' => 0, 'errors' => 1, 'first_error' => 'The uploaded CSV could not be read.'];
        $saved = 0; $errors = 0; $line = 0; $firstError = null; $service = new RedirectService($this->db());
        while (($row = fgetcsv($fp, 2000)) !== false) {
            $line++;
            if ($line === 1 && strtolower(trim((string) ($row[0] ?? ''))) === 'source') continue;
            if ($saved + $errors >= 2000) { $errors++; $firstError ??= 'The import is limited to 2,000 data rows.'; break; }
            if (count($row) < 2) { $errors++; $firstError ??= "Row {$line} needs a source and target."; continue; }
            [$ok, $error] = $service->save(null, (string) ($row[0] ?? ''), (string) ($row[1] ?? ''), (int) ($row[2] ?? 301), $userId);
            if ($ok) $saved++;
            else { $errors++; $firstError ??= "Row {$line}: " . ($error ?? 'invalid redirect'); }
        }
        fclose($fp);
        return ['saved' => $saved, 'errors' => $errors, 'first_error' => $firstError];
    }
}
