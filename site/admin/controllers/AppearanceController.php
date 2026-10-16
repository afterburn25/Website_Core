<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleRegistry;

/**
 * Theme management: list discovered themes, activate one. Activation only
 * changes the theme.active setting — business data and settings are never
 * touched (themes own presentation, never records).
 */
final class AppearanceController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'appearance.manage')) {
            return $this->forbidden();
        }
        $registry = $this->c->get(ModuleRegistry::class);
        $registry->discover();
        $themes = [];
        foreach ($registry->all() as $id => $info) {
            if (($info['manifest']['type'] ?? '') === 'theme') {
                $themes[$id] = $info;
            }
        }
        return $this->page($r, 'appearance/index', [
            'themes' => $themes,
            'active' => (string) $this->settings()->get('theme.active', 'base'),
        ], 'Appearance');
    }

    public function activate(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'appearance.manage')) {
            return $this->forbidden();
        }
        $slug = basename($r->string('slug'));
        if (!is_dir(WC_THEMES . '/' . $slug) || !is_file(WC_THEMES . '/' . $slug . '/theme.json')) {
            $this->flash('error', 'Theme not found.');
            return $this->redirect($r, '/admin/appearance');
        }
        $this->settings()->set('theme.active', $slug);
        $this->c->get(\Core\Audit\AuditLog::class)->record('theme.activate', $u->id, ['theme' => $slug]);
        $this->flash('ok', 'Theme activated — all content and settings preserved.');
        return $this->redirect($r, '/admin/appearance');
    }
}
