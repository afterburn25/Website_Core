<?php
declare(strict_types=1);

namespace Core\Branding;

/** Dependency-free, presentation-safe social marks shared by all themes. */
final class SocialIcon
{
    public static function svg(string $platform): string
    {
        $body = match ($platform) {
            'facebook' => '<path d="M14 8h3V4.4c-.8-.1-1.8-.2-3-.2-3 0-5 1.8-5 5.2V12H6v4h3v8h4v-8h3.2l.5-4H13V9.8c0-1.2.3-1.8 1-1.8Z"/>',
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2"/>',
            'linkedin' => '<path d="M5 8.5h4V21H5V8.5Zm2-5A2.3 2.3 0 1 1 7 8a2.3 2.3 0 0 1 0-4.5ZM11 8.5h3.8v1.7h.1c.5-1 1.8-2.2 3.7-2.2 4 0 4.7 2.6 4.7 6V21h-4v-6.2c0-1.5 0-3.4-2.1-3.4-2.1 0-2.4 1.6-2.4 3.3V21H11V8.5Z"/>',
            'youtube' => '<path d="M23.5 7.2a3 3 0 0 0-2.1-2.1C19.5 4.6 12 4.6 12 4.6s-7.5 0-9.4.5A3 3 0 0 0 .5 7.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 4.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-4.8ZM9.6 15.2V8.8L15.8 12l-6.2 3.2Z"/>',
            'tiktok' => '<path d="M16.5 3c.4 2.2 1.7 3.6 4 4v4a9 9 0 0 1-4-1.2v6.1A7.1 7.1 0 1 1 10.4 9v4.1a3 3 0 1 0 2.1 2.8V3h4Z"/>',
            'x' => '<path d="M4 4h4.8l4.1 5.5L17.7 4H20l-6 7.2L21 20h-4.8l-4.5-6-5.2 6H4l6.5-7.7L4 4Zm3.5 2 9.7 12h1.4L8.9 6H7.5Z"/>',
            default => '',
        };
        if ($body === '') return '';
        return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">' . $body . '</svg>';
    }
}
