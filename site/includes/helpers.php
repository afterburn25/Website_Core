<?php
declare(strict_types=1);

/**
 * Template helpers available inside views.
 */

if (!function_exists('e')) {
    /** Escape for HTML context — the only approved raw interpolation path. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    function e_attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('e_url')) {
    function e_url(string $value): string
    {
        // Block javascript:/data: style payloads in URL contexts. Browsers
        // strip tabs/newlines inside schemes and leading whitespace before
        // parsing ("java\tscript:", " javascript:") — probe a sanitized
        // copy so obfuscated payloads cannot reach the page.
        $probe = (string) preg_replace('/[\x00-\x20]/', '', $value);
        $scheme = strtolower((string) parse_url($probe, PHP_URL_SCHEME));
        if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return '#';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    /** Translate a catalog key through the shared Translator. */
    function __(string $key, string|int|float ...$args): string
    {
        return \Core\I18n\Translator::instance()->t($key, ...$args);
    }
}
