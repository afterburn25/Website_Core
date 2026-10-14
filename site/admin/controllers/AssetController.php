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

        return Response::html('')
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents($file));
    }
}
