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
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE event LIKE ? OR detail LIKE ?';
            $params = ['%' . $search . '%', '%' . $search . '%'];
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
        ], 'Audit log');
    }
}
