<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Branding\BrandAssets;
use Core\Appearance\ModuleThemeCollections;
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
        $branding = [
            'logo_media_id' => (string) $this->settings()->get('brand.logo_media_id', ''),
            'favicon_media_id' => (string) $this->settings()->get('brand.favicon_media_id', ''),
            'social_media_id' => (string) $this->settings()->get('brand.social_media_id', ''),
            'primary_color' => (string) $this->settings()->get('design.primary_color', '#0369a1'),
            'accent_color' => (string) $this->settings()->get('design.accent_color', '#0ea5e9'),
            'radius' => (string) $this->settings()->get('design.radius', '8'),
            'header_layout' => (string) $this->settings()->get('design.header_layout', 'classic'),
            'footer_layout' => (string) $this->settings()->get('design.footer_layout', 'centered'),
            'copyright' => (string) $this->settings()->get('footer.copyright', ''),
        ];
        $submitted = $_SESSION['_wc_appearance_input'] ?? null;
        unset($_SESSION['_wc_appearance_input']);
        if (is_array($submitted)) {
            foreach (array_keys($branding) as $key) {
                if (array_key_exists($key, $submitted)) $branding[$key] = (string) $submitted[$key];
            }
        }
        $moduleThemes = $this->c->get(ModuleThemeCollections::class);
        $themeQuery = mb_strtolower(trim($r->string('theme_q')));
        $collections = $moduleThemes->collections();
        if ($themeQuery !== '') {
            foreach ($collections as $package => &$collection) {
                $collection['variants'] = array_filter(
                    $collection['variants'],
                    static fn (array $variant): bool => str_contains(
                        mb_strtolower($collection['label'] . ' ' . $variant['name'] . ' ' . $variant['description']),
                        $themeQuery,
                    ),
                );
                if ($collection['variants'] === []) unset($collections[$package]);
            }
            unset($collection);
        }
        $currentCollections = [];
        foreach (array_keys($collections) as $package) $currentCollections[$package] = $moduleThemes->current($package);
        return $this->page($r, 'appearance/index', [
            'themes' => $themes,
            'active' => (string) $this->settings()->get('theme.active', 'base'),
            'branding' => $branding,
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
                'url' => (string) ($submitted['social_' . $platform]
                    ?? $this->settings()->get('social.' . $platform . '.url', '')),
            ], $brand->socialDefinitions(), array_keys($brand->socialDefinitions())),
            'socialPlacement' => (string) ($submitted['social_placement'] ?? $brand->socialPlacement()),
            'moduleThemeCollections' => $collections,
            'moduleThemeCurrent' => $currentCollections,
            'themeQuery' => $themeQuery,
        ], 'Appearance');
    }

    public function activate(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'appearance.manage')) {
            return $this->forbidden();
        }
        $slug = basename($r->string('slug'));
        $registry = $this->c->get(ModuleRegistry::class);
        $registry->discover();
        $theme = null;
        foreach ($registry->all() as $info) {
            if (basename((string) ($info['dir'] ?? '')) === $slug && ($info['manifest']['type'] ?? '') === 'theme') {
                $theme = $info; break;
            }
        }
        if ($theme === null) {
            $this->flash('error', 'Theme not found.');
            return $this->redirect($r, '/admin/appearance');
        }
        if (($theme['errors'] ?? []) !== []) {
            $this->flash('error', 'That theme is not compatible: ' . (string) $theme['errors'][0]);
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
            return $this->brandingError($r, 'Brand colors must use six-digit hex values such as #0369a1.');
        }
        $radius = max(0, min(24, (int) $r->string('radius', '8')));
        $socialProfiles = [];
        $socialPlacement = $r->string('social_placement', 'footer');
        if (!in_array($socialPlacement, ['header', 'footer', 'both'], true)) $socialPlacement = 'footer';
        $brand = $this->c->get(BrandAssets::class);
        foreach ($brand->socialDefinitions() as $platform => $label) {
            $url = mb_substr($r->string('social_' . $platform), 0, 500);
            if ($url !== '' && !BrandAssets::validSocialUrl($url)) {
                return $this->brandingError($r, $label . ' must be a complete HTTPS profile URL or left blank.');
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
                return $this->brandingError($r, 'One of the selected brand images is unavailable or private.');
            }
            $ids[$field] = $value > 0 ? $value : null;
        }

        $created = [];
        foreach (['logo_upload' => 'logo_media_id', 'favicon_upload' => 'favicon_media_id', 'social_upload' => 'social_media_id'] as $upload => $field) {
            $file = $r->files[$upload] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if ($error = $this->uploadError($r, $upload)) {
                foreach ($created as $id) $this->c->get(MediaService::class)->delete($id);
                return $this->brandingError($r, ucfirst(str_replace('_', ' ', $upload)) . ': ' . $error);
            }
            [$id, $error] = $this->c->get(MediaService::class)->store($file, $u->id, false, 'Branding');
            $record = $id ? $this->c->get(MediaService::class)->find($id) : null;
            if ($error !== null || $id === null || !$record || !str_starts_with((string) $record['mime'], 'image/')) {
                if ($id) $this->c->get(MediaService::class)->delete($id);
                foreach ($created as $createdId) $this->c->get(MediaService::class)->delete($createdId);
                return $this->brandingError($r, ucfirst(str_replace('_', ' ', $upload)) . ': ' . ($error ?? 'Choose a supported image file.'));
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

    public function moduleTheme(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'appearance.manage')) return $this->forbidden();
        $package = $r->string('package');
        $operation = $r->string('operation');
        $service = $this->c->get(ModuleThemeCollections::class);
        if (!isset($service->collections()[$package])) {
            $this->flash('error', 'Industry theme library is unavailable.');
            return $this->redirect($r, '/admin/appearance');
        }
        [$ok, $error] = match ($operation) {
            'activate' => $service->activate(
                $package,
                $r->string('variant'),
                $r->string('palette'),
                is_array($r->input('media', [])) ? $r->input('media', []) : [],
                $u->id,
            ),
            'draft' => $service->saveDraft(
                $package,
                $r->string('variant'),
                $r->string('palette'),
                is_array($r->input('media', [])) ? $r->input('media', []) : [],
                $u->id,
            ),
            'publish' => $service->publish($package, $u->id),
            'rollback' => $service->rollback($package, $u->id),
            default => [false, 'Unknown module theme operation.'],
        };
        $this->flash($ok ? 'ok' : 'error', $ok
            ? match ($operation) {
                'activate' => 'Theme activated. Its layout, color palette, and selected images are now live.',
                'draft' => 'Module theme draft saved. Preview it before publishing.',
                'publish' => 'Module theme published. Content, navigation, and selected Media were preserved.',
                default => 'Previous module theme restored.',
            }
            : (string) $error);
        return $this->redirect($r, '/admin/appearance');
    }

    private function brandingError(Request $r, string $message): Response
    {
        $_SESSION['_wc_appearance_input'] = array_filter(
            $r->post,
            static fn (string $key): bool => $key !== '_csrf',
            ARRAY_FILTER_USE_KEY
        );
        $this->flash('error', $message . ' Your entered settings are still below.');
        return $this->redirect($r, '/admin/appearance');
    }
}
