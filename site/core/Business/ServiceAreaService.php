<?php
declare(strict_types=1);

namespace Core\Business;

use Core\Audit\AuditLog;
use Core\Database\Connection;
use Core\Settings\SettingsRepository;

/** Server-side geocoding, radius decisions, cache, overrides and accessible map output. */
final class ServiceAreaService
{
    /** @var array<string,array<string,array{geocoder:callable,attribution:string}>> */
    private array $providers = [];

    public function __construct(
        private Connection $db,
        private SettingsRepository $settings,
        private ?AuditLog $audit = null,
    ) {}

    public function registerProvider(string $packageId, string $name, callable $geocoder, string $attribution): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $name)) throw new \InvalidArgumentException('Geocoding provider names must be stable lowercase tokens.');
        $attribution = mb_substr(trim(strip_tags($attribution)), 0, 190);
        if ($attribution === '') throw new \InvalidArgumentException('Geocoding provider attribution is required.');
        if (isset($this->providers[$packageId][$name])) throw new \InvalidArgumentException('Geocoding provider is already registered by this package.');
        $this->providers[$packageId][$name] = ['geocoder' => $geocoder, 'attribution' => $attribution];
    }

    /** @return array<string,string> provider key => public attribution */
    public function providers(string $packageId): array
    {
        return array_map(static fn (array $item): string => $item['attribution'], $this->providers[$packageId] ?? []);
    }

    public function configureRegistered(string $packageId, array $input, string $provider = ''): array
    {
        [$provider, $entry] = $this->registeredProvider($packageId, $provider);
        if ($entry === null) return [false, 'No server-side geocoding provider is registered for this module.'];
        [$ok, $error] = $this->configure($packageId, $input, $entry['geocoder'], $provider);
        if ($ok) $this->settings->set('business.service_area_provider_attribution', $entry['attribution']);
        return [$ok, $error];
    }

    /** Resolve an address only through a provider registered by this package. */
    public function checkRegistered(string $packageId, array|string $address, string $provider = ''): array
    {
        [$provider, $entry] = $this->registeredProvider($packageId, $provider);
        if ($entry === null) return $this->unknown('Address verification is unavailable and requires manual review.');
        return $this->checkAddress($packageId, $address, $entry['geocoder'], $provider);
    }

    /**
     * Resolve and persist the shared service-area center. The callable receives
     * one normalized query and returns status, latitude, longitude, normalized,
     * precision and optional provider. Provider secrets never enter storage.
     */
    public function configure(string $packageId, array $input, callable $geocoder, string $provider): array
    {
        $city = $this->text($input['city'] ?? '', 120);
        $region = $this->text($input['region'] ?? '', 120);
        $country = $this->text($input['country'] ?? '', 120);
        $radius = filter_var($input['radius_miles'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($city === '' || $region === '') return [false, 'Enter the service-area city and state or region.'];
        if ($radius === false || $radius < 1 || $radius > 500) return [false, 'Service radius must be between 1 and 500 miles.'];
        $result = $this->geocode(implode(', ', array_filter([$city, $region, $country])), $geocoder, $provider);
        if ($result['status'] !== 'ok') {
            return [false, $result['status'] === 'ambiguous'
                ? 'The service-area center is ambiguous. Add more location detail.'
                : 'The service-area center could not be verified. Try again or review the provider.'];
        }
        foreach ([
            'center_city' => $city, 'center_region' => $region, 'center_country' => $country,
            'radius_miles' => (string) round((float) $radius, 2),
            'latitude' => (string) $result['latitude'], 'longitude' => (string) $result['longitude'],
            'provider_key' => (string) $result['provider'], 'precision' => (string) $result['precision'],
            'normalized' => (string) $result['normalized'], 'geocoded_at' => date('c'),
        ] as $key => $value) {
            $this->settings->set('business.service_area_' . $key, $value);
        }
        $this->settings->set('business.service_area_owner', $packageId);
        return [true, null];
    }

    /** @return array<string,mixed> */
    public function profile(): array
    {
        $result = [];
        foreach (['center_city','center_region','center_country','radius_miles','latitude','longitude','provider_key','provider_attribution','precision','normalized','geocoded_at'] as $key) {
            $result[$key] = trim((string) $this->settings->get('business.service_area_' . $key, ''));
        }
        $result['configured'] = $result['latitude'] !== '' && $result['longitude'] !== ''
            && (float) $result['radius_miles'] >= 1;
        return $result;
    }

    /** @return array{status:string,distance_miles:?float,address:string,precision:string,override:bool,message:string} */
    public function checkAddress(string $packageId, array|string $address, callable $geocoder, string $provider): array
    {
        $query = $this->normalizedAddress($address);
        if (mb_strlen($query) < 6) return $this->unknown('Enter a complete service address.');
        $hash = hash('sha256', mb_strtolower($query));
        $override = $this->activeOverride($packageId, $hash);
        if ($override !== null) {
            $inside = $override['decision'] === 'inside';
            return [
                'status' => $inside ? 'inside' : 'outside', 'distance_miles' => null,
                'address' => $query, 'precision' => 'staff_override', 'override' => true,
                'message' => $inside ? 'This address was approved by staff.' : 'This address is outside the approved service area.',
            ];
        }
        $area = $this->profile();
        if (!$area['configured']) return $this->unknown('The service area has not been configured.');
        $result = $this->geocode($query, $geocoder, $provider);
        if ($result['status'] === 'ambiguous') return $this->unknown('The address is ambiguous. Check it and add missing details.');
        if ($result['status'] !== 'ok') return $this->unknown('The address could not be verified. It requires manual review.');
        if (!in_array($result['precision'], ['rooftop', 'parcel', 'street', 'postal', 'locality'], true)) {
            return $this->unknown('The address result is not precise enough. It requires correction or manual review.');
        }
        $distance = self::distanceMiles(
            (float) $area['latitude'], (float) $area['longitude'],
            (float) $result['latitude'], (float) $result['longitude'],
        );
        $inside = $distance <= (float) $area['radius_miles'] + 0.00001;
        return [
            'status' => $inside ? 'inside' : 'outside',
            'distance_miles' => round($distance, 2),
            'address' => (string) $result['normalized'], 'precision' => (string) $result['precision'],
            'override' => false,
            'message' => $inside
                ? 'This address is inside the service area.'
                : 'This address is currently outside the service area.',
        ];
    }

    public function override(
        string $packageId,
        array|string $address,
        bool $inside,
        string $reason,
        int $adminId,
        ?string $expiresAt = null,
    ): array {
        $query = $this->normalizedAddress($address);
        $reason = $this->text($reason, 500);
        if (mb_strlen($query) < 6 || $reason === '' || $adminId < 1) return [false, 'Address, reason, and administrator are required.'];
        if ($expiresAt !== null && strtotime($expiresAt) === false) return [false, 'Override expiration is invalid.'];
        $hash = hash('sha256', mb_strtolower($this->text($query, 500)));
        $this->db->insert('service_area_overrides', [
            'owner_package' => $packageId, 'address_hash' => $hash,
            'decision' => $inside ? 'inside' : 'outside', 'reason' => $reason,
            'admin_id' => $adminId,
            'expires_at' => $expiresAt === null ? null : date('Y-m-d H:i:s', (int) strtotime($expiresAt)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit?->record('service_area.override', $adminId, [
            'package' => $packageId, 'address_hash' => $hash,
            'decision' => $inside ? 'inside' : 'outside', 'reason' => $reason,
        ]);
        return [true, null];
    }

    /** Accessible, dependency-free map/fallback that never leaks provider credentials. */
    public function renderMap(string $label = 'Service area'): string
    {
        $area = $this->profile();
        if (!$area['configured']) {
            return '<section class="wc-service-area"><h2>' . e($label) . '</h2><p>Service-area details are being updated. Contact us to confirm availability.</p></section>';
        }
        $location = trim(implode(', ', array_filter([$area['center_city'], $area['center_region'], $area['center_country']])));
        $radius = rtrim(rtrim(number_format((float) $area['radius_miles'], 2, '.', ''), '0'), '.');
        $provider = $area['provider_attribution'] !== '' ? $area['provider_attribution'] : 'configured geocoding provider';
        return '<section class="wc-service-area" aria-labelledby="wc-service-area-title">'
            . '<h2 id="wc-service-area-title">' . e($label) . '</h2>'
            . '<div class="wc-service-area-map" role="img" aria-label="Approximately ' . e_attr($radius)
            . ' miles around ' . e_attr($location) . '"><svg viewBox="0 0 400 240" aria-hidden="true" focusable="false">'
            . '<circle cx="200" cy="120" r="92"></circle><circle class="center" cx="200" cy="120" r="7"></circle>'
            . '<path d="M200 28v184M108 120h184"></path></svg></div>'
            . '<p><strong>' . e($radius) . '-mile service radius</strong> centered on ' . e($location) . '.</p>'
            . '<p class="wc-map-fallback">Enter the complete event or delivery address to confirm availability. '
            . 'If verification is unavailable, the request will be marked for manual review instead of being rejected.</p>'
            . '<small>Location data provided by ' . e($provider) . '.</small></section>';
    }

    /** @return array<string,mixed> */
    private function geocode(string $query, callable $geocoder, string $provider): array
    {
        $query = $this->text($query, 500);
        $provider = preg_replace('/[^a-z0-9_.-]/i', '', $provider) ?: 'provider';
        $hash = hash('sha256', mb_strtolower($provider . "\0" . $query));
        $cached = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('service_area_cache') . '` WHERE address_hash=? AND expires_at>?',
            [$hash, date('Y-m-d H:i:s')],
        );
        if ($cached) return $this->cacheResult($cached);
        try {
            $raw = $geocoder($query);
        } catch (\Throwable $e) {
            error_log('[website-core] geocoder failed: ' . $e->getMessage());
            $raw = ['status' => 'unavailable'];
        }
        $raw = is_array($raw) ? $raw : ['status' => 'unavailable'];
        $status = in_array($raw['status'] ?? '', ['ok', 'ambiguous', 'not_found', 'unavailable'], true)
            ? (string) $raw['status'] : 'unavailable';
        $lat = filter_var($raw['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($raw['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($status === 'ok' && ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
            $status = 'unavailable'; $lat = $lng = false;
        }
        $row = [
            'address_hash' => $hash, 'provider' => $provider, 'query_text' => $query,
            'status' => $status, 'normalized_address' => $this->text($raw['normalized'] ?? $query, 500),
            'latitude' => $lat === false ? null : $lat, 'longitude' => $lng === false ? null : $lng,
            'precision_name' => $this->text($raw['precision'] ?? '', 40),
            'cached_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + ($status === 'ok' ? 2592000 : 900)),
        ];
        $this->db->run('DELETE FROM `' . $this->db->table('service_area_cache') . '` WHERE address_hash=?', [$hash]);
        $this->db->insert('service_area_cache', $row);
        return $this->cacheResult($row);
    }

    private function cacheResult(array $row): array
    {
        return [
            'status' => (string) $row['status'], 'provider' => (string) $row['provider'],
            'normalized' => (string) ($row['normalized_address'] ?? ''),
            'latitude' => $row['latitude'] === null ? null : (float) $row['latitude'],
            'longitude' => $row['longitude'] === null ? null : (float) $row['longitude'],
            'precision' => (string) ($row['precision_name'] ?? ''),
        ];
    }

    private function activeOverride(string $packageId, string $hash): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('service_area_overrides')
            . '` WHERE owner_package=? AND address_hash=? AND (expires_at IS NULL OR expires_at>?) ORDER BY id DESC LIMIT 1',
            [$packageId, $hash, date('Y-m-d H:i:s')],
        ) ?: null;
    }

    /** @return array{0:string,1:?array{geocoder:callable,attribution:string}} */
    private function registeredProvider(string $packageId, string $provider): array
    {
        $registered = $this->providers[$packageId] ?? [];
        if ($provider === '') {
            $saved = trim((string) $this->settings->get('business.service_area_provider_key', ''));
            $provider = isset($registered[$saved]) ? $saved : (string) array_key_first($registered);
        }
        return [$provider, $registered[$provider] ?? null];
    }

    private function unknown(string $message): array
    {
        return ['status' => 'unknown', 'distance_miles' => null, 'address' => '', 'precision' => '', 'override' => false, 'message' => $message];
    }

    private function text(mixed $value, int $limit): string
    {
        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $value))), 0, $limit);
    }

    private function normalizedAddress(array|string $address): string
    {
        if (!is_array($address)) return $this->text($address, 500);
        return $this->text(implode(', ', array_filter(array_map(
            fn (mixed $value): string => $this->text($value, 190),
            [$address['line1'] ?? '', $address['line2'] ?? '', $address['city'] ?? '', $address['region'] ?? '', $address['postal_code'] ?? '', $address['country'] ?? ''],
        ))), 500);
    }

    public static function distanceMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthMiles = 3958.7613;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        return $earthMiles * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
