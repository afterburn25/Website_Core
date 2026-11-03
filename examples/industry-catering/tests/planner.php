<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/MenuPlanner.php';
require_once dirname(__DIR__) . '/src/EventOperations.php';
use CajunWebPro\Catering\MenuPlanner;
use CajunWebPro\Catering\EventOperations;
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$planner = new MenuPlanner();
$items = [
    ['id' => 1, 'category_id' => 1, 'name' => 'Starter', 'visible' => 1, 'price_cents' => 825, 'pricing_basis' => 'per_guest', 'minimum_quantity' => 1, 'dietary_tags' => '["vegetarian"]', 'allergens' => '["milk"]'],
    ['id' => 2, 'category_id' => 2, 'name' => 'Tray', 'visible' => 1, 'price_cents' => 12500, 'pricing_basis' => 'per_tray', 'servings_per_tray' => 12, 'minimum_quantity' => 1],
    ['id' => 3, 'category_id' => 2, 'name' => 'Event dish', 'visible' => 1, 'price_cents' => 15000, 'pricing_basis' => 'fixed'],
    ['id' => 4, 'category_id' => 2, 'name' => 'Custom', 'visible' => 1, 'price_cents' => 8000, 'pricing_basis' => 'manual'],
    ['id' => 5, 'category_id' => 2, 'name' => 'Hidden', 'visible' => 0, 'price_cents' => 1, 'pricing_basis' => 'per_guest'],
];
$package = ['id' => 10, 'name' => 'Event package', 'visible' => 1, 'price_cents' => 3900, 'pricing_basis' => 'per_guest', 'guest_min' => 20, 'guest_max' => 100,
    'choice_rules' => json_encode(['1' => ['label' => 'Starters', 'min' => 1, 'max' => 1], '2' => ['label' => 'Mains', 'min' => 1, 'max' => 2]])];
