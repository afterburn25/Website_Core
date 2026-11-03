<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Database\Connection;
use Core\Leads\QuoteService;

final class EventWorkspace
{
    public const STATES = ['tentative', 'confirmed', 'completed', 'cancelled'];
    public const TASK_STATUSES = ['open', 'in_progress', 'done', 'cancelled'];
    public const STAFF_STATUSES = ['needed', 'assigned', 'confirmed', 'completed', 'cancelled'];
    public const EQUIPMENT_STATUSES = ['needed', 'pulled', 'packed', 'returned', 'damaged'];
    public const TASTING_STATUSES = ['requested', 'reviewing', 'scheduled', 'completed', 'declined', 'cancelled'];
    public const COST_CATEGORIES = ['food', 'labor', 'rental', 'travel', 'other'];

    public function __construct(private Connection $db) {}

    public function synchronizeAcceptedQuotes(QuoteService $quotes, string $packageId): int
    {
        $created = 0;
        foreach ($quotes->all($packageId) as $quote) {
            if (($quote['status'] ?? '') !== 'accepted' || empty($quote['inquiry_id'])) continue;
            if ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('catering_events') . '` WHERE core_quote_id=?', [(int) $quote['id']]) > 0) continue;
            $inquiry = $this->db->fetch('SELECT * FROM `' . $this->db->table('catering_inquiries') . '` WHERE core_inquiry_id=? AND kind=?', [(int) $quote['inquiry_id'], 'quote']);
            if ($inquiry !== null && $this->createFromInquiry($inquiry, (int) $quote['id']) !== null) $created++;
        }
        return $created;
    }

    public function createFromInquiryId(int $inquiryId, ?int $coreQuoteId = null): ?int
    {
        $inquiry = $this->db->fetch('SELECT * FROM `' . $this->db->table('catering_inquiries') . '` WHERE id=? AND kind=?', [$inquiryId, 'quote']);
        return $inquiry === null ? null : $this->createFromInquiry($inquiry, $coreQuoteId);
    }

    public function createFromInquiry(array $inquiry, ?int $coreQuoteId = null): ?int
    {
        $existing = $this->db->fetchColumn('SELECT id FROM `' . $this->db->table('catering_events') . '` WHERE inquiry_id=?', [(int) $inquiry['id']]);
        if ($existing !== false) {
            if ($coreQuoteId !== null) {
                $this->db->update('catering_events', [
                    'core_quote_id'=>$coreQuoteId, 'state'=>'confirmed', 'updated_at'=>date('Y-m-d H:i:s'),
                ], 'id=? AND core_quote_id IS NULL', [(int)$existing]);
            }
            return (int) $existing;
        }
        $title = trim((string) ($inquiry['event_type'] ?? ''));
        $title = ($title !== '' ? $title : 'Catering event') . ' — ' . (string) ($inquiry['name'] ?? 'Customer');
        $now = date('Y-m-d H:i:s');
        return (int) $this->db->insert('catering_events', [
            'inquiry_id' => (int) $inquiry['id'], 'core_quote_id' => $coreQuoteId,
            'state' => $coreQuoteId === null ? 'tentative' : 'confirmed', 'title' => mb_substr($title, 0, 190),
            'event_date' => $inquiry['event_date'] ?: null, 'event_time' => $inquiry['event_time'] ?: null,
            'customer_name' => (string) $inquiry['name'], 'customer_email' => (string) $inquiry['email'],
            'customer_phone' => $inquiry['phone'] ?: null, 'guest_count' => $inquiry['guest_count'],
            'final_guest_count' => $inquiry['guest_count'], 'final_headcount_due' => null,
            'venue' => $inquiry['venue'] ?: null, 'address_line_1' => $inquiry['address_line_1'] ?? null,
            'address_line_2' => $inquiry['address_line_2'] ?? null, 'city' => $inquiry['city'] ?? null,
            'region' => $inquiry['region'] ?? null, 'postal_code' => $inquiry['postal_code'] ?: null,
            'fulfillment_method' => $inquiry['fulfillment_method'] ?: null,
            'fulfillment_window' => $inquiry['fulfillment_window'] ?: null,
            'menu_plan' => $inquiry['menu_plan'] ?: null, 'operations_plan' => $inquiry['operations_plan'] ?: null,
            'run_of_show' => null, 'internal_notes' => null, 'delivery_status' => 'unassigned',
            'driver_name' => null, 'route_order' => null, 'setup_instructions' => null,
            'proof_reference' => null, 'revision' => 1, 'created_at' => $now, 'updated_at' => null,
        ]);
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT e.*, (SELECT COUNT(*) FROM `' . $this->db->table('catering_event_tasks') . '` t WHERE t.event_id=e.id AND t.status NOT IN (?,?)) AS open_tasks FROM `' . $this->db->table('catering_events') . '` e ORDER BY COALESCE(event_date, ?), id', ['done', 'cancelled', '9999-12-31']);
    }

    public function availableInquiries(): array
    {
        return $this->db->fetchAll('SELECT i.id,i.name,i.event_type,i.event_date,i.guest_count,i.core_inquiry_id FROM `' . $this->db->table('catering_inquiries') . '` i LEFT JOIN `' . $this->db->table('catering_events') . '` e ON e.inquiry_id=i.id WHERE i.kind=? AND e.id IS NULL ORDER BY i.created_at DESC LIMIT 100', ['quote']);
    }

    public function find(int $id): ?array
    {
        $event = $this->db->fetch('SELECT * FROM `' . $this->db->table('catering_events') . '` WHERE id=?', [$id]);
        if ($event === null) return null;
        $event['tasks'] = $this->db->fetchAll('SELECT * FROM `' . $this->db->table('catering_event_tasks') . '` WHERE event_id=? ORDER BY sort_order,due_date,id', [$id]);
        $event['staff'] = $this->db->fetchAll('SELECT * FROM `' . $this->db->table('catering_event_staff') . '` WHERE event_id=? ORDER BY starts_at,id', [$id]);
        $event['equipment'] = $this->db->fetchAll('SELECT * FROM `' . $this->db->table('catering_event_equipment') . '` WHERE event_id=? ORDER BY item_name,id', [$id]);
        $event['costs'] = $this->db->fetchAll('SELECT * FROM `' . $this->db->table('catering_event_costs') . '` WHERE event_id=? ORDER BY category,description,id', [$id]);
        $event['revisions'] = $this->db->fetchAll('SELECT * FROM `' . $this->db->table('catering_event_revisions') . '` WHERE event_id=? ORDER BY revision DESC,id DESC', [$id]);
        $event['menu'] = json_decode((string) ($event['menu_plan'] ?? ''), true) ?: [];
        $event['operations'] = json_decode((string) ($event['operations_plan'] ?? ''), true) ?: [];
        return $event;
    }

    public function productionPlan(array $event, ?array $acceptedQuote = null): array
    {
        $guests = max(1, (int) ($event['final_guest_count'] ?: $event['guest_count'] ?: 1));
        $items = [];
        $ingredientTotals = [];
        $foodCost = 0;
        $prepGroups = [];
        foreach (($event['menu']['items'] ?? []) as $snapshot) {
            $itemId = (int) ($snapshot['id'] ?? 0);
            $recipes = $itemId > 0 ? $this->db->fetchAll('SELECT r.*,i.name ingredient_name,i.unit,i.cost_minor_per_unit,i.allergens FROM `' . $this->db->table('catering_recipe_ingredients') . '` r INNER JOIN `' . $this->db->table('catering_ingredients') . '` i ON i.id=r.ingredient_id WHERE r.menu_item_id=? AND i.active=1 ORDER BY i.name', [$itemId]) : [];
            $cost = 0;
            foreach ($recipes as $recipe) {
                $quantity = (float) $recipe['quantity_per_guest'] * $guests * (1 + ((float) $recipe['waste_percent'] / 100));
                $lineCost = (int) round($quantity * (int) $recipe['cost_minor_per_unit']);
                $cost += $lineCost; $foodCost += $lineCost;
                $key = (int) $recipe['ingredient_id'];
                if (!isset($ingredientTotals[$key])) $ingredientTotals[$key] = ['name'=>$recipe['ingredient_name'],'unit'=>$recipe['unit'],'quantity'=>0.0,'cost_minor'=>0,'allergens'=>$recipe['allergens']];
                $ingredientTotals[$key]['quantity'] += $quantity;
                $ingredientTotals[$key]['cost_minor'] += $lineCost;
                $station = trim((string) ($recipe['station'] ?? '')) ?: 'Kitchen';
                $prepDate = (string) ($event['event_date'] ?? '');
                if ($prepDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $prepDate)) {
                    $prepDate = (new \DateTimeImmutable($prepDate))->modify('-' . max(0, (int) ($recipe['prep_lead_days'] ?? 0)) . ' days')->format('Y-m-d');
                } else $prepDate = 'Date pending';
                $prepGroups[$prepDate][$station][] = [
                    'dish'=>(string)($snapshot['name'] ?? 'Menu item'), 'ingredient'=>(string)$recipe['ingredient_name'],
                    'quantity'=>$quantity, 'unit'=>(string)$recipe['unit'], 'notes'=>(string)($recipe['prep_notes'] ?? ''),
                    'label_notes'=>(string)($recipe['label_notes'] ?? ''),
                ];
            }
            $items[] = $snapshot + ['production_guests'=>$guests,'recipe'=>$recipes,'estimated_cost_minor'=>$cost];
        }
        $laborCost = 0;
        foreach ($event['staff'] ?? [] as $staff) {
            $start = strtotime((string) ($staff['starts_at'] ?? ''));
            $end = strtotime((string) ($staff['ends_at'] ?? ''));
            if ($start && $end && $end > $start) $laborCost += (int) round((($end - $start) / 3600) * (int) $staff['hourly_cost_minor']);
        }
        $equipmentCost = array_sum(array_map(static fn(array $row): int => max(0, (int)$row['quantity'] - (int)$row['returned_quantity']) * (int)$row['replacement_cost_minor'], $event['equipment'] ?? []));
        ksort($prepGroups);
        $estimatedCost = $foodCost + $laborCost;
        $recordedEstimated = 0; $recordedActual = 0; $actualComplete = ($event['costs'] ?? []) !== [];
        foreach ($event['costs'] ?? [] as $cost) {
            $recordedEstimated += max(0, (int) $cost['estimated_minor']);
            if ($cost['actual_minor'] === null) $actualComplete = false;
            else $recordedActual += max(0, (int) $cost['actual_minor']);
        }
        if ($recordedEstimated > 0) $estimatedCost = $recordedEstimated;
        $revenue = $acceptedQuote !== null && ($acceptedQuote['status'] ?? '') === 'accepted'
            ? max(0, (int) ($acceptedQuote['total_minor'] ?? 0)) : null;
        return [
            'guests'=>$guests, 'items'=>$items, 'ingredients'=>array_values($ingredientTotals), 'prep_groups'=>$prepGroups,
            'food_cost_minor'=>$foodCost, 'labor_cost_minor'=>$laborCost, 'equipment_exposure_minor'=>$equipmentCost,
            'estimated_cost_minor'=>$estimatedCost, 'actual_cost_minor'=>$actualComplete ? $recordedActual : null,
            'revenue_minor'=>$revenue, 'estimated_margin_minor'=>$revenue === null ? null : $revenue - $estimatedCost,
            'actual_margin_minor'=>$revenue === null || !$actualComplete ? null : $revenue - $recordedActual,
        ];
    }

    public function purchasingPlan(array $events): array
    {
        $totals = [];
        foreach ($events as $event) {
            if (in_array((string)($event['state'] ?? ''), ['cancelled', 'completed'], true)) continue;
            $full = $this->find((int)$event['id']);
            if ($full === null) continue;
            foreach ($this->productionPlan($full)['ingredients'] as $ingredient) {
                $key = $ingredient['name'] . '|' . $ingredient['unit'];
                if (!isset($totals[$key])) $totals[$key] = $ingredient + ['events'=>0];
                else {
                    $totals[$key]['quantity'] += $ingredient['quantity'];
                    $totals[$key]['cost_minor'] += $ingredient['cost_minor'];
                }
                $totals[$key]['events']++;
            }
        }
        usort($totals, static fn(array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));
        return array_values($totals);
    }
}
