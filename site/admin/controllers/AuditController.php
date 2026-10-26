<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

/** Audit history isolated from the other system tools and safe on schema drift. */
final class AuditController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'audit.view')) {
            return $this->forbidden();
        }

        $search = mb_substr($r->string('q'), 0, 190);
        $eventPrefix = preg_replace('/[^a-z0-9_.-]/i', '', mb_substr($r->string('event'), 0, 80));
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE (event LIKE ? OR context LIKE ? OR ip LIKE ?)';
            $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
        }
        if ($eventPrefix !== '') {
            $where .= $where === '' ? ' WHERE ' : ' AND ';
            $where .= 'event LIKE ?';
            $params[] = $eventPrefix . '%';
        }
        $table = $this->db()->table('audit_log');
        $warning = null;
        try {
            $total = (int) $this->db()->fetchColumn(
                'SELECT COUNT(*) FROM `' . $table . '`' . $where,
                $params
            );
            $pages = max(1, (int) ceil($total / 50));
            $page = max(1, min($pages, (int) $r->input('page', 1)));
            $rows = $this->db()->fetchAll(
                'SELECT * FROM `' . $table . '`' . $where
                    . ' ORDER BY id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50),
                $params
            );
        } catch (\Throwable $e) {
            error_log('[website-core] audit workspace recovery: ' . $e->getMessage());
            $total = 0;
            $pages = 1;
            $page = 1;
            $rows = [];
            $warning = 'Audit history could not be read. No records were changed. '
                . 'Open System Health or download diagnostics for recovery details.';
        }

        return $this->page($r, 'system/audit', [
            'rows' => $rows,
            'search' => $search,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'warning' => $warning,
            'eventPrefix' => $eventPrefix,
        ], 'Audit log');
    }

    public function export(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'audit.view')) return $this->forbidden();
        $rows = $this->db()->fetchAll(
            'SELECT id,event,actor_id,context,ip,created_at FROM `' . $this->db()->table('audit_log') . '` ORDER BY id DESC LIMIT 10000'
        );
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['id','event','actor_id','context','ip','created_at'], ',', '"', '');
        foreach ($rows as $row) fputcsv($stream, array_map([$this, 'csvCell'], array_values($row)), ',', '"', '');
        rewind($stream); $csv = (string) stream_get_contents($stream); fclose($stream);
        $this->audit('audit.exported', ['rows' => count($rows)]);
        return Response::text($csv)->header('Content-Type', 'text/csv; charset=utf-8')->header('Content-Disposition', 'attachment; filename="website-core-audit-' . date('Ymd-His') . '.csv"')->header('Cache-Control', 'private, no-store');
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'" . $value : $value;
    }
}
