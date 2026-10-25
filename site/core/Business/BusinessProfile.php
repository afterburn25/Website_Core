<?php
declare(strict_types=1);

namespace Core\Business;

use Core\Settings\SettingsRepository;

/** One Core-owned business identity reused by every enabled industry module. */
final class BusinessProfile
{
    private const FIELDS = [
        'name', 'legal_name', 'email', 'phone', 'website', 'address_line_1',
        'address_line_2', 'city', 'region', 'postal_code', 'country',
        'service_area', 'hours',
        'service_area_center_city', 'service_area_center_region', 'service_area_center_country',
        'service_area_radius_miles',
    ];

    public function __construct(private SettingsRepository $settings) {}

    /** @return array<string,string> */
    public function get(): array
    {
        $profile = [];
        foreach (self::FIELDS as $field) {
            $profile[$field] = trim((string) $this->settings->get('business.' . $field, ''));
        }
        return $profile;
    }

    /** @return list<string> */
    public function save(array $input): array
    {
        // Optional module fields may be absent while their module is disabled;
        // a partial profile edit must not erase preserved shared data.
        $values = $this->normalize(array_replace($this->get(), $input));
        $errors = $this->validate($values);
        if ($errors !== []) return $errors;
        foreach ($values as $field => $value) $this->settings->set('business.' . $field, $value);
        return [];
    }

    /** Seed blank values once without replacing administrator edits. */
    public function seed(string $packageId, array $defaults): void
    {
        $marker = 'business.seeded.' . str_replace('/', '.', $packageId);
        if ((bool) $this->settings->get($marker, false)) return;
        $values = $this->normalize($defaults);
        foreach ($values as $field => $value) {
            if ($value !== '' && trim((string) $this->settings->get('business.' . $field, '')) === '') {
                $this->settings->set('business.' . $field, $value);
            }
        }
        $this->settings->set($marker, true);
    }

    /** @return array<string,string> */
    private function normalize(array $input): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = str_replace("\r\n", "\n", trim((string) ($input[$field] ?? '')));
            $values[$field] = mb_substr($value, 0, in_array($field, ['hours', 'service_area'], true) ? 3000 : 500);
        }
        return $values;
    }

    /** @return list<string> */
    private function validate(array $values): array
    {
        $errors = [];
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Business email must be a valid email address.';
        }
        if ($values['website'] !== '' && (!filter_var($values['website'], FILTER_VALIDATE_URL)
            || !in_array(strtolower((string) parse_url($values['website'], PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $errors[] = 'Business website must be a valid HTTP or HTTPS URL.';
        }
        if ($values['phone'] !== '' && !preg_match('/^[+0-9().,; x-]{3,60}$/i', $values['phone'])) {
            $errors[] = 'Business phone contains unsupported characters.';
        }
        if ($values['service_area_radius_miles'] !== ''
            && (!is_numeric($values['service_area_radius_miles'])
                || (float) $values['service_area_radius_miles'] < 1
                || (float) $values['service_area_radius_miles'] > 500)) {
            $errors[] = 'Service-area radius must be between 1 and 500 miles.';
        }
        return $errors;
    }
}
