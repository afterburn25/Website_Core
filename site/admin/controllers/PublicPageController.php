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

    public function localized(Request $r): Response
    {
        $locale=\Core\I18n\ContentTranslations::locale((string)($r->attributes['locale']??''));
        $slug=trim((string)($r->attributes['slug']??''),'/');
        $service=new \Core\I18n\ContentTranslations($this->db());
        $page=$locale!==''?$service->findPublished($locale,$slug):null;
        if(!$page)return Response::notFound($this->renderNotFound($r));
        return $this->renderRecord($r,$page,false,$locale);
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
        $this->shareGlobals($v, $r, $page, null);
        $page['rendered_blocks'] = (new \Core\Content\PageBlocks(
            $this->db(), $this->c->get(\Core\Modules\ModuleRegistry::class), $this->csrf()
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

        return $this->renderRecord($r,$page,$isHome,null);
    }

    private function renderRecord(Request $r,array $page,bool $isHome,?string $locale): Response
    {
        if($locale!==null)$this->c->get(\Core\I18n\Translator::class)->useLocale($locale);
        $v = $this->c->get(View::class);
        $this->shareGlobals($v, $r, $page, $locale);
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
            $this->db(), $this->c->get(\Core\Modules\ModuleRegistry::class), $this->csrf()
        ))->render($page['blocks_json'] ?? null, $r->basePath);

        $body = $v->renderInLayout('theme:page', 'theme:layout', [
            'page'    => $page,
            'isHome'  => $isHome,
            'pages'   => $this->publishedNav($locale),
        ]);
        // Core-managed published pages contain no session-bound form state,
        // so Application may add an ETag and a short shared-cache policy.
        $response = Response::html($body);
        return str_contains((string) ($page['rendered_blocks'] ?? ''), 'wc-public-form')
            ? $response->header('Cache-Control', 'private, no-store')
            : $response->header('X-WC-Cacheable', 'public');
    }

    /**
     * Theme navigation: the 'primary' menu tree when items exist, else a
     * fallback list of published pages so a fresh install still navigates.
     */
    private function publishedNav(?string $locale=null): array
    {
        if($locale!==null){return array_map(fn(array $p)=>['slug'=>$p['slug'],'title'=>$p['title'],'url'=>'/l/'.$locale.'/'.$p['slug'],'children'=>[]],(new \Core\I18n\ContentTranslations($this->db()))->translatedNav($locale));}
        $menu = new \Core\Content\MenuService($this->db());
        $tree = $menu->tree('primary');
        if ($menu->itemsFor('primary', true) !== []) {
            return array_map(fn (array $i) => [
                'slug' => $i['page_slug'] ?? '', 'page_slug' => $i['page_slug'] ?? '',
                'title' => $i['label'], 'url' => $i['url'],
                'target' => $i['target'] ?? 'self', 'children' => $i['children'],
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
        return \Core\Branding\StatusPage::render(
            404, 'This trail ends here.',
            'The page may have moved, changed its name, or drifted out with the tide.',
            $r->routeBase(), (string) $this->settings()->get('site.name', 'Website')
        );
    }

    private function shareGlobals(View $v, Request $r, ?array $currentPage=null, ?string $locale=null): void
    {
        $v->share('basePath', $r->basePath);
        $v->share('currentPath', rtrim($r->path, '/') ?: '/');
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
        $brand = $this->c->get(\Core\Branding\BrandAssets::class);
        $v->share('brandLogoUrl', $brand->url($r, 'logo', 480));
        $v->share('faviconUrl', $brand->url($r, 'favicon'));
        $v->share('socialImageUrl', $brand->url($r, 'social', 1200));
        $v->share('copyrightNotice', $brand->copyrightNotice((string) $this->settings()->get('site.name', 'Website Core')));
        $v->share('footerNavigation', (new \Core\Content\MenuService($this->db()))->tree('footer'));
        $v->share('headerSocialLinks', $brand->socialLinks('header'));
        $v->share('footerSocialLinks', $brand->socialLinks('footer'));
        [$themeStyles, $themeScripts] = $this->themeAssets($r);
        $v->share('themeStyleUrls', $themeStyles);
        $v->share('themeScriptUrls', $themeScripts);
        $primary = (string) $this->settings()->get('design.primary_color', '#0369a1');
        $accent = (string) $this->settings()->get('design.accent_color', '#0ea5e9');
        $radius = max(0, min(24, (int) $this->settings()->get('design.radius', 8)));
        $v->share('designTokens', [
            'primary' => preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#0369a1',
            'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#0ea5e9',
            'radius' => $radius,
            'header_layout' => (string) $this->settings()->get('design.header_layout', 'classic'),
            'footer_layout' => (string) $this->settings()->get('design.footer_layout', 'centered'),
        ]);
        $translations=new \Core\I18n\ContentTranslations($this->db());
        $v->share('languageLinks',$currentPage?array_map(fn(array $x)=>$x+['url'=>$r->routeUrl('/l/'.$x['locale'].'/'.$x['slug'])],$translations->alternates((int)$currentPage['id'])):[]);
        $v->share('currentLocale',$locale);
    }

    /** @return array{0:list<string>,1:list<string>} */
    private function themeAssets(Request $r): array
    {
        $version = rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development');
        $styles = [$r->routeUrl('/_a/themes/base/assets/css/style.css') . '&v=' . $version];
        $scripts = [];
        $slug = basename((string) $this->settings()->get('theme.active', 'base'));
        if ($slug === '' || $slug === 'base') return [$styles, $scripts];
        $root = defined('WC_THEMES') ? WC_THEMES : dirname(__DIR__, 2) . '/themes';
        $manifestFile = $root . '/' . $slug . '/theme.json';
        $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : null;
        if (!is_array($manifest)) return [$styles, $scripts];
        foreach (['css', 'js'] as $kind) {
            foreach ((array) ($manifest['assets'][$kind] ?? []) as $asset) {
                $asset = ltrim(str_replace('\\', '/', (string) $asset), '/');
                if ($asset === '' || str_contains($asset, '..') || !preg_match('/\.' . $kind . '$/i', $asset)) continue;
                if (!is_file($root . '/' . $slug . '/' . $asset)) continue;
                $url = $r->routeUrl('/_a/themes/' . rawurlencode($slug) . '/' . str_replace('%2F', '/', rawurlencode($asset))) . '&v=' . $version;
                if ($kind === 'css') $styles[] = $url;
                else $scripts[] = $url;
            }
        }
        return [array_values(array_unique($styles)), array_values(array_unique($scripts))];
    }

}
