<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Agency\AgencyService;
use Core\Http\Request;
use Core\Http\Response;

final class AgencyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user(); if (!$user || !$user->isOwner()) return $this->forbidden();
        $agency = $this->c->get(AgencyService::class);
        if ($request->isPost()) {
            try {
                $action = $request->string('action');
                if ($action === 'add') { $agency->add($request->string('label'), $request->string('base_url'), $request->string('api_token')); $this->flash('ok', 'Site added to the agency dashboard.'); }
                elseif ($action === 'check') { $result = $agency->check((int) $request->string('id')); $this->flash($result['ok'] ? 'ok' : 'error', $result['ok'] ? 'Remote site is healthy.' : (string) $result['error']); }
                elseif ($action === 'remove') { $agency->remove((int) $request->string('id')); $this->flash('ok', 'Site removed from this dashboard.'); }
            } catch (\Throwable $e) { $this->flash('error', $e->getMessage()); }
            return $this->redirect($request, '/admin/agency');
        }
        return $this->page($request, 'agency/index', ['sites' => $agency->all()], 'Agency dashboard');
    }
}
