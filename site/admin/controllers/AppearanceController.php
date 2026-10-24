<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Branding\BrandAssets;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media\MediaService;
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
        $brand = $this->c->get(BrandAssets::class);
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
                'header_layout' => (string) $this->settings()->get('design.header_layout', 'classic'),
                'footer_layout' => (string) $this->settings()->get('design.footer_layout', 'centered'),
                'copyright' => (string) $this->settings()->get('footer.copyright', ''),
            ],
            'mediaItems' => $this->db()->fetchAll(
                'SELECT id, original_name FROM `' . $this->db()->table('media')
                . "` WHERE is_private = 0 AND mime LIKE 'image/%' ORDER BY id DESC LIMIT 200"
            ),
            'brandPreviews' => [
                'logo' => $brand->url($r, 'logo', 480),
                'favicon' => $brand->url($r, 'favicon'),
                'social' => $brand->url($r, 'social', 1200),
            ],
            'socialProfiles' => array_map(fn (string $label, string $platform): array => [
                'platform' => $platform,
                'label' => $label,
                'url' => (string) $this->settings()->get('social.' . $platform . '.url', ''),
            ], $brand->socialDefinitions(), array_keys($brand->socialDefinitions())),
            'socialPlacement' => $brand->socialPlacement(),
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
        $socialProfiles = [];
        $socialPlacement = $r->string('social_placement', 'footer');
        if (!in_array($socialPlacement, ['header', 'footer', 'both'], true)) $socialPlacement = 'footer';
        $brand = $this->c->get(BrandAssets::class);
        foreach ($brand->socialDefinitions() as $platform => $label) {
            $url = mb_substr($r->string('social_' . $platform), 0, 500);
            if ($url !== '' && !BrandAssets::validSocialUrl($url)) {
                $this->flash('error', $label . ' must be a complete HTTPS profile URL or left blank.');
                return $this->redirect($r, '/admin/appearance');
            }
            $socialProfiles[$platform] = $url;
        }
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

        $created = [];
        foreach (['logo_upload' => 'logo_media_id', 'favicon_upload' => 'favicon_media_id'] as $upload => $field) {
            $file = $r->files[$upload] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if ($error = $this->uploadError($r, $upload)) {
                foreach ($created as $id) $this->c->get(MediaService::class)->delete($id);
                $this->flash('error', ucfirst(str_replace('_', ' ', $upload)) . ': ' . $error);
                return $this->redirect($r, '/admin/appearance');
            }
            [$id, $error] = $this->c->get(MediaService::class)->store($file, $u->id, false, 'Branding');
            $record = $id ? $this->c->get(MediaService::class)->find($id) : null;
            if ($error !== null || $id === null || !$record || !str_starts_with((string) $record['mime'], 'image/')) {
                if ($id) $this->c->get(MediaService::class)->delete($id);
                foreach ($created as $createdId) $this->c->get(MediaService::class)->delete($createdId);
                $this->flash('error', ucfirst(str_replace('_', ' ', $upload)) . ': ' . ($error ?? 'Choose a supported image file.'));
                return $this->redirect($r, '/admin/appearance');
            }
            $created[] = $id;
            $ids[$field] = $id;
        }
        foreach ($ids as $field => $value) $this->settings()->set('brand.' . $field, $value);
        $this->settings()->set('design.primary_color', $primary);
        $this->settings()->set('design.accent_color', $accent);
        $this->settings()->set('design.radius', $radius);
        $header = in_array($r->string('header_layout'), ['classic', 'centered', 'compact'], true) ? $r->string('header_layout') : 'classic';
        $footer = in_array($r->string('footer_layout'), ['centered', 'split', 'minimal'], true) ? $r->string('footer_layout') : 'centered';
        $this->settings()->set('design.header_layout', $header);
        $this->settings()->set('design.footer_layout', $footer);
        $copyright = strip_tags($r->string('copyright'));
        $copyright = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $copyright));
        if (mb_strlen($copyright) > 240) {
            $copyright = mb_substr($copyright, 0, 240);
        }
        $this->settings()->set('footer.copyright', $copyright);
        foreach ($socialProfiles as $platform => $url) {
            $this->settings()->set('social.' . $platform . '.url', $url);
        }
        $this->settings()->set('social.placement', $socialPlacement);

        $this->db()->run('DELETE FROM `' . $this->db()->table('media_usage') . '` WHERE context = ?', ['core.branding']);
        foreach (array_filter($ids) as $id) {
            $this->db()->insert('media_usage', [
                'media_id' => $id, 'context' => 'core.branding', 'context_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->c->get(\Core\Audit\AuditLog::class)->record('appearance.branding', $u->id, ['media' => array_values(array_filter($ids))]);
        $this->flash('ok', 'Brand assets, social profiles, footer identity, and design tokens saved.');
        return $this->redirect($r, '/admin/appearance');
    }
}
