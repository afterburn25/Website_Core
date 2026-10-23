<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\View\View;

/**
 * Public site renderer: published pages through the active theme with a
 * base-theme fallback. Drafts are never publicly reachable; preview links
 * will use purpose-bound tokens (M2).
 */
final class PublicPageController extends Controller
{
    public function home(Request $r): Response
    {
        $slug = (string) $this->settings()->get('site.home_slug', 'home');
        return $this->renderPage($r, $slug, isHome: true);
    }

    public function show(Request $r): Response
    {
        $slug = trim((string) ($r->attributes['slug'] ?? ''), '/');
        return $this->renderPage($r, $slug, isHome: false);
    }

    /** Purpose-bound draft preview — noindex, no-store, token-redeemed. */
    public function preview(Request $r): Response
    {
        $tokens = new \Core\Content\PreviewTokens($this->db());
        $pageId = $tokens->peek((string) ($r->attributes['token'] ?? ''));
        if ($pageId === null) {
            return Response::notFound('Invalid or expired preview link.');
        }
        $page = $this->db()->fetch(
            'SELECT * FROM `' . $this->db()->table('pages') . '` WHERE id = ?',
            [$pageId]
        );
        if (!$page) {
            return Response::notFound();
        }
        $v = $this->c->get(View::class);
        $this->shareGlobals($v, $r);
        $page['rendered_blocks'] = (new \Core\Content\PageBlocks(
            $this->db(), $this->c->get(\Core\Modules\ModuleRegistry::class)
        ))->render($page['blocks_json'] ?? null, $r->basePath);
        $body = $v->renderInLayout('theme:page', 'theme:layout', [
            'page' => $page, 'isHome' => false, 'pages' => $this->publishedNav(),
            'isPreview' => true,
        ]);
        return Response::html($body)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }

    private function renderPage(Request $r, string $slug, bool $isHome): Response
    {
        $page = $this->db()->fetch(
            'SELECT * FROM `' . $this->db()->table('pages')
            . "` WHERE slug = ? AND status = 'published' AND deleted_at IS NULL",
            [$slug]
        );
        if (!$page) {
            // A configured redirect beats a 404 — moved pages keep working.
            $redirect = (new \Core\Content\RedirectService($this->db()))->match($r->path);
            if ($redirect) {
                $target = (string) $redirect['target'];
                if (str_starts_with($target, '/')) {
                    $target = $r->basePath . $target;
                }
                return Response::redirect($target, (int) $redirect['status_code']);
            }
            return Response::notFound($this->renderNotFound($r));
        }

        $v = $this->c->get(View::class);
        $this->shareGlobals($v, $r);
        $sections = new \Core\Content\SectionService($this->db());
        $v->share('section', fn (string $key): string => $sections->render($key));

        // Resolve the OG image id to a public media URL when configured.
        if (!empty($page['og_media_id'])) {
            $media = $this->db()->fetch(
                'SELECT id, filename FROM `' . $this->db()->table('media') . '` WHERE id = ? AND is_private = 0',
                [(int) $page['og_media_id']]
            );
            if ($media) {
                $page['og_image_url'] = $r->basePath . '/media/' . (int) $media['id'] . '/' . rawurlencode((string) $media['filename']);
            }
        }
        $page['rendered_blocks'] = (new \Core\Content\PageBlocks(
            $this->db(), $this->c->get(\Core\Modules\ModuleRegistry::class)
        ))->render($page['blocks_json'] ?? null, $r->basePath);

        $body = $v->renderInLayout('theme:page', 'theme:layout', [
            'page'    => $page,
            'isHome'  => $isHome,
            'pages'   => $this->publishedNav(),
        ]);
        // Core-managed published pages contain no session-bound form state,
        // so Application may add an ETag and a short shared-cache policy.
        return Response::html($body)->header('X-WC-Cacheable', 'public');
    }

    /**
     * Theme navigation: the 'primary' menu tree when items exist, else a
     * fallback list of published pages so a fresh install still navigates.
     */
    private function publishedNav(): array
    {
        $menu = new \Core\Content\MenuService($this->db());
        $tree = $menu->tree('primary');
        if ($tree !== []) {
            return array_map(fn (array $i) => [
                'slug' => '', 'title' => $i['label'], 'url' => $i['url'],
                'children' => $i['children'],
            ], $tree);
        }
        return array_map(fn (array $p) => [
            'slug' => $p['slug'], 'title' => $p['title'],
            'url' => '/' . $p['slug'], 'children' => [],
        ], $this->db()->fetchAll(
            'SELECT slug, title FROM `' . $this->db()->table('pages')
            . "` WHERE status = 'published' AND deleted_at IS NULL ORDER BY title LIMIT 20"
        ));
    }

    private function renderNotFound(Request $r): string
    {
        $v = $this->c->get(View::class);
        $this->shareGlobals($v, $r);
        return $v->renderInLayout('theme:404', 'theme:layout', ['pages' => $this->publishedNav()]);
    }

    private function shareGlobals(View $v, Request $r): void
    {
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        $v->share('tagline', $this->settings()->get('site.tagline', ''));
        $siteUrl = rtrim((string) ($this->settings()->get('app.url', '')
            ?: $this->c->get(\Core\Config::class)->get('app.url', '')), '/');
        $v->share('siteUrl', $siteUrl);
        $v->share('currentUrl', $siteUrl . ($r->path === '/' ? '/' : $r->path));
        $v->share('organization', [
            'name' => (string) $this->settings()->get('site.name', 'Website Core'),
            'url' => $siteUrl,
            'telephone' => (string) $this->settings()->get('site.phone', ''),
            'email' => (string) $this->settings()->get('site.contact_email', ''),
            'address' => (string) $this->settings()->get('site.address', ''),
        ]);
        $v->share('brandLogoUrl', $this->brandMediaUrl($r, 'brand.logo_media_id', 480));
        $v->share('faviconUrl', $this->brandMediaUrl($r, 'brand.favicon_media_id'));
        $v->share('socialImageUrl', $this->brandMediaUrl($r, 'brand.social_media_id', 1200));
        $primary = (string) $this->settings()->get('design.primary_color', '#0369a1');
        $accent = (string) $this->settings()->get('design.accent_color', '#0ea5e9');
        $radius = max(0, min(24, (int) $this->settings()->get('design.radius', 8)));
        $v->share('designTokens', [
            'primary' => preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#0369a1',
            'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#0ea5e9',
            'radius' => $radius,
        ]);
    }

    private function brandMediaUrl(Request $r, string $setting, ?int $width = null): ?string
    {
        $id = (int) $this->settings()->get($setting, 0);
        if ($id < 1) return null;
        $media = $this->db()->fetch(
            'SELECT id, original_name FROM `' . $this->db()->table('media') . '` WHERE id = ? AND is_private = 0', [$id]
        );
        if (!$media) return null;
        return $r->basePath . '/media/' . $id
            . ($width ? '/w/' . $width : '') . '/' . rawurlencode((string) $media['original_name']);
    }
}
