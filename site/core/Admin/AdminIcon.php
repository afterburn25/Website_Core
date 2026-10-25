<?php
declare(strict_types=1);

namespace Core\Admin;

/** Small dependency-free icon set used by the Core administration shell. */
final class AdminIcon
{
    public static function svg(string $name): string
    {
        $body = match ($name) {
            'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="4" rx="1"/><rect x="14" y="11" width="7" height="10" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/>',
            'analytics' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/><path d="m3 7 6-4 6 7 6-5"/>',
            'visitors' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-7 7-7s7 3 7 7M17 8h5M19.5 5.5v5"/>',
            'geography' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 4 6 4 9s-1 6-4 9c-3-3-4-6-4-9s1-6 4-9Z"/>',
            'sites' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 20V9"/>',
            'content' => '<path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
            'patterns' => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>',
            'translations' => '<path d="M4 5h9M8.5 3v2M6 5c.5 4 3 7 7 9M11 5c-.7 4-3 7-7 9"/><path d="m14 21 3.5-9 3.5 9M15.2 18h4.6"/>',
            'forms' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4"/>',
            'navigation' => '<circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="12" cy="18" r="2"/><path d="M7 6h10M6.5 7.5l4.2 8.7M17.5 7.5l-4.2 8.7"/>',
            'seo' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 5 5M8 10l1.5 1.5L13 8"/>',
            'media' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
            'appearance' => '<path d="M12 3a9 9 0 1 0 0 18h1.5a2 2 0 0 0 0-4H12a2 2 0 0 1 0-4h3a6 6 0 0 0 0-12h-3Z"/><circle cx="7.5" cy="10" r="1"/><circle cx="9" cy="6.5" r="1"/><circle cx="14" cy="6" r="1"/>',
            'privacy' => '<path d="M12 3 4.5 6v5c0 5 3 8.5 7.5 10 4.5-1.5 7.5-5 7.5-10V6z"/><path d="M9 12l2 2 4-5"/>',
            'integrations' => '<path d="M8 12H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h4M16 12h4a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-4M7 8h10M12 8v8"/><rect x="8" y="16" width="8" height="5" rx="1"/>',
            'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-7 7-7s7 3 7 7M17 8h5M19.5 5.5v5"/>',
            'roles' => '<circle cx="8" cy="8" r="3"/><path d="M2 19c0-3.5 2.5-6 6-6 2 0 3.5.7 4.5 2"/><path d="m17 13 4 2v3c0 2-1.5 3.5-4 4-2.5-.5-4-2-4-4v-3z"/>',
            'security' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
            'modules' => '<path d="M8 3h8v6h5v8h-5v4H8v-4H3V9h5z"/>',
            'marketplace' => '<path d="M4 9h16l-1 12H5zM7 9V7a5 5 0 0 1 10 0v2"/>',
            'backups' => '<path d="M4 8a8 8 0 1 1 1 10"/><path d="M4 13V8h5M12 7v6l4 2"/>',
            'operations' => '<path d="M4 7h10M4 17h16M14 7h6"/><circle cx="16" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
            'health' => '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
            'updates' => '<path d="M20 11a8 8 0 0 0-15-3M4 3v5h5M4 13a8 8 0 0 0 15 3M20 21v-5h-5"/>',
            'audit' => '<path d="M7 3h10v4H7z"/><path d="M5 5H3v16h18V5h-2M7 12h10M7 16h7"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19 13.5v-3l-2-.7-.7-1.7.9-1.9-2.1-2.1-1.9.9-1.7-.7L10.5 2h-3l-.7 2-1.7.7-1.9-.9-2.1 2.1.9 1.9-.7 1.7-2 .7v3l2 .7.7 1.7-.9 1.9 2.1 2.1 1.9-.9 1.7.7.7 2h3l.7-2 1.7-.7 1.9.9 2.1-2.1-.9-1.9.7-1.7z" transform="translate(2.5 0) scale(.79)"/>',
            default => '<circle cx="12" cy="12" r="8"/><path d="M12 8v8M8 12h8"/>',
        };
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
    }
}
