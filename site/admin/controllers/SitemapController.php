<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

/**
 * /sitemap.xml and /robots.txt. Only published pages appear; previews and
 * drafts never leak. robots content is editable via settings.
 */
final class SitemapController extends Controller
{
    public function sitemap(Request $r): Response
    {
        $pages = $this->db()->fetchAll(
            'SELECT slug, updated_at FROM `' . $this->db()->table('pages')
            . "` WHERE status = 'published' ORDER BY slug"
        );
        $base = rtrim((string) $this->c->get(\Core\Config::class)->get('app.url'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as $p) {
            $loc = $base . '/' . ($p['slug'] === 'home' ? '' : $p['slug']);
            $xml .= '  <url><loc>' . e($loc) . '</loc>'
                . ($p['updated_at'] ? '<lastmod>' . e(substr((string) $p['updated_at'], 0, 10)) . '</lastmod>' : '')
                . "</url>\n";
        }
        $xml .= '</urlset>';
        return (new Response($xml))->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(Request $r): Response
    {
        $custom = (string) $this->settings()->get('seo.robots_txt', '');
        $base = rtrim((string) $this->c->get(\Core\Config::class)->get('app.url'), '/');
        $body = $custom !== '' ? $custom : "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /preview\n";
        $body .= "\nSitemap: {$base}/sitemap.xml\n";
        return Response::text($body);
    }
}
