<?php
declare(strict_types=1);

namespace Core\Content;

/**
 * Canonical reusable business-presentation blocks. They deliberately contain
 * presentation data only; authoritative prices, schedules and industry rules
 * remain in the declaring package's records and services.
 */
final class PresentationBlocks
{
    /** @return array<string,array{label:string,fields:array}> */
    public static function definitions(): array
    {
        $text = static fn (string $label, string $type = 'text'): array => [
            'label' => $label,
            'type' => $type,
        ];

        return [
            'faq' => ['label' => 'FAQ', 'fields' => [
                'heading' => $text('Heading'),
                'items' => $text('One question | answer per line', 'lines'),
            ]],
            'gallery' => ['label' => 'Gallery', 'fields' => [
                'heading' => $text('Heading'),
                'media_ids' => $text('Media IDs (comma separated)', 'media_list'),
            ]],
            'testimonials' => ['label' => 'Testimonials', 'fields' => [
                'heading' => $text('Heading'),
                'items' => $text('One quote | name per line', 'lines'),
            ]],
            'services' => ['label' => 'Services', 'fields' => [
                'heading' => $text('Heading'),
                'intro' => $text('Introduction', 'richtext'),
                'items' => $text('One name | description | URL per line', 'lines'),
            ]],
            'team' => ['label' => 'Team', 'fields' => [
                'heading' => $text('Heading'),
                'intro' => $text('Introduction', 'richtext'),
                'items' => $text('One name | role | biography per line', 'lines'),
            ]],
            'cta' => ['label' => 'Call to action', 'fields' => [
                'heading' => $text('Heading'),
                'body' => $text('Text', 'richtext'),
                'button_label' => $text('Button label'),
                'button_url' => $text('Button URL', 'url'),
            ]],
        ];
    }

    /** @return list<list<string>> */
    public static function rows(string $value, int $columns): array
    {
        $rows = [];
        foreach (array_slice(preg_split('/\R/u', $value) ?: [], 0, 100) as $line) {
            if (trim($line) === '') continue;
            $parts = array_map(
                static fn (string $part): string => mb_substr(trim($part), 0, 2000),
                explode('|', $line, $columns),
            );
            $rows[] = array_pad($parts, $columns, '');
        }
        return $rows;
    }
}
