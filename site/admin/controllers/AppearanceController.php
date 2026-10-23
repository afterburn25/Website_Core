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
            'branding' => [
                'logo_media_id' => (string) $this->settings()->get('brand.logo_media_id', ''),
                'favicon_media_id' => (string) $this->settings()->get('brand.favicon_media_id', ''),
                'social_media_id' => (string) $this->settings()->get('brand.social_media_id', ''),
                'primary_color' => (string) $this->settings()->get('design.primary_color', '#0369a1'),
                'accent_color' => (string) $this->settings()->get('design.accent_color', '#0ea5e9'),
                'radius' => (string) $this->settings()->get('design.radius', '8'),
            ],
            'mediaItems' => $this->db()->fetchAll(
                'SELECT id, original_name FROM `' . $this->db()->table('media')
                . "` WHERE is_private = 0 AND mime LIKE 'image/%' ORDER BY id DESC LIMIT 200"
            ),
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

    public function saveBranding(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'appearance.manage')) return $this->forbidden();
        $primary = strtolower($r->string('primary_color'));
        $accent = strtolower($r->string('accent_color'));
        if (!preg_match('/^#[0-9a-f]{6}$/', $primary) || !preg_match('/^#[0-9a-f]{6}$/', $accent)) {
            $this->flash('error', 'Brand colors must use six-digit hex values such as #0369a1.');
            return $this->redirect($r, '/admin/appearance');
        }
        $radius = max(0, min(24, (int) $r->string('radius', '8')));
        $ids = [];
        foreach (['logo_media_id', 'favicon_media_id', 'social_media_id'] as $field) {
            $value = (int) $r->string($field, '0');
            if ($value > 0 && !$this->db()->fetchColumn(
                'SELECT COUNT(*) FROM `' . $this->db()->table('media') . "` WHERE id = ? AND is_private = 0 AND mime LIKE 'image/%'",
                [$value]
            )) {
                $this->flash('error', 'One of the selected brand images is unavailable or private.');
                return $this->redirect($r, '/admin/appearance');
            }
            $ids[$field] = $value > 0 ? $value : null;
        }
        foreach ($ids as $field => $value) $this->settings()->set('brand.' . $field, $value);
        $this->settings()->set('design.primary_color', $primary);
        $this->settings()->set('design.accent_color', $accent);
        $this->settings()->set('design.radius', $radius);

        $this->db()->run('DELETE FROM `' . $this->db()->table('media_usage') . '` WHERE context = ?', ['core.branding']);
        foreach (array_filter($ids) as $id) {
            $this->db()->insert('media_usage', [
                'media_id' => $id, 'context' => 'core.branding', 'context_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->c->get(\Core\Audit\AuditLog::class)->record('appearance.branding', $u->id, ['media' => array_values(array_filter($ids))]);
        $this->flash('ok', 'Brand assets and design tokens saved.');
        return $this->redirect($r, '/admin/appearance');
    }
}
