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
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        $v->share('tagline', $this->settings()->get('site.tagline', ''));
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
            . "` WHERE slug = ? AND status = 'published'",
            [$slug]
        );
        if (!$page) {
            return Response::notFound($this->renderNotFound($r));
        }

        $v = $this->c->get(View::class);
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        $v->share('tagline', $this->settings()->get('site.tagline', ''));

        $body = $v->renderInLayout('theme:page', 'theme:layout', [
            'page'    => $page,
            'isHome'  => $isHome,
            'pages'   => $this->publishedNav(),
        ]);
        return Response::html($body);
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
            . "` WHERE status = 'published' ORDER BY title LIMIT 20"
        ));
    }

    private function renderNotFound(Request $r): string
    {
        $v = $this->c->get(View::class);
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        return $v->renderInLayout('theme:404', 'theme:layout', ['pages' => $this->publishedNav()]);
    }
}
