<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Marketplace\MarketplaceService;
use Core\Update\ManagedUpdateService;
use Core\Http\Request;
use Core\Http\Response;

final class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user(); if (!$user || !$user->isOwner()) return $this->forbidden();
        $market = $this->c->get(MarketplaceService::class); $managed = $this->c->get(ManagedUpdateService::class);
        if ($request->isPost()) {
            try {
                $action = $request->string('action');
                if ($action === 'source_add') { $market->addSource($request->string('label'), $request->string('catalog_url')); $this->flash('ok', 'Signed catalog source added.'); }
                elseif ($action === 'source_refresh') { $catalog = $market->refresh((int) $request->string('id')); $this->flash('ok', 'Catalog verified: ' . count($catalog['packages']) . ' packages and ' . count($catalog['core_updates']) . ' core releases.'); }
                elseif ($action === 'source_disable') { $market->disable((int) $request->string('id')); $this->flash('ok', 'Catalog source disabled.'); }
                elseif ($action === 'policy') { $policy = $request->string('policy'); if (!in_array($policy, ManagedUpdateService::POLICIES, true)) throw new \InvalidArgumentException('Unknown update policy.'); $this->settings()->set('updates.automation', $policy); $this->flash('ok', 'Managed update policy saved.'); }
                elseif ($action === 'check_updates') { $result = $managed->run(true); $this->flash('ok', match ($result['status']) {'staged' => 'Trusted update downloaded, verified and staged.', 'current' => $result['message'], default => 'Update catalog checked.'}); }
            } catch (\Throwable $e) { $this->flash('error', $e->getMessage()); }
            if (isset($action) && $action !== '') $this->audit('marketplace.' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($action)));
            return $this->redirect($request, '/admin/marketplace');
        }
        return $this->page($request, 'marketplace/index', ['sources' => $market->sources(), 'packages' => $market->packages(),
            'release' => $market->latestCore((string) $this->c->get(\Core\Config::class)->get('updates.channel', 'stable')),
            'policy' => (string) $this->settings()->get('updates.automation', 'off'), 'policies' => ManagedUpdateService::POLICIES,
            'history' => $managed->recent()], 'Marketplace & managed updates');
    }
}
