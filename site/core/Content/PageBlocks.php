<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;
use Core\Modules\ModuleRegistry;

/** Validates and renders the portable structured page-block document. */
final class PageBlocks
{
    public function __construct(private Connection $db, private ?ModuleRegistry $registry = null) {}

    /** @return array<string,array{label:string,fields:array,renderer:?callable,package:string}> */
    public function definitions(): array
    {
        $text = fn (string $label, string $type = 'text') => ['label' => $label, 'type' => $type];
        $defs = [
            'hero' => ['label' => 'Hero', 'fields' => [
                'eyebrow' => $text('Eyebrow'), 'heading' => $text('Heading'),
                'body' => $text('Body', 'richtext'), 'image_id' => $text('Background image media ID', 'media'),
                'button_label' => $text('Button label'), 'button_url' => $text('Button URL', 'url'),
                'align' => ['label' => 'Alignment', 'type' => 'select', 'options' => ['left','center','right']],
            ]],
            'rich_text' => ['label' => 'Rich text', 'fields' => [
                'heading' => $text('Heading'), 'body' => $text('Content', 'richtext'),
            ]],
            'image_text' => ['label' => 'Image + text', 'fields' => [
                'heading' => $text('Heading'), 'body' => $text('Content', 'richtext'),
                'image_id' => $text('Image media ID', 'media'),
                'layout' => ['label' => 'Image position', 'type' => 'select', 'options' => ['left','right']],
            ]],
            'gallery' => ['label' => 'Gallery', 'fields' => [
                'heading' => $text('Heading'), 'media_ids' => $text('Media IDs (comma separated)', 'media_list'),
            ]],
            'cta' => ['label' => 'Call to action', 'fields' => [
                'heading' => $text('Heading'), 'body' => $text('Text', 'richtext'),
                'button_label' => $text('Button label'), 'button_url' => $text('Button URL', 'url'),
            ]],
            'testimonials' => ['label' => 'Testimonials', 'fields' => [
                'heading' => $text('Heading'), 'items' => $text('One quote | name per line', 'lines'),
            ]],
            'faq' => ['label' => 'FAQ', 'fields' => [
                'heading' => $text('Heading'), 'items' => $text('One question | answer per line', 'lines'),
            ]],
            'stats' => ['label' => 'Statistics', 'fields' => [
                'heading' => $text('Heading'), 'items' => $text('One value | label per line', 'lines'),
            ]],
            'logo_strip' => ['label' => 'Logo strip', 'fields' => [
                'heading' => $text('Heading'), 'media_ids' => $text('Media IDs (comma separated)', 'media_list'),
            ]],
            'contact' => ['label' => 'Contact details', 'fields' => [
                'heading' => $text('Heading'), 'body' => $text('Intro', 'richtext'),
            ]],
            'spacer' => ['label' => 'Spacer / divider', 'fields' => [
                'size' => ['label' => 'Size', 'type' => 'select', 'options' => ['small','medium','large']],
            ]],
        ];
        foreach ($defs as $type => &$def) {
            $def['renderer'] = null;
            $def['package'] = 'core';
        }
        unset($def);
        foreach ($this->registry?->pageBlocks() ?? [] as $type => $def) {
            if (!isset($defs[$type])) $defs[$type] = $def;
        }
        return $defs;
    }

