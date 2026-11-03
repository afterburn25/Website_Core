<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Business\BusinessProfile;
use Core\Business\ServiceAreaService;
use Core\Content\ContentItems;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Modules\ModuleContext;

/** Package-owned data for Core's validated JSON-LD renderer. */
final class StructuredData
{
    public function __construct(private ModuleContext $ctx) {}

    /** @return list<array<string,mixed>> */
    public function forRequest(Request $request): array
    {
        $profile = $this->ctx->service(BusinessProfile::class)->get();
        $business = array_filter([
            '@type' => 'Caterer',
            'name' => $profile['name'] ?: 'Catering business',
            'email' => $profile['email'],
            'telephone' => $profile['phone'],
            'url' => $profile['website'],
            'address' => $this->address($profile),
            'areaServed' => $profile['service_area'],
        ], static fn (mixed $value): bool => $value !== '' && $value !== [] && $value !== null);
        $objects = [$business];
        $path = rtrim($request->path, '/') ?: '/';
        if ($path === '/faq') $objects[] = $this->faq();
        if ($path === '/menus' || str_starts_with($path, '/menus/')) $objects[] = $this->menu();
        if ($path === '/services' || str_starts_with($path, '/services/')) {
            foreach ($this->services($path) as $service) $objects[] = $service;
        }
        if ($path === '/event-stories') {
            foreach ($this->reviews() as $review) $objects[] = $review;
        }
        if ($path === '/service-area') {
            $area = $this->ctx->service(ServiceAreaService::class)->profile();
            if (!empty($area['configured'])) {
                $objects[] = [
                    '@type' => 'Place',
                    'name' => 'Catering service area',
                    'description' => (string) $area['radius_miles'] . '-mile service radius centered on '
                        . trim((string) $area['center_city'] . ', ' . (string) $area['center_region']),
                    'geo' => [
                        '@type' => 'GeoCoordinates',
                        'latitude' => (float) $area['latitude'],
                        'longitude' => (float) $area['longitude'],
                    ],
                ];
            }
        }
        return $objects;
    }

    private function address(array $profile): array
    {
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim(($profile['address_line_1'] ?? '') . ' ' . ($profile['address_line_2'] ?? '')),
            'addressLocality' => $profile['city'] ?? '',
            'addressRegion' => $profile['region'] ?? '',
            'postalCode' => $profile['postal_code'] ?? '',
            'addressCountry' => $profile['country'] ?? '',
        ], static fn (mixed $value): bool => $value !== '');
        return count($address) > 1 ? $address : [];
    }

    private function faq(): array
    {
        $questions = [];
        foreach (CateringController::publicFaqs() as [$question, $answer]) {
            $questions[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }
        return ['@type' => 'FAQPage', 'mainEntity' => $questions];
    }

    private function menu(): array
    {
        $db = $this->ctx->service(Connection::class);
        $sections = [];
        foreach ($db->fetchAll('SELECT * FROM `' . $db->table('catering_menu_categories') . '` WHERE visible=1 ORDER BY sort_order,id') as $category) {
            $items = [];
            foreach ($db->fetchAll('SELECT * FROM `' . $db->table('catering_menu_items') . '` WHERE category_id=? AND visible=1 ORDER BY sort_order,id LIMIT 25', [(int) $category['id']]) as $item) {
                $items[] = ['@type' => 'MenuItem', 'name' => (string) $item['name'], 'description' => (string) ($item['description'] ?? '')];
            }
            $sections[] = ['@type' => 'MenuSection', 'name' => (string) $category['name'], 'hasMenuItem' => $items];
        }
        return ['@type' => 'Menu', 'name' => 'Catering menu', 'hasMenuSection' => $sections];
    }

    /** @return list<array<string,mixed>> */
    private function services(string $path): array
    {
        $objects = [];
        foreach (CateringController::publicServices() as $slug => $service) {
            if ($path !== '/services' && $path !== '/services/' . $slug) continue;
            $objects[] = ['@type' => 'Service', 'name' => $service['title'], 'description' => $service['summary']];
        }
        return $objects;
    }

    /** @return list<array<string,mixed>> */
    private function reviews(): array
    {
        $objects = [];
        $items = $this->ctx->service(ContentItems::class)->items($this->ctx->packageId(), 'catering_review', true);
        foreach (array_slice($items, 0, 20) as $item) {
            $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
            if (empty($fields['verified'])) continue;
            $rating = max(1, min(5, (int) ($fields['rating'] ?? 5)));
            $objects[] = [
                '@type' => 'Review',
                'author' => ['@type' => 'Organization', 'name' => (string) ($fields['customer_name'] ?? 'Verified customer')],
                'reviewBody' => (string) ($fields['review'] ?? ''),
                'reviewRating' => ['ratingValue' => $rating, 'bestRating' => 5, 'worstRating' => 1],
                'itemReviewed' => ['@type' => 'Caterer', 'name' => (string) ($this->ctx->service(BusinessProfile::class)->get()['name'] ?: 'Catering business')],
            ];
        }
        return $objects;
    }
}
