<?php
declare(strict_types=1);

namespace Core\Seo;

use Core\Http\Request;
use Core\Modules\ModuleRegistry;

/**
 * Validates and injects package-supplied Schema.org objects. Packages provide
 * data only; Core owns the script element, @context, encoding, bounds and
 * failure isolation so a module cannot inject arbitrary markup.
 */
final class ModuleSchema
{
    private const TYPES = [
        'Organization', 'LocalBusiness', 'ProfessionalService',
        'FoodEstablishment', 'Restaurant', 'Caterer', 'Service', 'Product',
        'Offer', 'FAQPage', 'Question', 'Answer', 'ItemList', 'ListItem',
        'BreadcrumbList', 'Menu', 'MenuSection', 'MenuItem', 'Place',
        'PostalAddress', 'GeoCoordinates', 'ContactPoint', 'AggregateRating',
        'Review', 'WebSite', 'WebPage', 'ImageObject', 'OpeningHoursSpecification',
    ];

    public function __construct(private ModuleRegistry $registry) {}

    public function inject(string $html, Request $request): string
    {
        if (stripos($html, '</head>') === false) {
            return $html;
        }
        $objects = [];
        foreach ($this->registry->contexts() as $context) {
            foreach ($context->structuredDataProviders() as $key => $provider) {
                try {
                    foreach ($this->normalize($provider($request)) as $object) {
                        $objects[] = $object;
                    }
                } catch (\Throwable $error) {
                    error_log('[website-core] structured data failed: '
                        . $context->packageId() . '/' . $key . ': ' . $error->getMessage());
                }
            }
        }
        if ($objects === []) {
            return $html;
        }
        $document = ['@context' => 'https://schema.org', '@graph' => $objects];
        $json = json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if (!is_string($json) || strlen($json) > 65535) {
            return $html;
        }
        $script = '<script type="application/ld+json">' . $json . '</script>';
        return preg_replace('/<\/head>/i', $script . '</head>', $html, 1) ?? $html;
    }

    /** @return list<array<string,mixed>> */
    public function normalize(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException('Structured data must return an object or list of objects.');
        }
        $objects = array_is_list($value) ? $value : [$value];
        if (count($objects) > 40) {
            throw new \InvalidArgumentException('Structured data is limited to 40 root objects.');
        }
        $nodes = 0;
        $normalized = [];
        foreach ($objects as $object) {
            if (!is_array($object) || array_is_list($object)) {
                throw new \InvalidArgumentException('Every structured-data root must be an object.');
            }
            unset($object['@context']);
            $clean = $this->node($object, 0, $nodes);
            $types = (array) ($clean['@type'] ?? []);
            if ($types === [] || array_diff($types, self::TYPES) !== []) {
                throw new \InvalidArgumentException('Structured data requires an allowlisted Schema.org @type.');
            }
            $normalized[] = $clean;
        }
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || strlen($encoded) > 60000) {
            throw new \InvalidArgumentException('Structured data exceeds the 60 KB package limit.');
        }
        return $normalized;
    }

    private function node(mixed $value, int $depth, int &$nodes): mixed
    {
        if ($depth > 8 || ++$nodes > 500) {
            throw new \InvalidArgumentException('Structured data exceeds its nesting or node limit.');
        }
        if (is_string($value)) {
            if (mb_strlen($value) > 5000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                throw new \InvalidArgumentException('Structured-data text is invalid or too long.');
            }
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Structured data supports only JSON values.');
        }
        if (array_is_list($value)) {
            if (count($value) > 100) {
                throw new \InvalidArgumentException('Structured-data lists are limited to 100 values.');
            }
            return array_map(fn (mixed $item): mixed => $this->node($item, $depth + 1, $nodes), $value);
        }
        $clean = [];
        foreach ($value as $key => $item) {
            $key = (string) $key;
            if (!preg_match('/^(?:@(?:id|type)|[A-Za-z][A-Za-z0-9_-]{0,80})$/', $key)) {
                throw new \InvalidArgumentException('Structured data contains an unsupported property name.');
            }
            $normalized = $this->node($item, $depth + 1, $nodes);
            if ($key === '@type') {
                $types = is_array($normalized) ? $normalized : [$normalized];
                if ($types === [] || array_filter($types, 'is_string') !== $types
                    || array_diff($types, self::TYPES) !== []) {
                    throw new \InvalidArgumentException('Structured data contains an unsupported Schema.org @type.');
                }
            }
            if ($key === '@id' && (!is_string($normalized) || !$this->identifierOk($normalized))) {
                throw new \InvalidArgumentException('Structured-data @id must be an HTTPS URL or fragment.');
            }
            $clean[$key] = $normalized;
        }
        return $clean;
    }

    private function identifierOk(string $value): bool
    {
        if (preg_match('/^#[A-Za-z][A-Za-z0-9_.:-]{0,190}$/', $value)) {
            return true;
        }
        return str_starts_with(strtolower($value), 'https://')
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
