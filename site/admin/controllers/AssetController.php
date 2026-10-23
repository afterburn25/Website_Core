<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Namespaced public asset resolver: /_a/themes/<theme>/<path> and
 * /_a/modules/<package-id>/<path>. Only allowlisted extensions are served,
 * paths are realpath-constrained to their package root, and no directory
 * listing is possible.
 */
final class AssetController extends Controller
{
    private const MIME = [
        'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'txt' => 'text/plain', 'json' => 'application/json',
        // SVG deliberately excluded until a reviewed sanitizer ships (M2).
    ];

    public function serve(Request $r): Response
    {
        $path = (string) ($r->attributes['path'] ?? '');
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return Response::notFound();
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? null;
        if ($mime === null) {
            return Response::notFound();
        }

        if (preg_match('#^themes/([^/]+)/(.+)$#', $path, $m)) {
            $base = realpath(WC_THEMES . '/' . $m[1]);
            $file = $base ? realpath($base . '/' . $m[2]) : false;
        } elseif (preg_match('#^modules/([^/]+)/(.+)$#', $path, $m)) {
            $packageDir = realpath(WC_MODULES . '/addons/' . $m[1]) ?: realpath(WC_MODULES . '/industries/' . $m[1]);
            $base = $packageDir ?: false;
            $file = $base ? realpath($base . '/' . $m[2]) : false;
        } else {
            return Response::notFound();
        }

        if (!$file || !$base || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return Response::notFound();
        }

        $body = (string) file_get_contents($file);
        if ($ext === 'css') {
            $body = self::rewriteCssUrls($body, $path);
        }

        return Response::html('')
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($body);
    }

    /**
     * A stylesheet served through index.php has no virtual directory from
     * which a browser can resolve ../images or ../fonts. Rebase relative CSS
     * resources onto the same rewrite-free asset route. Query-only URLs keep
     * the current physical index.php and therefore work in any subfolder.
     */
    private static function rewriteCssUrls(string $css, string $assetPath): string
    {
        $directory = str_replace('\\', '/', dirname($assetPath));

        return (string) preg_replace_callback(
            '#url\(\s*([\'\"]?)([^\'\")]+)\1\s*\)#i',
            static function (array $match) use ($directory): string {
                $reference = trim($match[2]);
                if ($reference === ''
                    || preg_match('#^(?:[a-z][a-z0-9+.-]*:|/|\\\\|\#|\?)#i', $reference)
                ) {
                    return $match[0];
                }

                $fragment = '';
                if (($hash = strpos($reference, '#')) !== false) {
                    $fragment = substr($reference, $hash);
                    $reference = substr($reference, 0, $hash);
                }
                $query = '';
                if (($question = strpos($reference, '?')) !== false) {
                    $query = substr($reference, $question + 1);
                    $reference = substr($reference, 0, $question);
                }

                $segments = [];
                foreach (explode('/', $directory . '/' . $reference) as $segment) {
                    if ($segment === '' || $segment === '.') {
                        continue;
                    }
                    if ($segment === '..') {
                        if ($segments === []) {
                            return $match[0];
                        }
                        array_pop($segments);
                        continue;
                    }
                    $segments[] = $segment;
                }
                if ($segments === [] || !in_array($segments[0], ['modules', 'themes'], true)) {
                    return $match[0];
                }

                $encoded = implode('/', array_map('rawurlencode', $segments));
                $url = '?p=/_a/' . $encoded;
                $url .= '&v=' . rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development');
                $url .= $query !== '' ? '&' . $query : '';
                return 'url("' . $url . $fragment . '")';
            },
            $css
        );
    }
}