    /** @return array{0:string,1:?string} normalized JSON + validation error */
    public function normalize(string $json): array
    {
        if (trim($json) === '') return ['[]', null];
        $blocks = json_decode($json, true);
        if (!is_array($blocks)) return ['[]', 'The page layout data is invalid. Reload and try again.'];
        $definitions = $this->definitions();
        $clean = [];
        foreach (array_slice(array_values($blocks), 0, 100) as $block) {
            if (!is_array($block)) continue;
            $type = (string) ($block['type'] ?? '');
            if (!isset($definitions[$type])) continue;
            $data = [];
            foreach ($definitions[$type]['fields'] as $key => $field) {
                $value = is_scalar($block['data'][$key] ?? null) ? (string) $block['data'][$key] : '';
                $data[$key] = $this->cleanValue($value, (string) ($field['type'] ?? 'text'));
            }
            $clean[] = [
                'id' => preg_match('/^[a-zA-Z0-9_-]{4,80}$/', (string) ($block['id'] ?? ''))
                    ? (string) $block['id'] : bin2hex(random_bytes(6)),
                'type' => $type, 'hidden' => !empty($block['hidden']), 'data' => $data,
            ];
        }
        return [json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), null];
    }

    public function render(?string $json, string $basePath = ''): string
    {
        $blocks = json_decode((string) $json, true);
        if (!is_array($blocks)) return '';
        $defs = $this->definitions();
        $html = '';
        foreach ($blocks as $block) {
            if (!is_array($block) || !empty($block['hidden'])) continue;
            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            if (!isset($defs[$type])) continue;
            if (is_callable($defs[$type]['renderer'] ?? null)) {
                $html .= (string) ($defs[$type]['renderer'])($data, $basePath);
            } else {
                $html .= $this->renderCore($type, $data, $basePath);
            }
        }
        return $html;
    }

    private function cleanValue(string $value, string $type): string
    {
        $value = trim($value);
        return match ($type) {
            'richtext' => (new Sanitizer())->html($value),
            'url' => $this->safeUrl($value),
            'media' => (string) max(0, (int) $value),
            'media_list' => implode(',', array_slice(array_values(array_unique(array_filter(array_map(
                fn ($v) => (int) trim($v), explode(',', $value)
            )))), 0, 40)),
            'lines' => mb_substr(strip_tags($value), 0, 10000),
            default => mb_substr(strip_tags($value), 0, 2000),
        };
    }

    private function safeUrl(string $url): string
    {
        if ($url === '') return '';
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https','mailto','tel'], true)
            ? $url : '';
    }

    private function renderCore(string $type, array $d, string $basePath): string
    {
        $heading = ($d['heading'] ?? '') !== '' ? '<h2>' . e((string) $d['heading']) . '</h2>' : '';
        $body = (string) ($d['body'] ?? '');
        if ($type === 'spacer') return '<div class="wc-block wc-spacer wc-spacer-' . e_attr((string) ($d['size'] ?? 'medium')) . '" aria-hidden="true"></div>';
        if ($type === 'hero') {
            $style = ($img = $this->mediaUrl((int) ($d['image_id'] ?? 0), $basePath, 1600))
                ? ' style="background-image:linear-gradient(#07111daa,#07111daa),url(' . e_attr($img) . ')"' : '';
            return '<section class="wc-block wc-hero align-' . e_attr((string) ($d['align'] ?? 'left')) . '"' . $style . '>'
                . (($d['eyebrow'] ?? '') !== '' ? '<p class="wc-eyebrow">' . e((string) $d['eyebrow']) . '</p>' : '')
                . '<h1>' . e((string) ($d['heading'] ?? '')) . '</h1><div>' . $body . '</div>' . $this->button($d, $basePath) . '</section>';
        }
        if ($type === 'rich_text') return '<section class="wc-block wc-rich-text">' . $heading . $body . '</section>';
        if ($type === 'image_text') {
            $image = $this->image((int) ($d['image_id'] ?? 0), $basePath, 900);
            return '<section class="wc-block wc-image-text image-' . e_attr((string) ($d['layout'] ?? 'left')) . '">' . $image
                . '<div>' . $heading . $body . '</div></section>';
        }
        if (in_array($type, ['gallery','logo_strip'], true)) {
            $images = '';
            foreach (explode(',', (string) ($d['media_ids'] ?? '')) as $id) $images .= $this->image((int) $id, $basePath, $type === 'gallery' ? 900 : 360);
            return '<section class="wc-block wc-' . e_attr(str_replace('_','-',$type)) . '">' . $heading . '<div class="wc-image-grid">' . $images . '</div></section>';
        }
        if ($type === 'cta') return '<section class="wc-block wc-cta">' . $heading . $body . $this->button($d, $basePath) . '</section>';
        if (in_array($type, ['testimonials','faq','stats'], true)) {
            $items = '';
            foreach (preg_split('/\R/', (string) ($d['items'] ?? '')) ?: [] as $line) {
                if (trim($line) === '') continue;
                [$a,$b] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
                $items .= $type === 'faq'
                    ? '<details><summary>' . e($a) . '</summary><p>' . e($b) . '</p></details>'
                    : '<article><strong>' . e($a) . '</strong><span>' . e($b) . '</span></article>';
            }
            return '<section class="wc-block wc-' . e_attr($type) . '">' . $heading . '<div class="wc-item-grid">' . $items . '</div></section>';
        }
        if ($type === 'contact') return '<section class="wc-block wc-contact">' . $heading . $body . '</section>';
        return '';
    }

    private function button(array $d, string $basePath): string
    {
        if (($d['button_label'] ?? '') === '' || ($d['button_url'] ?? '') === '') return '';
        $url = (string) $d['button_url'];
        if (str_starts_with($url, '/')) $url = rtrim($basePath, '/') . $url;
        return '<p><a class="wc-button" href="' . e_url($url) . '">' . e((string) $d['button_label']) . '</a></p>';
    }

    private function image(int $id, string $basePath, int $width): string
    {
        $url = $this->mediaUrl($id, $basePath, $width);
        if ($url === null) return '';
        $row = $this->db->fetch('SELECT alt_text, original_name FROM `' . $this->db->table('media') . '` WHERE id = ?', [$id]);
        return '<img src="' . e_attr($url) . '" alt="' . e_attr((string) (($row['alt_text'] ?? '') ?: ($row['original_name'] ?? ''))) . '" loading="lazy">';
    }

    private function mediaUrl(int $id, string $basePath, int $width): ?string
    {
        if ($id < 1) return null;
        $row = $this->db->fetch('SELECT original_name FROM `' . $this->db->table('media') . '` WHERE id = ? AND is_private = 0', [$id]);
        if (!$row) return null;
        return rtrim($basePath, '/') . '/media/' . $id . '/w/' . $width . '/' . rawurlencode((string) $row['original_name']);
    }
}
