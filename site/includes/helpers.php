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
        // Block javascript:/data: style payloads in URL contexts.
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return '#';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
