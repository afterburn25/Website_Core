<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Commerce\ProductRepository;
use Core\Database\Connection;
use Core\Modules\ModuleContext;
use Core\Settings\SettingsRepository;

/** Maps explicitly eligible Catering packages into Core commerce and validates checkout. */
final class CommerceIntegration
{
    public const MODES = ['quote_only', 'direct_order', 'mixed'];

    public function __construct(
        private ModuleContext $ctx,
        private Connection $db,
        private SettingsRepository $settings,
    ) {}

    public function mode(): string
    {
        $mode = (string) $this->settings->get('catering.ordering_mode', 'quote_only');
        return in_array($mode, self::MODES, true) ? $mode : 'quote_only';
    }

    public function commerceEnabled(): bool
    {
        return in_array($this->mode(), ['direct_order', 'mixed'], true);
    }

    /** Synchronize only module-managed CATERING-PKG-* products. */
    public function syncProducts(): void
    {
        $eligible = array_fill_keys($this->eligiblePackageIds(), true);
        $products = $this->ctx->service(ProductRepository::class);
        $existing = [];
        foreach ($products->all($this->ctx->packageId()) as $product) {
            if (preg_match('/^CATERING-PKG-([0-9]+)$/D', (string) ($product['sku'] ?? ''), $match)) {
                $existing[(int) $match[1]] = $product;
            }
        }
        if (!$this->commerceEnabled() && $existing === []) {
            return;
        }
        $packages = $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('catering_packages') . '` ORDER BY sort_order,id'
        );
        foreach ($packages as $package) {
            $id = (int) $package['id'];
            $active = $this->commerceEnabled() && isset($eligible[$id])
                && (int) $package['visible'] === 1
                && (int) $package['price_cents'] > 0
                && str_contains(mb_strtolower((string) $package['price_note']), 'per guest');
            if (!$active && !isset($existing[$id])) {
                continue;
            }
            [$productId, $error] = $products->save([
                'name' => (string) $package['name'] . ' — per guest',
                'sku' => 'CATERING-PKG-' . $id,
                'slug' => 'catering-' . (string) $package['slug'],
                'description' => trim((string) $package['description']) . ' Guest quantity is the event headcount. Date, fulfillment, and service-area eligibility are verified at checkout.',
                'price' => number_format(((int) $package['price_cents']) / 100, 2, '.', ''),
                'currency' => 'USD',
                'image_url' => '',
                'track_inventory' => false,
                'active' => $active,
                'featured' => (int) $package['featured'] === 1,
                'sort_order' => (int) $package['sort_order'],
            ], $this->ctx->packageId(), isset($existing[$id]) ? (int) $existing[$id]['id'] : null);
            if ($productId === null || $error !== null) {
                throw new \RuntimeException($error ?? 'A Catering commerce product could not be synchronized.');
            }
        }
        foreach ($existing as $packageId => $product) {
            if (!(bool) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $this->db->table('catering_packages') . '` WHERE id=?',
                [$packageId],
            )) {
                $products->setActive((int) $product['id'], $this->ctx->packageId(), false);
            }
        }
    }

    /** Core checkout callback. Quantity is the guest count for one eligible package. */
    public function validate(array $context): array
    {
        if (!$this->commerceEnabled()) {
            return ['outcome' => 'quote_only', 'message' => 'Online ordering is not enabled. Request a catering quote instead.'];
        }
        $lines = is_array($context['cart']['items'] ?? null) ? $context['cart']['items'] : [];
        if (count($lines) !== 1) {
            return ['outcome' => 'quote_only', 'message' => 'Direct catering orders must contain one eligible package. Request a quote for a custom combination.'];
        }
        $line = $lines[0];
        if (!preg_match('/^CATERING-PKG-([0-9]+)$/D', (string) ($line['sku'] ?? ''), $match)) {
            return ['outcome' => 'quote_only', 'message' => 'This selection requires a catering quote.'];
        }
        $packageId = (int) $match[1];
        if (!in_array($packageId, $this->eligiblePackageIds(), true)) {
            return ['outcome' => 'quote_only', 'message' => 'This package is not currently eligible for direct ordering.'];
        }
        $package = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('catering_packages') . '` WHERE id=? AND visible=1',
            [$packageId],
        );
        if (!$package || (int) $package['price_cents'] < 1
            || !str_contains(mb_strtolower((string) $package['price_note']), 'per guest')) {
            return ['outcome' => 'quote_only', 'message' => 'This package requires manual pricing and a catering quote.'];
        }

        $errors = [];
        $guests = max(0, (int) ($line['quantity'] ?? 0));
        $minimum = max(1, (int) $package['guest_min']);
        $maximum = !empty($package['guest_max']) ? (int) $package['guest_max'] : 10000;
        if ($guests < $minimum || $guests > $maximum) {
            $errors['_form'] = (string) $package['name'] . ' is available for ' . $minimum . '–' . $maximum . ' guests.';
        }
        $capacity = max(1, min(10000, (int) $this->settings->get('catering.capacity_guests', 10000)));
        if ($guests > $capacity) $errors['_form'] = 'This order exceeds the configured event capacity of ' . $capacity . ' guests.';

        $requested = trim((string) ($context['requested_date'] ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $requested);
        if ($date === false || $date->format('Y-m-d') !== $requested) {
            $errors['requested_date'] = 'Choose a valid catering date.';
        } else {
            $leadDays = $this->directLeadDays();
            if ($date < (new \DateTimeImmutable('today'))->modify('+' . $leadDays . ' days')) {
                $errors['requested_date'] = 'Direct orders require at least ' . $leadDays . ' days of lead time.';
            }
            $blackouts = $this->settings->get('catering.blackout_dates', []);
            if (is_array($blackouts) && in_array($requested, $blackouts, true)) {
                $errors['requested_date'] = 'The selected date is unavailable for catering orders.';
            }
            if ((!empty($package['available_from']) && $requested < (string) $package['available_from'])
                || (!empty($package['available_until']) && $requested > (string) $package['available_until'])) {
                $errors['requested_date'] = 'This package is not available on the selected date.';
            }
        }
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', (string) ($context['requested_time'] ?? ''))) {
            $errors['requested_time'] = 'Choose a valid requested catering time.';
        }

        $fulfillment = (string) ($context['fulfillment'] ?? '');
        if (!in_array($fulfillment, ['pickup', 'delivery'], true)) {
            $errors['fulfillment'] = 'Choose pickup or delivery.';
        }
        $zoneName = null;
        $radiusStatus = 'not_required';
        if ($fulfillment === 'delivery') {
            $address = is_array($context['address'] ?? null) ? $context['address'] : [];
            foreach (['line1' => 'address_line1', 'city' => 'city', 'region' => 'region', 'postal_code' => 'postal_code'] as $key => $field) {
                if (trim((string) ($address[$key] ?? '')) === '') $errors[$field] = 'Complete the delivery address.';
            }
            $zone = $this->deliveryZone((string) ($address['postal_code'] ?? ''));
            if ($zone === null) {
                $errors['postal_code'] = 'This address is currently outside our service area.';
            } else {
                $zoneName = (string) ($zone['name'] ?? 'Delivery area');
                $subtotal = max(0, (int) ($context['cart']['subtotal_minor'] ?? 0));
                if ($subtotal < (int) ($zone['minimum_order_cents'] ?? 0)) {
                    $errors['_form'] = 'Delivery to ' . $zoneName . ' requires at least $'
                        . number_format(((int) $zone['minimum_order_cents']) / 100, 2) . ' in food.';
                }
                if ((int) ($zone['fee_cents'] ?? 0) > 0) {
                    return ['outcome' => 'quote_only', 'message' => 'This delivery area has a variable delivery fee and requires a catering quote.'];
                }
            }
            if ($errors === []) {
                $radius = $this->ctx->checkServiceAddress($address);
                $radiusStatus = (string) ($radius['status'] ?? 'unknown');
                if ($radiusStatus === 'outside') {
                    $errors['address_line1'] = 'This address is currently outside our service area.';
                } elseif ($radiusStatus === 'unknown') {
                    return [
                        'outcome' => 'manual_review',
                        'message' => (string) ($radius['message'] ?? 'This delivery address requires staff review.'),
                        'snapshot' => ['package_id' => $packageId, 'guest_count' => $guests, 'radius_status' => 'unknown'],
                    ];
                }
            }
        }
        if ($errors !== []) return ['outcome' => 'errors', 'errors' => $errors];

        return [
            'outcome' => 'ok',
            'snapshot' => [
                'package_id' => $packageId,
                'package_slug' => (string) $package['slug'],
                'guest_count' => $guests,
                'requested_date' => $requested,
                'fulfillment' => $fulfillment,
                'delivery_zone' => $zoneName,
                'radius_status' => $radiusStatus,
                'final_revalidation' => !empty($context['final']),
            ],
        ];
    }

    /** @return list<int> */
    public function eligiblePackageIds(): array
    {
        $ids = $this->settings->get('catering.direct_order_package_ids', []);
        if (!is_array($ids)) return [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        sort($ids);
        return $ids;
    }

    private function directLeadDays(): int
    {
        $lead = max(0, min(3650, (int) $this->settings->get('catering.lead_time', 14)));
        $rules = $this->settings->get('catering.service_style_rules', []);
        if (!is_array($rules)) return $lead;
        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;
            $name = mb_strtolower((string) ($rule['name'] ?? ''));
            if (str_contains($name, 'drop-off') || str_contains($name, 'pickup')) {
                $lead = max($lead, (int) ($rule['lead_days'] ?? 0));
            }
        }
        return $lead;
    }

    private function deliveryZone(string $postalCode): ?array
    {
        $postalCode = strtoupper(trim($postalCode));
        if ($postalCode === '') return null;
        $zones = $this->settings->get('catering.delivery_zones', []);
        if (!is_array($zones)) return null;
        foreach ($zones as $zone) {
            if (!is_array($zone)) continue;
            $postals = is_array($zone['postal_codes'] ?? null) ? $zone['postal_codes'] : [];
            if (in_array('*', $postals, true) || in_array($postalCode, $postals, true)) return $zone;
        }
        return null;
    }
}
