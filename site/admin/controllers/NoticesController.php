<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Admin\NoticeCenter;
use Core\Http\Request;
use Core\Http\Response;

final class NoticesController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u) {
            return $this->forbidden();
        }
        return $this->page($r, 'notices/index', [
            'notices' => $this->c->get(NoticeCenter::class)->openFor($u->id),
        ], 'Notifications');
    }

    public function dismiss(Request $r): Response
    {
        $u = $this->user();
        if (!$u) {
            return $this->forbidden();
        }
        $this->c->get(NoticeCenter::class)->dismiss((int) ($r->attributes['id'] ?? 0), $u->id);
        return $this->redirect($r, '/admin/notices');
    }
}
