<?php
declare(strict_types=1);

namespace Core\Branding;

/**
 * Required Core attribution. It is deliberately code-owned rather than a
 * setting or theme hook so packages and themes cannot alter or remove it.
 */
final class Attribution
{
    private const MARKER = 'data-cajunwebpro-attribution';

    public function apply(string $html): string
    {
        if ($html === '' || str_contains($html, self::MARKER) || !stripos($html, '</body>')) {
            return $html;
        }
        $footer = '<footer ' . self::MARKER . '="required" style="padding:1.1rem 1rem;text-align:center;'
            . 'font:500 13px/1.55 system-ui,-apple-system,sans-serif;color:#64748b;background:#f8fafc;'
            . 'border-top:1px solid #e2e8f0">'
            . '<div>Website Design &amp; Development by '
            . '<a href="https://cajunwebpro.com" rel="noopener" style="color:#0369a1">Cajun Web Pro LLC</a></div>'
            . '<div>Want a website like this for your business? '
            . '<a href="https://cajunwebpro.com" rel="noopener" style="color:#0369a1;font-weight:700">Let’s build yours.</a></div>'
            . '</footer>';
        return (string) preg_replace('/<\/body>/i', $footer . '</body>', $html, 1);
    }
}
