<?php
declare(strict_types=1);

namespace Core\Commerce;

use Core\Settings\SettingsRepository;

final class PaymentMethods
{
    public const CATALOG = [
        'venmo' => ['label' => 'Venmo', 'placeholder' => '@your-business'],
        'paypal' => ['label' => 'PayPal', 'placeholder' => 'paypal.me/your-business or account email'],
        'cashapp' => ['label' => 'Cash App', 'placeholder' => '$YourBusiness'],
    ];

    public function __construct(private SettingsRepository $settings) {}

    public function configured(): array
    {
        $saved = $this->settings->get('commerce.payment_methods', []);
        if (!is_array($saved)) $saved = [];
        $out = [];
        foreach (self::CATALOG as $key => $meta) {
            $row = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            if (!empty($row['enabled'])) {
                $out[$key] = $meta + ['instructions' => trim((string) ($row['instructions'] ?? ''))];
            }
        }
        return $out;
    }

    public function save(array $input): void
    {
        $out = [];
        foreach (self::CATALOG as $key => $meta) {
            $row = is_array($input[$key] ?? null) ? $input[$key] : [];
            $out[$key] = [
                'enabled' => !empty($row['enabled']),
                'instructions' => mb_substr(trim(strip_tags((string) ($row['instructions'] ?? ''))), 0, 500),
            ];
        }
        $this->settings->set('commerce.payment_methods', $out);
    }
}
