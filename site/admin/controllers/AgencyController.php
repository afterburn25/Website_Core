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
            $action = $request->string('action');
            $siteId = (int) $request->string('id', '0');
            try {
                if ($action === 'add') {
                    $siteId = $agency->add($request->string('label'), $request->string('base_url'), $request->string('api_token'));
                    $this->flash('ok', 'Site connection saved. Use Check now to verify the remote key.');
                } elseif ($action === 'save') {
                    $agency->update($siteId, $request->string('label'), $request->string('base_url'), $request->string('api_token'));
                    $this->flash('ok', $request->string('api_token') === '' ? 'Connection details saved.' : 'Connection details and encrypted API key updated.');
                } elseif ($action === 'check') {
                    $result = $agency->check($siteId);
                    $this->flash($result['ok'] ? 'ok' : 'error', $result['ok'] ? 'Remote site is healthy and its version inventory is current.' : (string) $result['error']);
                } elseif ($action === 'toggle') {
                    $enable = $request->string('enable') === '1';
                    $agency->setActive($siteId, $enable);
                    $this->flash('ok', $enable ? 'Connection enabled. Run a health check when ready.' : 'Connection paused. Its encrypted key and last known inventory were retained.');
                } elseif ($action === 'remove') {
                    $agency->remove($siteId);
                    $this->flash('ok', 'Site and its encrypted credential were removed from this dashboard.');
                } else {
                    throw new \InvalidArgumentException('That agency action is not available.');
                }
                $this->audit('agency.' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($action)), ['site' => $siteId]);
            } catch (\Throwable $e) {
                $this->flash('error', $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException
                    ? $e->getMessage() : 'The agency action could not be completed. Check the application log for details.');
            }
            return $this->redirect($request, '/admin/agency');
        }
        return $this->page($request, 'agency/index', ['sites' => $agency->all(), 'localCoreVersion' => WC_VERSION], 'Agency dashboard');
    }
}
