<?php
declare(strict_types=1);

namespace Core\Appearance;

use Core\Audit\AuditLog;
use Core\Database\Connection;
use Core\Modules\Manifest;

/** Registered, package-scoped layout collections with draft/publish/rollback state. */
final class ModuleThemeCollections
{
    /** @var array<string,array{label:string,variants:array<string,array>,asset_namespace:string}> */
    private array $collections = [];

    public function __construct(private Connection $db, private ?AuditLog $audit = null) {}

    public function register(
        string $packageId,
        string $label,
        array $variants,
        string $packageRoot,
        string $assetNamespace,
    ): void {
        if (isset($this->collections[$packageId])) throw new \InvalidArgumentException('Theme collection is already registered by this package.');
        if (trim($label) === '' || mb_strlen(trim($label)) > 120) throw new \InvalidArgumentException('Theme collection label must be 1–120 characters.');
        if ($variants === [] || count($variants) > 50) throw new \InvalidArgumentException('Theme collections require 1–50 variants.');
        $clean = [];
        foreach ($variants as $variant) {
            if (!is_array($variant)) throw new \InvalidArgumentException('Every theme variant must be an object.');
            $id = (string) ($variant['id'] ?? '');
            $name = trim((string) ($variant['name'] ?? ''));
            if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $id) || $name === '' || mb_strlen($name) > 120 || isset($clean[$id])) {
                throw new \InvalidArgumentException('Theme variants require unique safe ids and names.');
            }
            $layout = (string) ($variant['layout'] ?? '');
            if (!Manifest::relativePathOk($layout) || !str_ends_with(strtolower($layout), '.php') || !$this->insidePackage($packageRoot, $layout)) {
                throw new \InvalidArgumentException("Theme variant {$id} has an unsafe or missing layout.");
            }
            $thumbnail = (string) ($variant['thumbnail'] ?? '');
            $thumbnailExtension = strtolower(pathinfo($thumbnail, PATHINFO_EXTENSION));
            if ($thumbnail !== '' && (!Manifest::relativePathOk($thumbnail)
                || !in_array($thumbnailExtension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)
                || !$this->insidePackage($packageRoot, $thumbnail))) {
                throw new \InvalidArgumentException("Theme variant {$id} has an unsafe or missing thumbnail.");
            }
            $assets = ['css' => [], 'js' => []];
            foreach ($assets as $kind => $_) {
                foreach (($variant['assets'][$kind] ?? []) as $path) {
                    $path = (string) $path;
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (!Manifest::relativePathOk($path) || $extension !== $kind
                        || !$this->insidePackage($packageRoot, $path)) {
                        throw new \InvalidArgumentException("Theme variant {$id} contains an unsafe {$kind} asset.");
                    }
                    $assets[$kind][] = $path;
                }
            }
            $palettes = [];
            foreach (($variant['palettes'] ?? []) as $paletteId => $palette) {
                if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', (string) $paletteId) || !is_array($palette)) {
                    throw new \InvalidArgumentException("Theme variant {$id} has an invalid palette.");
                }
                $tokens = $palette['tokens'] ?? $palette;
                if (!is_array($tokens)) throw new \InvalidArgumentException('Palette tokens must be an object.');
                $normalized = [];
                foreach ($tokens as $token => $value) {
                    if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', (string) $token)
                        || !is_string($value) || !preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                        throw new \InvalidArgumentException('Palette tokens require safe names and six-digit colors.');
                    }
                    $normalized[(string) $token] = strtolower($value);
                }
                if (!isset($normalized['background'], $normalized['text'])
                    || self::contrast($normalized['background'], $normalized['text']) < 4.5) {
                    throw new \InvalidArgumentException("Theme palette {$paletteId} must provide accessible background/text contrast.");
                }
                $palettes[(string) $paletteId] = [
                    'name' => mb_substr(trim((string) ($palette['name'] ?? ucfirst((string) $paletteId))), 0, 120),
                    'tokens' => $normalized,
                ];
            }
            if ($palettes === []) throw new \InvalidArgumentException("Theme variant {$id} requires at least one accessible palette.");
            $slots = [];
            foreach (($variant['media_slots'] ?? []) as $slot => $slotDefinition) {
                if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', (string) $slot)) throw new \InvalidArgumentException('Media slots require safe ids.');
                $slotDefinition = is_array($slotDefinition) ? $slotDefinition : [];
                $default = (string) ($slotDefinition['default'] ?? '');
                if ($default !== '' && (!Manifest::relativePathOk($default) || !$this->insidePackage($packageRoot, $default))) {
                    throw new \InvalidArgumentException("Media slot {$slot} has an unsafe default.");
                }
                $slots[(string) $slot] = [
                    'label' => mb_substr(trim((string) ($slotDefinition['label'] ?? ucfirst((string) $slot))), 0, 120),
                    'default' => $default,
                ];
            }
            $clean[$id] = [
                'id' => $id, 'name' => $name,
                'description' => mb_substr(trim(strip_tags((string) ($variant['description'] ?? ''))), 0, 500),
                'layout' => $layout, 'thumbnail' => $thumbnail,
                'assets' => $assets, 'palettes' => $palettes,
                'media_slots' => $slots,
            ];
        }
        $this->collections[$packageId] = [
            'label' => trim($label), 'variants' => $clean,
            'asset_namespace' => $assetNamespace,
        ];
    }

    /** @return array<string,array> */
    public function collections(): array
    {
        return $this->collections;
    }

    public function saveDraft(string $packageId, string $variant, string $palette, array $media = [], ?int $adminId = null): array
    {
        $definition = $this->choice($packageId, $variant, $palette);
        if ($definition === null) return [false, 'Choose an available layout and palette.'];
        $cleanMedia = [];
        foreach ($media as $slot => $mediaId) {
            if (!isset($definition['variant']['media_slots'][$slot])) return [false, 'A selected media slot is not declared by this layout.'];
            $mediaId = (int) $mediaId;
            if ($mediaId > 0 && !(bool) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $this->db->table('media') . "` WHERE id=? AND is_private=0 AND mime LIKE 'image/%'",
                [$mediaId],
            )) return [false, 'A selected theme image is unavailable or private.'];
            if ($mediaId > 0) $cleanMedia[(string) $slot] = $mediaId;
        }
        $current = $this->state($packageId);
        $data = [
            'draft_variant' => $variant, 'draft_palette' => $palette,
            'draft_media_json' => json_encode($cleanMedia, JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($current === null) {
            $this->db->insert('module_theme_state', $data + [
                'owner_package' => $packageId, 'published_variant' => null, 'published_palette' => null,
                'published_media_json' => null,
                'previous_variant' => null, 'previous_palette' => null, 'previous_media_json' => null,
            ]);
        } else {
            $this->db->update('module_theme_state', $data, 'owner_package=?', [$packageId]);
        }
        $this->syncMediaUsage($packageId);
        $this->audit?->record('module_theme.draft', $adminId, ['package' => $packageId, 'variant' => $variant, 'palette' => $palette]);
        return [true, null];
    }

    public function publish(string $packageId, ?int $adminId = null): array
    {
        $state = $this->state($packageId);
        if (!$state || !$this->choice($packageId, (string) $state['draft_variant'], (string) $state['draft_palette'])) {
            return [false, 'Save a valid draft layout and palette before publishing.'];
        }
        $this->db->update('module_theme_state', [
            'previous_variant' => $state['published_variant'], 'previous_palette' => $state['published_palette'],
            'previous_media_json' => $state['published_media_json'],
            'published_variant' => $state['draft_variant'], 'published_palette' => $state['draft_palette'],
            'published_media_json' => $state['draft_media_json'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'owner_package=?', [$packageId]);
        $this->syncMediaUsage($packageId);
        $this->audit?->record('module_theme.publish', $adminId, ['package' => $packageId, 'variant' => $state['draft_variant'], 'palette' => $state['draft_palette']]);
        return [true, null];
    }

    /** Save and publish one explicit visual choice from a theme card. */
    public function activate(
        string $packageId,
        string $variant,
        string $palette,
        array $media = [],
        ?int $adminId = null,
    ): array {
        return $this->db->transaction(function () use ($packageId, $variant, $palette, $media, $adminId): array {
            [$saved, $error] = $this->saveDraft($packageId, $variant, $palette, $media, $adminId);
            if (!$saved) return [false, $error];
            return $this->publish($packageId, $adminId);
        });
    }

    public function rollback(string $packageId, ?int $adminId = null): array
    {
        $state = $this->state($packageId);
        if (!$state || !$this->choice($packageId, (string) $state['previous_variant'], (string) $state['previous_palette'])) {
            return [false, 'No compatible previous module theme is available.'];
        }
        $this->db->update('module_theme_state', [
            'published_variant' => $state['previous_variant'], 'published_palette' => $state['previous_palette'],
            'published_media_json' => $state['previous_media_json'],
            'previous_variant' => $state['published_variant'], 'previous_palette' => $state['published_palette'],
            'previous_media_json' => $state['published_media_json'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'owner_package=?', [$packageId]);
        $this->syncMediaUsage($packageId);
        $this->audit?->record('module_theme.rollback', $adminId, ['package' => $packageId]);
        return [true, null];
    }

    /** Current valid published choice, or deterministic first variant/palette fallback. */
    public function current(string $packageId, bool $draft = false): ?array
    {
        $collection = $this->collections[$packageId] ?? null;
        if ($collection === null) return null;
        $state = $this->state($packageId);
        $variant = $draft ? (string) ($state['draft_variant'] ?? '') : (string) ($state['published_variant'] ?? '');
        $palette = $draft ? (string) ($state['draft_palette'] ?? '') : (string) ($state['published_palette'] ?? '');
        $choice = $this->choice($packageId, $variant, $palette);
        if ($choice === null) {
            $variant = (string) array_key_first($collection['variants']);
            $palette = (string) array_key_first($collection['variants'][$variant]['palettes']);
            $choice = $this->choice($packageId, $variant, $palette);
        }
        if ($choice === null) return null;
        $mediaColumn = $draft ? 'draft_media_json' : 'published_media_json';
        $media = json_decode((string) ($state[$mediaColumn] ?? '{}'), true);
        $media = is_array($media) ? $media : [];
        foreach ($media as $slot => $mediaId) {
            if (!isset($choice['variant']['media_slots'][$slot]) || !(bool) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $this->db->table('media') . "` WHERE id=? AND is_private=0 AND mime LIKE 'image/%'",
                [(int) $mediaId],
            )) unset($media[$slot]);
        }
        return $choice + [
            'package' => $packageId, 'collection_label' => $collection['label'],
            'asset_namespace' => $collection['asset_namespace'],
            'media' => $media,
        ];
    }

    private function choice(string $packageId, string $variant, string $palette): ?array
    {
        $definition = $this->collections[$packageId]['variants'][$variant] ?? null;
        $paletteDefinition = $definition['palettes'][$palette] ?? null;
        return $definition && $paletteDefinition
            ? ['variant_id' => $variant, 'palette_id' => $palette, 'variant' => $definition, 'palette' => $paletteDefinition]
            : null;
    }

    private function state(string $packageId): ?array
    {
        return $this->db->fetch('SELECT * FROM `' . $this->db->table('module_theme_state') . '` WHERE owner_package=?', [$packageId]) ?: null;
    }

    /** Keep Media deletion/usage inspection aware of draft, live and rollback selections. */
    private function syncMediaUsage(string $packageId): void
    {
        $context = 'module.theme.' . substr(hash('sha256', $packageId), 0, 24);
        $this->db->run('DELETE FROM `' . $this->db->table('media_usage') . '` WHERE context=?', [$context]);
        $state = $this->state($packageId);
        $ids = [];
        foreach (['draft_media_json', 'published_media_json', 'previous_media_json'] as $column) {
            $media = json_decode((string) ($state[$column] ?? '{}'), true);
            if (!is_array($media)) continue;
            foreach ($media as $id) {
                $id = (int) $id;
                if ($id > 0) $ids[$id] = $id;
            }
        }
        foreach ($ids as $id) {
            if (!(bool) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $this->db->table('media') . "` WHERE id=? AND is_private=0 AND mime LIKE 'image/%'",
                [$id],
            )) continue;
            $this->db->insert('media_usage', [
                'media_id' => $id, 'context' => $context, 'context_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insidePackage(string $root, string $relative): bool
    {
        $root = realpath($root);
        $path = realpath(rtrim((string) $root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        return $root !== false && $path !== false && ($path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR));
    }

    public static function contrast(string $first, string $second): float
    {
        $lum = static function (string $hex): float {
            $values = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
            $values = array_map(static function (int $value): float {
                $channel = $value / 255;
                return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }, $values);
            return 0.2126 * $values[0] + 0.7152 * $values[1] + 0.0722 * $values[2];
        };
        [$light, $dark] = [$lum($first), $lum($second)];
        if ($dark > $light) [$light, $dark] = [$dark, $light];
        return ($light + 0.05) / ($dark + 0.05);
    }
}
