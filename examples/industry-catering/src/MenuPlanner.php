<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

/** Catering selection rules and preliminary food pricing; never an order or invoice. */
final class MenuPlanner
{
    public const DIETARY = [
        'vegetarian' => 'Vegetarian', 'vegan' => 'Vegan',
        'gluten-free' => 'Gluten-free', 'dairy-free' => 'Dairy-free',
    ];
    public const ALLERGENS = [
        'milk' => 'Milk', 'eggs' => 'Eggs', 'fish' => 'Fish',
        'shellfish' => 'Crustacean shellfish', 'tree-nuts' => 'Tree nuts',
        'peanuts' => 'Peanuts', 'wheat' => 'Wheat', 'soy' => 'Soy', 'sesame' => 'Sesame',
    ];
    public const PRICING = [
        'per_guest' => 'Per guest', 'per_tray' => 'Per tray',
        'fixed' => 'Fixed event price', 'manual' => 'Team confirmation required',
    ];

    /** @return list<string> */
    public static function tags(mixed $value, array $allowed): array
    {
        $tags = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($tags)) return [];
        return array_values(array_unique(array_filter($tags,
            static fn ($tag): bool => is_string($tag) && array_key_exists($tag, $allowed))));
    }

    public static function cents(string $value): int
    {
        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $value)) {
            throw new \InvalidArgumentException('Enter a price from 0 to 1,000,000 with at most two decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($cents > 100_000_000) throw new \InvalidArgumentException('The maximum unit price is 1,000,000.');
        return $cents;
    }

    /** All input prices, labels and totals are ignored: the current catalog is authoritative. */
    public function calculate(array $input, array $items, array $packages, int $minimumGuests = 1): array
    {
        $errors = [];
        $guests = $this->positiveInt($input['guest_count'] ?? null);
        if ($guests === null || $guests < $minimumGuests || $guests > 10000) {
            $errors[] = 'Guest count must be between ' . $minimumGuests . ' and 10,000.';
        }
        $packageId = $input['package_id'] ?? '';
        $package = null;
        if ($packageId !== '' && $packageId !== '0' && $packageId !== 0 && $packageId !== null) {
            $id = $this->positiveInt($packageId);
            foreach ($packages as $candidate) {
                if ($id !== null && (int) $candidate['id'] === $id && !empty($candidate['visible'])) $package = $candidate;
            }
            if ($package === null) $errors[] = 'The selected package is no longer available. Please choose again.';
        }
        $catalog = [];
        foreach ($items as $item) {
            if (!empty($item['visible'])) $catalog[(int) $item['id']] = $item;
        }
        $ids = $input['menu_items'] ?? [];
        if (!is_array($ids) || count($ids) > 100) {
            $errors[] = 'Choose no more than 100 menu items.';
            $ids = [];
        }
        $selected = [];
        foreach ($ids as $rawId) {
            $id = $this->positiveInt($rawId);
            if ($id === null || !isset($catalog[$id])) {
                $errors[] = 'A selected dish is no longer available. Please review your menu.';
            } elseif (isset($selected[$id])) {
                $errors[] = 'The same dish cannot be selected twice.';
            } else {
                $selected[$id] = $catalog[$id];
            }
        }
        $rules = [];
        if ($package !== null) {
            if ($guests !== null && ($guests < (int) $package['guest_min'] ||
                (!empty($package['guest_max']) && $guests > (int) $package['guest_max']))) {
                $errors[] = 'Guest count is outside this package’s supported range.';
            }
            if (!empty($package['menu_group_id'])) {
                $groupIds = json_decode((string) ($package['menu_group_item_ids'] ?? '[]'), true);
                $allowed = [];
                foreach (is_array($groupIds) ? $groupIds : [] as $groupId) {
                    $groupId = $this->positiveInt($groupId);
                    if ($groupId !== null) $allowed[$groupId] = true;
                }
                foreach ($selected as $id => $_item) {
                    if (!isset($allowed[$id])) {
                        $errors[] = 'A selected dish is outside this package’s menu collection.';
                    }
                }
            }
            $rules = json_decode((string) ($package['choice_rules'] ?? ''), true) ?: [];
            if ($rules !== []) {
                $counts = [];
                foreach ($selected as $item) {
                    $category = (string) $item['category_id'];
                    if (!isset($rules[$category])) $errors[] = 'A selected dish is outside this package’s available categories.';
                    $counts[$category] = ($counts[$category] ?? 0) + 1;
                }
                foreach ($rules as $category => $rule) {
                    $count = $counts[$category] ?? 0;
                    if ($count < (int) $rule['min'] || $count > (int) $rule['max']) {
                        $errors[] = 'For ' . $rule['label'] . ', choose ' . (int) $rule['min'] . '–' . (int) $rule['max'] . ' dishes.';
                    }
                }
            }
        }
        $lines = [];
        $subtotal = 0;
        $complete = $package !== null || $selected !== [];
        $notes = [];
        if ($package !== null) {
            $line = $this->price($package, $guests ?? 1);
            $lines[] = ['kind' => 'package', 'id' => (int) $package['id'], 'name' => $package['name']] + $line;
            $subtotal += $line['total_cents'] ?? 0;
            if ($line['total_cents'] === null) $complete = false;
            if ($selected !== [] && $rules === []) {
                $complete = false;
                $notes[] = 'This package has no configured dish allowances. The team must confirm the selected menu and price.';
            }
        }
        $selectionSnapshot = [];
        foreach ($selected as $item) {
            $selectionSnapshot[] = [
                'id' => (int) $item['id'], 'name' => $item['name'],
                'category_id' => (int) $item['category_id'],
                'dietary' => (string) ($item['dietary'] ?? ''),
                'dietary_tags' => self::tags($item['dietary_tags'] ?? null, self::DIETARY),
                'allergens' => self::tags($item['allergens'] ?? null, self::ALLERGENS),
                'allergen_notes' => (string) ($item['allergen_notes'] ?? ''),
            ];
            if ($package === null) {
                $line = $this->price($item, $guests ?? 1);
                $lines[] = ['kind' => 'dish', 'id' => (int) $item['id'], 'name' => $item['name']] + $line;
                $subtotal += $line['total_cents'] ?? 0;
                if ($line['total_cents'] === null) $complete = false;
            }
        }
        if (!$complete) $notes[] = 'A complete food estimate needs confirmation from the catering team.';
        $notes[] = 'Quantities assume every selected dish serves the full headcount. Taxes, staffing, rentals, delivery and service charges are excluded.';
        $notes[] = 'Dietary labels and listed allergens do not guarantee absence of other allergens or cross-contact. Discuss allergies with the team.';
        return [
            'schema' => 1, 'guest_count' => $guests, 'errors' => array_values(array_unique($errors)),
            'package_id' => $package !== null ? (int) $package['id'] : null,
            'package_name' => $package['name'] ?? null, 'items' => $selectionSnapshot,
            'lines' => $lines, 'known_subtotal_cents' => $subtotal,
            'estimate_cents' => $complete && $errors === [] ? $subtotal : null,
            'notes' => $notes,
        ];
    }

    private function price(array $record, int $guests): array
    {
        $basis = $record['pricing_basis'] ?? 'manual';
        $minimum = max(1, (int) ($record['minimum_quantity'] ?? 1));
        $servings = max(1, (int) ($record['servings_per_tray'] ?? 1));
        $quantity = match ($basis) {
            'per_guest' => max($minimum, $guests),
            'per_tray' => max($minimum, intdiv($guests + $servings - 1, $servings)),
            'fixed' => 1,
            default => null,
        };
        $originalPrice = max(0, (int) $record['price_cents']);
        $discount = 0;
        $threshold = 0;
        foreach (json_decode((string) ($record['guest_tiers'] ?? ''), true) ?: [] as $tier) {
            if ($guests >= (int) $tier['minimum_guests'] && (int) $tier['minimum_guests'] >= $threshold) {
                $threshold = (int) $tier['minimum_guests'];
                $discount = max(0, min(10000, (int) $tier['discount_bps']));
            }
        }
        $price = intdiv($originalPrice * (10000 - $discount) + 5000, 10000);
        return ['pricing_basis' => $basis, 'quantity' => $quantity,
            'original_unit_cents' => $originalPrice, 'discount_bps' => $discount,
            'unit_cents' => $price, 'total_cents' => $quantity === null ? null : $quantity * $price];
    }

    private function positiveInt(mixed $value): ?int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,8}$/D', (string) $value)) return null;
        return (int) $value;
    }
}
