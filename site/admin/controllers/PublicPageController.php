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

    private function publishedNav(): array
    {
        return $this->db()->fetchAll(
            'SELECT slug, title FROM `' . $this->db()->table('pages')
            . "` WHERE status = 'published' ORDER BY title LIMIT 20"
        );
    }

    private function renderNotFound(Request $r): string
    {
        $v = $this->c->get(View::class);
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        return $v->renderInLayout('theme:404', 'theme:layout', ['pages' => $this->publishedNav()]);
    }
}