$plan = $planner->calculate(['guest_count' => '25', 'menu_items' => ['1', '2', '3']], $items, []);
check($plan['errors'] === [], 'Valid custom menu is accepted');
check($plan['estimate_cents'] === 73125, 'Per guest, rounded-up trays and fixed event prices use exact cents');
check($plan['lines'][1]['quantity'] === 3, '25 guests need three trays of twelve');
check($plan['items'][0]['allergens'] === ['milk'], 'Allergen snapshot comes from catalog');
$items[1]['minimum_quantity'] = 5;
$plan = $planner->calculate(['guest_count' => 25, 'menu_items' => ['2']], $items, []);
check($plan['estimate_cents'] === 62500 && $plan['lines'][0]['quantity'] === 5, 'Minimum tray quantity is enforced');
$items[0]['minimum_quantity'] = 30;
$plan = $planner->calculate(['guest_count' => '25', 'menu_items' => ['1']], $items, []);
check($plan['estimate_cents'] === 24750, 'Minimum billed guests is enforced');
$input = ['guest_count' => '25', 'package_id' => '10', 'menu_items' => ['1', '2'], 'estimate_cents' => '1', 'price_cents' => '1'];
$plan = $planner->calculate($input, $items, [$package]);
check($plan['estimate_cents'] === 97500 && count($plan['lines']) === 1, 'Package includes its chosen dishes without double-charging');
check($plan['items'][0]['name'] === 'Starter', 'Names are read from catalog');
$plan = $planner->calculate($input + ['ignored' => 'value'], $items, [$package]);
check($plan['estimate_cents'] === 97500, 'Client totals cannot change the server estimate');
foreach (['0', '-1', '1.5', '2e2', '10001', ['25'], '25oops'] as $guests) {
    check($planner->calculate(['guest_count' => $guests], $items, [])['errors'] !== [], 'Malformed guest count rejected');
}
check($planner->calculate(['guest_count' => '10'], $items, [], 20)['errors'] !== [], 'Business minimum guest count enforced');
foreach ([['5'], ['999'], ['1', '1'], [['1']], '1', array_fill(0, 101, '1')] as $ids) {
    check($planner->calculate(['guest_count' => '25', 'menu_items' => $ids], $items, [])['errors'] !== [], 'Hidden, unknown, duplicate and malformed selections rejected');
}
check($planner->calculate(['guest_count' => '25', 'package_id' => '999'], $items, [$package])['errors'] !== [], 'Unknown package rejected');
check($planner->calculate(['guest_count' => '101', 'package_id' => '10'], $items, [$package])['errors'] !== [], 'Package guest maximum enforced');
check($planner->calculate(['guest_count' => '19', 'package_id' => '10'], $items, [$package])['errors'] !== [], 'Package guest minimum enforced');
check($planner->calculate(['guest_count' => '25', 'package_id' => '10', 'menu_items' => ['2']], $items, [$package])['errors'] !== [], 'Minimum included choices enforced');
$extra = $items[0]; $extra['id'] = 6; $extra['category_id'] = 3;
check($planner->calculate(['guest_count' => '25', 'package_id' => '10', 'menu_items' => ['1', '2', '6']], [...$items, $extra], [$package])['errors'] !== [], 'Out-of-package categories rejected');
$extra['category_id'] = 1;
check($planner->calculate(['guest_count' => '25', 'package_id' => '10', 'menu_items' => ['1', '2', '6']], [...$items, $extra], [$package])['errors'] !== [], 'Maximum dish choices enforced');
$groupPackage = $package;
$groupPackage['menu_group_id'] = 7;
$groupPackage['menu_group_item_ids'] = '[1,2]';
check($planner->calculate($input, $items, [$groupPackage])['errors'] === [], 'Linked menu collection accepts its member dishes');
$groupPlan = $planner->calculate(['guest_count' => '25', 'package_id' => '10', 'menu_items' => ['1', '3']], $items, [$groupPackage]);
check(in_array('A selected dish is outside this package’s menu collection.', $groupPlan['errors'], true), 'Linked menu collection rejects nonmember dishes');
$package['choice_rules'] = null;
check($planner->calculate($input, $items, [$package])['estimate_cents'] === null, 'Unconfigured package selections never imply an inclusive price');
$package['pricing_basis'] = 'fixed';
check($planner->calculate(['guest_count' => '25', 'package_id' => '10'], $items, [$package])['estimate_cents'] === 3900, 'Fixed package bills once');
check($planner->calculate(['guest_count' => '25', 'menu_items' => ['1', '4']], $items, [])['estimate_cents'] === null, 'A manual-price item makes the full estimate incomplete');
check($planner->calculate(['guest_count' => '25'], $items, [])['estimate_cents'] === null, 'Open menu has no invented zero total');
check(MenuPlanner::cents('19.99') === 1999 && MenuPlanner::cents('0.01') === 1, 'Decimal prices use exact integer cents');
foreach (['-1', 'NaN', '1e3', '1.999', '1000000.01', '0 OR 1=1'] as $price) {
    try { MenuPlanner::cents($price); throw new RuntimeException('Invalid price was accepted'); }
    catch (InvalidArgumentException) { $checks++; }
}
check(MenuPlanner::tags('["vegan","unknown",["x"]]', MenuPlanner::DIETARY) === ['vegan'], 'Malformed tags cannot reach UI labels');
$items[0]['minimum_quantity'] = 1;
$items[0]['guest_tiers'] = json_encode([['minimum_guests' => 50, 'discount_bps' => 500], ['minimum_guests' => 100, 'discount_bps' => 1000]]);
$plan = $planner->calculate(['guest_count' => '49', 'menu_items' => ['1']], $items, []);
check($plan['lines'][0]['discount_bps'] === 0, 'Discount does not apply below its threshold');
$plan = $planner->calculate(['guest_count' => '50', 'menu_items' => ['1']], $items, []);
check($plan['estimate_cents'] === 39200 && $plan['lines'][0]['unit_cents'] === 784, 'Five percent tier rounds the unit price to cents');
$plan = $planner->calculate(['guest_count' => '100', 'menu_items' => ['1']], $items, []);
check($plan['estimate_cents'] === 74300 && $plan['lines'][0]['discount_bps'] === 1000, 'Highest reached guest threshold applies to all billed units');
$operations = new EventOperations();
$operationsConfig = [
    'lead_days' => 7, 'capacity_guests' => 250,
    'styles' => [['name' => 'Buffet', 'minimum_guests' => 20, 'maximum_guests' => 200, 'lead_days' => 14]],
    'extras' => [
        ['key' => 'setup', 'name' => 'Setup service', 'price_cents' => 15000, 'pricing_basis' => 'fixed'],
        ['key' => 'staff', 'name' => 'Service staff', 'price_cents' => 1200, 'pricing_basis' => 'per_guest'],
    ],
    'blackout_dates' => ['2027-06-20'],
    'windows' => ['pickup' => ['11:00 AM'], 'delivery' => ['12:00 PM']],
    'zones' => [['name' => 'Local', 'postal_codes' => ['70001'], 'fee_cents' => 5000, 'minimum_order_cents' => 50000]],
];
$operationPlan = $operations->evaluate([
    'event_date' => '2027-06-15', 'guest_count' => '25', 'service_style' => 'Buffet',
    'fulfillment_method' => 'delivery', 'fulfillment_window' => '12:00 PM', 'postal_code' => '70001',
    'extras' => ['setup', 'staff'],
], $operationsConfig, 97500, new DateTimeImmutable('2027-05-01'));
check($operationPlan['errors'] === [], 'Valid fulfillment request passes operational screening');
check($operationPlan['operations_cents'] === 50000 && $operationPlan['combined_estimate_cents'] === 147500,
    'Delivery, fixed extras, and per-guest extras use exact cents');
