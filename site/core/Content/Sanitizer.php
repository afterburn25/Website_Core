<?php
declare(strict_types=1);

namespace Core\Content;

/**
 * Rich-text sanitization boundary. Page/section bodies are sanitized at the
 * input boundary (save time) so renders and previews show exactly what will
 * ship. Uses HTMLPurifier — a maintained library — rather than a hand-rolled
 * regex filter. Editors and admins are trusted-ish, never trusted.
 */
final class Sanitizer
{
    private static ?\HTMLPurifier $purifier = null;

    public static function instance(): \HTMLPurifier
    {
        if (self::$purifier === null) {
            if (!class_exists(\HTMLPurifier::class)) {
                throw new \RuntimeException('HTMLPurifier dependency missing (vendor/autoload).');
            }
            $config = \HTMLPurifier_Config::createDefault();
            // Conservative baseline: common rich text, no scripts/iframes/
            // forms/objects/styles. Links keep http(s)/mailto only; target is
            // dropped. Images http(s) + site-relative paths.
            $config->set('HTML.Allowed', implode(',', [
                'p', 'br', 'hr', 'a[href|title]', 'strong', 'em', 'b', 'i', 'u', 's',
                'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
                'h2', 'h3', 'h4', 'h5', 'h6',
                'img[src|alt|title|width|height|class]',
                'figure', 'figcaption',
                'table', 'thead', 'tbody', 'tr', 'th', 'td', 'caption',
                'div[class]', 'span[class]',
            ]));
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('URI.DisableExternalResources', true);
            $config->set('HTML.TargetBlank', true);
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('Cache.SerializerPath', self::cacheDir());
            self::$purifier = new \HTMLPurifier($config);
        }
        return self::$purifier;
    }

    /** Sanitize an HTML fragment for storage. */
    public function html(string $html): string
    {
        return self::instance()->purify($html);
    }

    /** Strip ALL markup — for metadata, titles, plain-text fields. */
    public function text(string $input): string
    {
        return trim(html_entity_decode(strip_tags($input), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function cacheDir(): ?string
    {
        $dir = defined('WC_ROOT') ? WC_ROOT . '/storage/cache/htmlpurifier' : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return is_writable($dir) ? $dir : null;
    }
}
