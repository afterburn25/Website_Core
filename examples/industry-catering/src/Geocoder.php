<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

/** Small server-side adapter for Core's provider-neutral geocoding contract. */
final class Geocoder
{
    public function __construct(private string $contact = '') {}

    /** @return array{status:string,latitude?:float,longitude?:float,normalized?:string,precision?:string,provider?:string} */
    public function __invoke(string $query): array
    {
        if (!function_exists('curl_init')) return ['status' => 'unavailable'];
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'addressdetails' => '1',
            'limit' => '1',
        ], '', '&', PHP_QUERY_RFC3986);
        $curl = curl_init($url);
        if ($curl === false) return ['status' => 'unavailable'];
        $agent = 'CajunWebPro-Catering/2.0';
        if (filter_var($this->contact, FILTER_VALIDATE_EMAIL)) $agent .= ' (contact: ' . $this->contact . ')';
        $body = '';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: ' . $agent],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 1_000_000) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $received = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($received !== true || $status !== 200 || $body === '') {
            return ['status' => 'unavailable'];
        }
        try {
            $results = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['status' => 'unavailable'];
        }
        if (!is_array($results) || $results === []) return ['status' => 'not_found'];
        $first = $results[0] ?? null;
        if (!is_array($first) || !is_numeric($first['lat'] ?? null) || !is_numeric($first['lon'] ?? null)) {
            return ['status' => 'unavailable'];
        }
        $precision = self::precision((string) ($first['addresstype'] ?? $first['type'] ?? ''));
        if ($precision === '') return ['status' => 'ambiguous'];
        return [
            'status' => 'ok',
            'latitude' => (float) $first['lat'],
            'longitude' => (float) $first['lon'],
            'normalized' => mb_substr(trim((string) ($first['display_name'] ?? $query)), 0, 500),
            'precision' => $precision,
            'provider' => 'openstreetmap-nominatim',
        ];
    }

    private static function precision(string $type): string
    {
        return match (strtolower($type)) {
            'house', 'building', 'office', 'amenity', 'shop', 'commercial' => 'rooftop',
            'plot', 'parcel' => 'parcel',
            'road', 'street', 'residential', 'pedestrian' => 'street',
            'postcode' => 'postal',
            'city', 'town', 'village', 'municipality', 'hamlet', 'county', 'state_district' => 'locality',
            default => '',
        };
    }
}