check($operationPlan['delivery_zone']['name'] === 'Local', 'Matched delivery zone is retained in the snapshot');
check($operations->evaluate(['event_date' => '2027-05-10', 'guest_count' => '25', 'service_style' => 'Buffet',
    'fulfillment_method' => 'pickup', 'fulfillment_window' => '11:00 AM'], $operationsConfig, 97500,
    new DateTimeImmutable('2027-05-01'))['errors'] !== [], 'Service-specific lead time is enforced');
check($operations->evaluate(['event_date' => '2027-06-20', 'guest_count' => '25', 'service_style' => 'Buffet',
    'fulfillment_method' => 'pickup', 'fulfillment_window' => '11:00 AM'], $operationsConfig, 97500,
    new DateTimeImmutable('2027-05-01'))['errors'] !== [], 'Blackout date is enforced');
check($operations->evaluate(['event_date' => '2027-06-15', 'guest_count' => '251', 'service_style' => 'Buffet',
    'fulfillment_method' => 'pickup', 'fulfillment_window' => '11:00 AM'], $operationsConfig, 97500,
    new DateTimeImmutable('2027-05-01'))['errors'] !== [], 'Configured capacity and style guest range are enforced');
check($operations->evaluate(['event_date' => '2027-06-15', 'guest_count' => '25', 'service_style' => 'Buffet',
    'fulfillment_method' => 'delivery', 'fulfillment_window' => '12:00 PM', 'postal_code' => '99999'], $operationsConfig, 97500,
    new DateTimeImmutable('2027-05-01'))['errors'] !== [], 'Unknown delivery postal code is rejected');
check($operations->evaluate(['event_date' => '2027-06-15', 'guest_count' => '25', 'service_style' => 'Buffet',
    'fulfillment_method' => 'delivery', 'fulfillment_window' => '12:00 PM', 'postal_code' => '70001'], $operationsConfig, 40000,
    new DateTimeImmutable('2027-05-01'))['errors'] !== [], 'Delivery minimum food order is enforced');
check($operations->evaluate(['event_date' => '2027-06-15', 'guest_count' => '25', 'service_style' => 'Buffet',
    'fulfillment_method' => 'pickup', 'fulfillment_window' => '11:00 AM', 'extras' => ['unknown']], $operationsConfig, 97500,
    new DateTimeImmutable('2027-05-01'))['errors'] !== [], 'Unknown optional service is rejected');
echo "Menu planner: {$checks} checks passed.\n";
