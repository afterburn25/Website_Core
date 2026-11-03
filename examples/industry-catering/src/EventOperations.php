<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

final class EventOperations
{
    public const EXTRA_PRICING = [
        'fixed' => 'per event',
        'per_guest' => 'per guest',
    ];

    public function evaluate(array $input, array $config, ?int $foodEstimateCents, ?\DateTimeImmutable $today = null, bool $strict = true): array
    {
        $today ??= new \DateTimeImmutable('today');
        $guests = $this->wholeNumber($input['guest_count'] ?? null);
        $eventDate = $this->date($input['event_date'] ?? null);
        $serviceStyle = is_scalar($input['service_style'] ?? null) ? trim((string) $input['service_style']) : '';
        $fulfillment = is_scalar($input['fulfillment_method'] ?? null) ? trim((string) $input['fulfillment_method']) : '';
        $window = is_scalar($input['fulfillment_window'] ?? null) ? trim((string) $input['fulfillment_window']) : '';
        $postalCode = is_scalar($input['postal_code'] ?? null) ? strtoupper(trim((string) $input['postal_code'])) : '';
        $selectedExtras = $input['extras'] ?? [];
        $errors = [];
        $notes = ['Submitting a request does not reserve capacity, a date, or a fulfillment window.'];
        $lines = [];

        $styles = [];
        foreach (is_array($config['styles'] ?? null) ? $config['styles'] : [] as $style) {
            if (is_array($style) && isset($style['name'])) $styles[(string) $style['name']] = $style;
        }
        $style = $serviceStyle !== '' ? ($styles[$serviceStyle] ?? null) : null;
        if ($serviceStyle !== '' && $style === null) $errors[] = 'Please choose a configured service style.';
        if ($guests !== null && $style !== null) {
            $minimum = max(1, (int) ($style['minimum_guests'] ?? 1));
            $maximum = max($minimum, (int) ($style['maximum_guests'] ?? 10000));
            if ($guests < $minimum || $guests > $maximum) {
                $errors[] = $serviceStyle . ' is available for ' . $minimum . '–' . $maximum . ' guests.';
            }
        }

        $leadDays = max(0, (int) ($config['lead_days'] ?? 0), (int) ($style['lead_days'] ?? 0));
        if ($eventDate !== null) {
            if ($eventDate < $today->modify('+' . $leadDays . ' days')) {
                $errors[] = 'This request needs at least ' . $leadDays . ' days of lead time.';
            }
            if (in_array($eventDate->format('Y-m-d'), is_array($config['blackout_dates'] ?? null) ? $config['blackout_dates'] : [], true)) {
                $errors[] = 'The selected date is unavailable for catering requests.';
            }
        }
        $capacity = max(1, (int) ($config['capacity_guests'] ?? 10000));
        if ($guests !== null && $guests > $capacity) {
            $errors[] = 'This request exceeds the configured event capacity of ' . $capacity . ' guests.';
        }

        $windows = is_array($config['windows'][$fulfillment] ?? null) ? $config['windows'][$fulfillment] : [];
        if ($strict && !in_array($fulfillment, ['pickup', 'delivery'], true)) {
            $errors[] = 'Please choose pickup or delivery.';
        } elseif ($fulfillment !== '' && ($window === '' || !in_array($window, $windows, true))) {
            $errors[] = 'Please choose an available ' . $fulfillment . ' window.';
        }

        $zoneSnapshot = null;
        if ($fulfillment === 'delivery') {
            if ($postalCode === '') {
                $errors[] = 'A delivery postal code is required.';
            } else {
                foreach (is_array($config['zones'] ?? null) ? $config['zones'] : [] as $zone) {
                    if (!is_array($zone) || (!in_array('*', $zone['postal_codes'] ?? [], true)
                        && !in_array($postalCode, $zone['postal_codes'] ?? [], true))) continue;
                    $zoneSnapshot = $zone;
                    break;
                }
                if ($zoneSnapshot === null) {
                    $errors[] = 'This address is currently outside our service area.';
                } else {
                    $fee = max(0, (int) ($zoneSnapshot['fee_cents'] ?? 0));
                    if ($fee > 0) $lines[] = ['key' => 'delivery', 'name' => $zoneSnapshot['name'] . ' delivery', 'quantity' => 1, 'unit_cents' => $fee, 'total_cents' => $fee];
                    $minimum = max(0, (int) ($zoneSnapshot['minimum_order_cents'] ?? 0));
                    if ($foodEstimateCents !== null && $foodEstimateCents < $minimum) {
                        $errors[] = 'Delivery to ' . $zoneSnapshot['name'] . ' requires at least ' . self::money($minimum) . ' in food.';
                    }
                }
            }
        }

        if (!is_array($selectedExtras) || count($selectedExtras) > 20) {
            $errors[] = 'Invalid optional service selection.';
            $selectedExtras = [];
        }
        $extraCatalog = [];
        foreach (is_array($config['extras'] ?? null) ? $config['extras'] : [] as $extra) {
            if (is_array($extra) && isset($extra['key'])) $extraCatalog[(string) $extra['key']] = $extra;
        }
        $seen = [];
        foreach ($selectedExtras as $key) {
            if (!is_scalar($key)) { $errors[] = 'Invalid optional service selection.'; continue; }
            $key = (string) $key;
            if (isset($seen[$key]) || !isset($extraCatalog[$key])) { $errors[] = 'Invalid optional service selection.'; continue; }
            $seen[$key] = true;
            $extra = $extraCatalog[$key];
            $basis = (string) ($extra['pricing_basis'] ?? 'fixed');
            $unit = max(0, (int) ($extra['price_cents'] ?? 0));
            $quantity = $basis === 'per_guest' ? $guests : 1;
            $total = $quantity === null ? null : $unit * $quantity;
            $lines[] = ['key' => $key, 'name' => (string) $extra['name'], 'quantity' => $quantity, 'unit_cents' => $unit, 'total_cents' => $total];
        }

        $operationsCents = 0;
        foreach ($lines as $line) {
            if ($line['total_cents'] === null) { $operationsCents = null; break; }
            $operationsCents += (int) $line['total_cents'];
        }
        $combined = $foodEstimateCents !== null && $operationsCents !== null ? $foodEstimateCents + $operationsCents : null;
        return [
            'errors' => array_values(array_unique($errors)),
            'notes' => $notes,
            'service_style' => $style === null ? null : [
                'name' => (string) $style['name'],
                'minimum_guests' => (int) $style['minimum_guests'],
                'maximum_guests' => (int) $style['maximum_guests'],
                'lead_days' => (int) $style['lead_days'],
            ],
            'fulfillment_method' => $fulfillment,
            'fulfillment_window' => $window,
            'postal_code' => $postalCode,
            'delivery_zone' => $zoneSnapshot === null ? null : [
                'name' => (string) $zoneSnapshot['name'],
                'fee_cents' => (int) $zoneSnapshot['fee_cents'],
                'minimum_order_cents' => (int) $zoneSnapshot['minimum_order_cents'],
            ],
            'lines' => $lines,
            'operations_cents' => $operationsCents,
            'combined_estimate_cents' => $combined,
        ];
    }

    private function wholeNumber(mixed $value): ?int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,4}$/D', (string) $value)) return null;
        return (int) $value;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!is_scalar($value)) return null;
        $value = (string) $value;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
