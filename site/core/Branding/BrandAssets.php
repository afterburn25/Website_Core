<?php
declare(strict_types=1);

namespace Core\Branding;

use Core\Database\Connection;
use Core\Http\Request;
use Core\Media\MediaService;
use Core\Settings\SettingsRepository;

/** Core-owned brand media, including one-time defaults supplied by modules. */
final class BrandAssets
{
    private const SETTINGS = [
        'logo' => 'brand.logo_media_id',
        'favicon' => 'brand.favicon_media_id',
        'social' => 'brand.social_media_id',
    ];
    private const SOCIALS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'x' => 'X',
    ];

    public function __construct(
        private Connection $db,
        private SettingsRepository $settings,
        private MediaService $media,
    ) {}

    /**
     * Copy package defaults into Core Media once. Existing Core selections
     * always win, and the marker prevents a deliberately cleared asset from
     * being restored on a later request or module update.
     *
     * @param array<string,string> $assets role => package-relative path
     */
    public function seed(string $packageId, string $packageRoot, array $assets): void
    {
        $marker = 'branding.seeded.' . str_replace('/', '.', $packageId);
        if ((bool) $this->settings->get($marker, false)) return;

        $root = realpath($packageRoot);
        if ($root === false || !is_dir($root)) {
            throw new \InvalidArgumentException('Brand asset package root is unavailable.');
        }
        $rootPrefix = rtrim(str_replace('\\', '/', $root), '/') . '/';

        foreach ($assets as $role => $relative) {
            if (!isset(self::SETTINGS[$role]) || !is_string($relative) || trim($relative) === '') {
                throw new \InvalidArgumentException('Brand assets support logo, favicon, and social image paths.');
            }
            if (str_contains($relative, "\0") || str_starts_with(str_replace('\\', '/', $relative), '/')) {
                throw new \InvalidArgumentException('Brand asset paths must be relative to the package.');
            }
            $source = realpath($root . '/' . ltrim($relative, '/\\'));
            $normalized = $source === false ? '' : str_replace('\\', '/', $source);
            if ($source === false || !str_starts_with($normalized, $rootPrefix) || !is_file($source)) {
                throw new \InvalidArgumentException('Brand asset path escapes the package or does not exist.');
            }

            $setting = self::SETTINGS[$role];
            if ((int) $this->settings->get($setting, 0) > 0) continue;

            [$id, $error] = $this->media->importFile($source, null, 'Branding', basename($source));
            if ($error !== null || $id === null) {
                throw new \RuntimeException('Unable to import module brand asset: ' . ($error ?? 'unknown error'));
            }
            $record = $this->media->find($id);
            if (!$record || !str_starts_with((string) $record['mime'], 'image/')) {
                $this->media->delete($id);
                throw new \InvalidArgumentException('Brand assets must be supported image files.');
            }
            $this->settings->set($setting, $id);
            $this->db->insert('media_usage', [
                'media_id' => $id,
                'context' => 'core.branding',
                'context_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->settings->set($marker, true);
    }

    public function url(Request $request, string $role, ?int $width = null): ?string
    {
        if (!isset(self::SETTINGS[$role])) return null;
        $id = (int) $this->settings->get(self::SETTINGS[$role], 0);
        if ($id < 1) return null;
        $record = $this->db->fetch(
            'SELECT id, original_name FROM `' . $this->db->table('media') . '` WHERE id = ? AND is_private = 0',
            [$id]
        );
        if (!$record) return null;
        return $request->routeUrl('/media/' . $id
            . ($width ? '/w/' . max(64, min(2400, $width)) : '')
            . '/' . rawurlencode((string) $record['original_name']));
    }

    /** Render the administrator's plain-text footer notice with durable tokens. */
    public function copyrightNotice(string $siteName): string
    {
        $template = trim((string) $this->settings->get('footer.copyright', ''));
        if ($template === '') {
            $template = '© {year} {site_name}. All rights reserved.';
        }
        return str_replace(
            ['{year}', '{site_name}'],
            [date('Y'), trim($siteName)],
            $template,
        );
    }

    /** @return array<string,string> platform key => public label */
    public function socialDefinitions(): array
    {
        return self::SOCIALS;
    }

    /** @return list<array{platform:string,label:string,url:string,placement:string}> */
    public function socialLinks(?string $location = null): array
    {
        $links = [];
        foreach (self::SOCIALS as $platform => $label) {
            $url = trim((string) $this->settings->get('social.' . $platform . '.url', ''));
            $placement = (string) $this->settings->get('social.' . $platform . '.placement', 'footer');
            if (!in_array($placement, ['header', 'footer', 'both'], true)) $placement = 'footer';
            $matches = $location === null || $placement === 'both' || $placement === $location;
            if ($matches && $url !== '' && self::validSocialUrl($url)) {
                $links[] = compact('platform', 'label', 'url', 'placement');
            }
        }
        return $links;
    }

    public static function validSocialUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
