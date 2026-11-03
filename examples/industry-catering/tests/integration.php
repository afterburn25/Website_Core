<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
session_start();
require __DIR__ . '/bootstrap.php';
set_error_handler(static function (int $level, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $level, $file, $line);
});
$checks = 0;
function verify(bool $condition, string $description): void {
    global $checks;
    if (!$condition) throw new RuntimeException($description);
    $checks++;
}
$f = cateringFixture();
$db = $f['db']; $csrf = $f['csrf']; $ctl = $f['controller']; $router = $f['router'];
$token = $csrf->token();
$categories = $db->fetchAll('SELECT * FROM `' . $db->table('catering_menu_categories') . '` ORDER BY id');
$items = $db->fetchAll('SELECT * FROM `' . $db->table('catering_menu_items') . '` ORDER BY id');
verify(count($items) === 10, 'All starter dishes survive the additive migration');
verify($items[0]['pricing_basis'] === 'per_guest', 'Legacy guest prices are inferred once');
$db->update('catering_menu_items', ['pricing_basis' => 'manual', 'name' => 'Administrator dish', 'dietary' => 'Custom dietary note'], 'id = ?', [(int) $items[0]['id']]);
$migration = require $moduleRoot . '/migrations/0005_menu_planner.php';
$migration->up($db); $migration->up($db);
$availabilityMigration = require $moduleRoot . '/migrations/0006_catalog_availability.php';
$availabilityMigration->up($db); $availabilityMigration->up($db);
$operationsMigration = require $moduleRoot . '/migrations/0007_event_operations.php';
$operationsMigration->up($db); $operationsMigration->up($db);
$workspaceMigration = require $moduleRoot . '/migrations/0008_event_workspace.php';
$workspaceMigration->up($db); $workspaceMigration->up($db);
$custom = $db->fetch('SELECT * FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$items[0]['id']]);
verify($custom['pricing_basis'] === 'manual' && $custom['dietary'] === 'Custom dietary note', 'Repeated upgrade preserves administrator pricing and content');
verify($db->tableExists('catering_menu_groups') && $db->tableExists('catering_menu_group_items'),
    'Seasonal catalog migration creates reusable menu collections idempotently');
$hasOperationsSnapshot = $db->driver() === 'sqlite'
    ? in_array('operations_plan', array_column($db->fetchAll('PRAGMA table_info(`' . $db->table('catering_inquiries') . '`)'), 'name'), true)
    : (bool) $db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
        [$db->table('catering_inquiries'), 'operations_plan']);
verify($hasOperationsSnapshot, 'Event operations migration adds the immutable operations snapshot');
verify($db->tableExists('catering_events') && $db->tableExists('catering_event_costs') && $db->tableExists('catering_recipe_ingredients'),
    'Event workspace migration is idempotent and creates production and costing tables');
verify((new Core\Database\Migrator($db, $moduleRoot . '/migrations'))->pending() === [], 'Core migration journal records the module migrations');
verify($router->dispatch(cateringRequest('POST', '/catering/estimate', ['guest_count' => '25']))->status() === 419, 'Estimate endpoint requires CSRF');
verify($ctl->adminMenusSave(cateringRequest('POST', '/admin/catering/menus', ['_csrf' => $token, 'action' => 'delete_item', 'id' => $items[0]['id']]))->status() === 403, 'Direct admin action denies unauthenticated callers');
cateringTestIdentity($f, 'editor');
verify($ctl->adminPackagesSave(cateringRequest('POST', '/admin/catering/packages', ['_csrf' => $token, 'action' => 'add_package']))->status() === 403, 'Core editor cannot modify catering packages');
cateringTestIdentity($f, 'owner');
verify($ctl->adminMenusSave(cateringRequest('POST', '/admin/catering/menus', ['action' => 'add_item']))->status() === 419, 'Direct admin action also requires CSRF');
verify($ctl->adminSettingsSave(cateringRequest('POST', '/admin/catering/settings'))->status() === 419, 'Direct settings action requires CSRF');
$groupInput = [
    '_csrf' => $token, 'action' => 'add_group', 'name' => 'Wedding favorites',
    'description' => 'A reusable menu for wedding packages.', 'sort_order' => '10',
    'item_ids' => [(string) $items[0]['id'], (string) $items[1]['id']],
];
verify($router->dispatch(cateringRequest('POST', '/admin/catering/menu-groups', $groupInput))->status() === 302,
    'Owner can create a reusable menu collection');
$menuGroup = $db->fetch('SELECT * FROM `' . $db->table('catering_menu_groups') . '` WHERE name = ?', ['Wedding favorites']);
verify($menuGroup !== null && (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_menu_group_items') . '` WHERE group_id = ?', [(int) $menuGroup['id']]) === 2,
    'Menu collection saves validated dish membership');
$settingsInput = [
    '_csrf' => $token, 'travel_note' => 'Delivery depends on zone.', 'minimum_guests' => '15',
    'lead_time' => '10', 'quote_expiry' => '21',
    'event_types' => "Wedding\nFundraiser", 'service_styles' => "Buffet\nChef stations",
    'budget_ranges' => "Under \$5,000\n\$5,000+",
    'capacity_guests' => '300',
    'service_style_rules' => "Buffet|20|200|10\nChef stations|40|300|21",
    'optional_extras' => "setup|Setup service|150.00|fixed\nstaff|Service staff|12.00|per_guest",
    'blackout_dates' => date('Y-m-d', strtotime('+200 days')),
    'pickup_windows' => "11:00 AM–12:00 PM\n4:00 PM–5:00 PM",
    'delivery_windows' => "12:00 PM–1:00 PM\n5:00 PM–6:00 PM",
    'delivery_zones' => 'Greater New Orleans|70001,70002|50.00|500.00',
    'quote_fields' => [
        'dietary' => ['enabled' => '1', 'required' => '1'],
        'service_style' => ['enabled' => '1'], 'budget' => ['enabled' => '1'],
    ],
];
verify($router->dispatch(cateringRequest('POST', '/admin/catering/settings', $settingsInput))->status() === 302,
    'Owner can save Catering-specific quote choices through the protected settings route');
verify($f['settings']->get('catering.event_types') === ['Wedding', 'Fundraiser']
    && $f['settings']->get('catering.quote_fields')['dietary']['required'] === true
    && $f['settings']->get('catering.delivery_zones')[0]['fee_cents'] === 5000
    && $f['settings']->get('catering.delivery_zones')[0]['postal_codes'] === ['70001', '70002'],
    'Quote choices, field requirements, and operations rules persist in Core settings');
$configuredQuote = $router->dispatch(cateringRequest('GET', '/request-a-quote'))->body();
verify(str_contains($configuredQuote, 'Fundraiser') && !str_contains($configuredQuote, 'name="company"')
    && str_contains($configuredQuote, 'name="dietary"') && str_contains($configuredQuote, 'name="dietary" rows="4" maxlength="3000" required')
    && str_contains($configuredQuote, 'name="fulfillment_method"') && str_contains($configuredQuote, 'Setup service'),
    'Quote form renders configured event choices, field requirements, fulfillment, and optional services');
$resources = [];
foreach ($f['ctx']->adminResources() as $resource) $resources[(string) $resource['key']] = $resource;
verify(isset($resources['menu-items'], $resources['packages']), 'Core shared menu and package resources are registered');
$menuRows = ($resources['menu-items']['list'])('', 1, 25);
verify(count($menuRows['rows']) === 10 && $menuRows['total'] === 10, 'Shared menu resource lists the complete starter catalog');
$searchRows = ($resources['menu-items']['list'])('Gumbo', 1, 25);
verify($searchRows['total'] === 1 && str_contains($searchRows['rows'][0]['name'], 'Gumbo'), 'Shared menu resource searches dish and category names');
$orderedIds = [(int) $items[2]['id'], (int) $items[0]['id'], (int) $items[1]['id']];
verify(($resources['menu-items']['reorder'])($orderedIds) === true, 'Shared menu resource accepts visible-row ordering');
$catalogOrder = array_map('intval', array_column($db->fetchAll('SELECT id FROM `' . $db->table('catering_menu_items') . '` ORDER BY sort_order, name, id'), 'id'));
$selectedOrder = array_values(array_filter($catalogOrder, static fn (int $id): bool => in_array($id, $orderedIds, true)));
verify($selectedOrder === $orderedIds && count($catalogOrder) === 10, 'Menu resource persists the submitted partial order without dropping records');
verify(($resources['menu-items']['bulk'])([$items[0]['id'], $items[1]['id']], 'hide') === true
    && (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_menu_items') . '` WHERE id IN (?, ?) AND visible = 0', [$items[0]['id'], $items[1]['id']]) === 2,
    'Menu resource performs validated bulk visibility changes');
verify(($resources['menu-items']['bulk'])([$items[0]['id'], $items[1]['id']], 'show') === true, 'Menu resource restores bulk visibility');
$preservedPrice = (int) $db->fetchColumn('SELECT price_cents FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$items[0]['id']]);
[$quickItemId, $quickError] = ($resources['menu-items']['save'])((int) $items[0]['id'], [
    'category_id' => (string) $categories[0]['id'], 'name' => 'Quick manager dish', 'visible' => 1,
]);
verify($quickError === null && $quickItemId === (int) $items[0]['id']
    && (int) $db->fetchColumn('SELECT price_cents FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$items[0]['id']]) === $preservedPrice,
    'Quick menu edits preserve advanced pricing fields');
[$draftItemId, $quickError] = ($resources['menu-items']['save'])(null, [
    'category_id' => (string) $categories[0]['id'], 'name' => 'Quick draft dish', 'visible' => 0,
]);
verify($quickError === null && (string) $db->fetchColumn('SELECT pricing_basis FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$draftItemId]) === 'manual',
    'Quick-created dishes require team pricing confirmation');
verify(($resources['menu-items']['delete'])($draftItemId) === true, 'Shared menu resource deletes only its validated record');
$packageRows = ($resources['packages']['list'])('', 1, 25);
verify($packageRows['total'] === 3 && count($packageRows['rows']) === 3, 'Shared package resource lists the package catalog');
$packageIds = array_map('intval', array_column($packageRows['rows'], 'id'));
verify(($resources['packages']['reorder'])(array_reverse($packageIds)) === true, 'Shared package resource saves accessible ordering');
verify(($resources['packages']['bulk'])([$packageIds[0]], 'feature') === true
    && (int) $db->fetchColumn('SELECT featured FROM `' . $db->table('catering_packages') . '` WHERE id = ?', [$packageIds[0]]) === 1,
    'Shared package resource applies declared featured actions');
verify(is_string(($resources['packages']['bulk'])([999999], 'hide')), 'Resource callbacks reject records outside the Catering catalog');
$input = ['_csrf' => $token, 'action' => 'update_item', 'id' => (string) $items[0]['id'], 'category_id' => (string) $categories[0]['id'],
    'name' => '<script>alert(1)</script> Special', 'description' => 'A test dish', 'price' => '125.25', 'pricing_basis' => 'per_tray',
    'servings_per_tray' => '12', 'minimum_quantity' => '1', 'price_unit' => 'per tray', 'dietary_tags' => ['vegetarian'],
    'allergens' => ['milk', 'wheat'], 'allergen_notes' => 'Shared kitchen', 'dietary' => 'Recipe verified', 'image' => 'boudin-balls.webp', 'sort_order' => '10'];
verify($router->dispatch(cateringRequest('POST', '/admin/catering/menus', $input))->status() === 302, 'Owner can update catering pricing and labels through protected route');
$updated = $db->fetch('SELECT * FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$items[0]['id']]);
verify((int) $updated['price_cents'] === 12525 && $updated['pricing_basis'] === 'per_tray', 'Exact price and serving configuration persisted');
verify(json_decode($updated['allergens'], true) === ['milk', 'wheat'], 'Structured allergens persisted');
verify($db->fetchColumn('SELECT actor_id FROM `' . $db->table('audit_log') . '` WHERE event = ?', ['catering.menu.update_item']) !== false, 'Menu changes create a Core audit event');
$bad = $input; $bad['price'] = '1.999';
$response = $router->dispatch(cateringRequest('POST', '/admin/catering/menus', $bad));
verify($response->status() === 200 && str_contains($response->body(), 'at most two decimal'), 'Invalid price produces a retained-input error');
verify(str_contains($response->body(), 'value="1.999"'), 'Failed editor preserves input');
verify(!str_contains($response->body(), '<script>alert(1)</script>'), 'Admin display escapes dish names');
$package = $db->fetch('SELECT * FROM `' . $db->table('catering_packages') . '` ORDER BY id LIMIT 1');
$packageInput = ['_csrf' => $token, 'action' => 'update_package', 'id' => (string) $package['id'], 'name' => $package['name'],
    'price' => '39.00', 'pricing_basis' => 'per_guest', 'minimum_quantity' => '1', 'guest_min' => '20', 'guest_max' => '100',
    'menu_group_id' => (string) $menuGroup['id'],
    'choices' => [(string) $categories[0]['id'] => ['enabled' => '1', 'min' => '1', 'max' => '1']]];
verify($router->dispatch(cateringRequest('POST', '/admin/catering/packages', $packageInput))->status() === 302, 'Owner can configure included category choices');
verify((int) $db->fetchColumn('SELECT menu_group_id FROM `' . $db->table('catering_packages') . '` WHERE id = ?', [$package['id']]) === (int) $menuGroup['id'],
    'Package links to the reusable menu collection');
$db->update('catering_packages', ['available_until' => '2000-01-01'], 'id = ?', [$package['id']]);
verify(!str_contains($router->dispatch(cateringRequest('GET', '/request-a-quote'))->body(), 'value="' . (int) $package['id'] . '" data-rules='),
    'Expired packages are excluded from public quote planning');
$db->update('catering_packages', ['available_until' => null], 'id = ?', [$package['id']]);
$outsideGroup = ['_csrf' => $token, 'guest_count' => '25', 'package_id' => (string) $package['id'], 'menu_items' => [(string) $items[2]['id']]];
$outsidePlan = json_decode($router->dispatch(cateringRequest('POST', '/catering/estimate', $outsideGroup))->body(), true);
verify(in_array('A selected dish is outside this package’s menu collection.', $outsidePlan['errors'], true),
    'Server rejects package dishes outside the linked collection');
$db->update('catering_menu_items', ['available_until' => '2000-01-01'], 'id = ?', [$items[2]['id']]);
$expiredPlan = json_decode($router->dispatch(cateringRequest('POST', '/catering/estimate', [
    '_csrf' => $token, 'guest_count' => '25', 'menu_items' => [(string) $items[2]['id']],
]))->body(), true);
verify(in_array('A selected dish is no longer available. Please review your menu.', $expiredPlan['errors'], true),
    'Expired seasonal dishes are excluded by server-authoritative planning');
$db->update('catering_menu_items', ['available_until' => null], 'id = ?', [$items[2]['id']]);
$tierInput = $input;
$tierInput['guest_tiers'] = [['minimum_guests' => '50', 'discount_percent' => '5.00']];
verify($router->dispatch(cateringRequest('POST', '/admin/catering/menus', $tierInput))->status() === 302, 'Guest-count tiers can be saved through admin');
$tierEstimate = $estimateInput ?? ['_csrf' => $token, 'guest_count' => '50', 'menu_items' => [(string) $items[0]['id']]];
$tierEstimate['guest_count'] = '50';
$discounted = json_decode($router->dispatch(cateringRequest('POST', '/catering/estimate', $tierEstimate))->body(), true);
verify($discounted['lines'][0]['discount_bps'] === 500 && $discounted['estimate_cents'] === 59495, 'Saved discount is applied to the rounded tray quantity');
$badTier = $input; $badTier['guest_tiers'] = [['minimum_guests' => '50', 'discount_percent' => '101']];
verify(str_contains($router->dispatch(cateringRequest('POST', '/admin/catering/menus', $badTier))->body(), 'between 0 and 100'), 'Invalid discount rejected without saving');
$badPackage = $packageInput; $badPackage['guest_max'] = '10';
verify(str_contains($router->dispatch(cateringRequest('POST', '/admin/catering/packages', $badPackage))->body(), 'Maximum guests must'), 'Impossible guest range is rejected');
$badPackage = $packageInput; $badPackage['choices'][(string) $categories[0]['id']]['min'] = '99'; $badPackage['choices'][(string) $categories[0]['id']]['max'] = '100';
verify(str_contains($router->dispatch(cateringRequest('POST', '/admin/catering/packages', $badPackage))->body(), 'not enough visible dishes'), 'Unsatisfiable package choices rejected');
$estimateInput = ['_csrf' => $token, 'guest_count' => '25', 'menu_items' => [(string) $items[0]['id']], 'estimate_cents' => '1'];
$estimate = $router->dispatch(cateringRequest('POST', '/catering/estimate', $estimateInput));
verify($estimate->status() === 200 && json_decode($estimate->body(), true)['estimate_cents'] === 37575, 'Estimate route rounds trays and ignores submitted totals');
verify(($estimate->headers()['Cache-Control'] ?? '') === 'private, no-store', 'Estimate responses cannot enter shared caches');
$db->update('catering_menu_categories', ['visible' => 0], 'id = ?', [$categories[0]['id']]);
verify($router->dispatch(cateringRequest('POST', '/catering/estimate', $estimateInput))->status() === 422, 'Dishes in hidden categories cannot be selected');
$db->update('catering_menu_categories', ['visible' => 1], 'id = ?', [$categories[0]['id']]);
$before = (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_inquiries') . '`');
$preview = $router->dispatch(cateringRequest('POST', '/request-a-quote', $estimateInput + ['preview_plan' => '1']));
verify($preview->status() === 200 && str_contains($preview->body(), '375.75'), 'No-JavaScript preview calculates the server estimate');
verify((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_inquiries') . '`') === $before, 'Preview does not create an inquiry');
$quote = $estimateInput + ['event_type' => 'Wedding', 'event_date' => date('Y-m-d', strtotime('+120 days')), 'name' => 'Menu Planner Test', 'email' => 'guest@example.test',
    'company' => 'Ignored disabled value', 'dietary' => 'Please discuss milk allergy', 'acknowledged' => '1', 'package_id' => (string) $package['id']];
$quote += ['service_style' => 'Buffet', 'fulfillment_method' => 'delivery', 'fulfillment_window' => '12:00 PM–1:00 PM',
    'address_line_1' => '101 Main Street', 'address_line_2' => 'Ballroom A', 'city' => 'New Orleans',
    'region' => 'LA', 'postal_code' => '70001', 'extras' => ['setup'], 'tasting_requested' => '1'];
$missingDietary = $quote; unset($missingDietary['dietary']);
verify(str_contains($router->dispatch(cateringRequest('POST', '/request-a-quote', $missingDietary))->body(), 'Dietary needs, allergies and handling requests is required.'),
    'Server enforces administrator-configured required quote fields');
$response = $router->dispatch(cateringRequest('POST', '/request-a-quote', $quote));
verify($response->status() === 302 && str_contains($response->headers()['Location'] ?? '', '/catering/index.php?p=/request-a-quote&sent=1'),
    'Quote redirects correctly under subfolder/query routing; status=' . $response->status() . '; body=' . trim(strip_tags($response->body())));
$row = $db->fetch('SELECT * FROM `' . $db->table('catering_inquiries') . '` ORDER BY id DESC LIMIT 1');
$snapshot = json_decode($row['menu_plan'], true);
$operationsSnapshot = json_decode($row['operations_plan'], true);
verify($snapshot['estimate_cents'] === 97500 && $snapshot['items'][0]['name'] === $input['name'], 'Inquiry stores authoritative package/menu/price snapshot');
verify($operationsSnapshot['operations_cents'] === 20000 && $operationsSnapshot['combined_estimate_cents'] === 117500
    && $row['fulfillment_method'] === 'delivery', 'Inquiry stores authoritative fulfillment, zone fee, and optional-service snapshot');
verify($row['dietary'] === 'Please discuss milk allergy', 'Customer allergy requests remain separate from catalog labels');
verify($row['company'] === '', 'Disabled quote fields are discarded even when submitted directly');
verify($row['address_line_1'] === '101 Main Street' && $row['city'] === 'New Orleans', 'Structured delivery address is retained with the questionnaire');
verify((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_tastings') . '` WHERE inquiry_id=?', [(int)$row['id']]) === 1,
    'Tasting request creates one review record without scheduling an appointment');
$mail = $db->fetch('SELECT * FROM `' . $db->table('system_messages') . '` ORDER BY id DESC LIMIT 1');
verify(str_contains($mail['body'], 'Selected dishes:') && str_contains($mail['body'], '$975.00') && str_contains($mail['body'], '$1,175.00'),
    'Core queued email includes the selected menu and combined estimate');
$db->update('catering_menu_items', ['name' => 'Renamed later'], 'id = ?', [$items[0]['id']]);
$inbox = $router->dispatch(cateringRequest('GET', '/admin/catering/inquiries'));
verify($inbox->status() === 302 && str_contains($inbox->headers()['Location'], '/admin/inquiries'), 'Legacy inbox redirects to Core');
$coreInquiry = $db->fetch('SELECT * FROM `' . $db->table('crm_inquiries') . '` WHERE id = ? AND owner_package = ?', [(int) $row['core_inquiry_id'], 'cajunwebpro/catering']);
verify($coreInquiry !== null && str_contains($coreInquiry['message'], 'Menu submission snapshot') && str_contains($coreInquiry['message'], 'Special')
    && str_contains($coreInquiry['message'], '$975.00') && str_contains($coreInquiry['message'], 'Operations submission snapshot'),
    'Core inquiry retains original selected menu and operations estimates');
$coreCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('crm_inquiries') . '`');
$ctl->synchronizeLegacyInquiries(); $ctl->synchronizeLegacyInquiries();
verify((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('crm_inquiries') . '`') === $coreCount, 'Repeated linking does not duplicate sales inquiries');
verify((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('system_messages') . '`') === 1, 'One notification is queued per submission');
$invalidQuote = $quote; $invalidQuote['event_date'] = '2027-02-30';
verify(str_contains($router->dispatch(cateringRequest('POST', '/request-a-quote', $invalidQuote))->body(), 'valid event date'), 'Impossible calendar date rejected server-side');
$invalidQuote = $quote; unset($invalidQuote['acknowledged']);
verify(str_contains($router->dispatch(cateringRequest('POST', '/request-a-quote', $invalidQuote))->body(), 'acknowledge'), 'Booking acknowledgement enforced server-side');
$outsideZone = $estimateInput + ['event_date' => date('Y-m-d', strtotime('+120 days')), 'guest_count' => '25', 'service_style' => 'Buffet',
    'fulfillment_method' => 'delivery', 'fulfillment_window' => '12:00 PM–1:00 PM', 'postal_code' => '99999'];
verify(str_contains($router->dispatch(cateringRequest('POST', '/catering/estimate', $outsideZone))->body(), 'currently outside our service area'),
    'Server-authoritative estimate gives a customer-facing out-of-area error');
$eventCreate=$router->dispatch(cateringRequest('POST','/admin/catering/events',['_csrf'=>$token,'action'=>'create_event','inquiry_id'=>(string)$row['id']]));
verify($eventCreate->status()===302,'Qualified quote request can be promoted to a tentative event');
$event=$db->fetch('SELECT * FROM `'.$db->table('catering_events').'` WHERE inquiry_id=?',[(int)$row['id']]);
verify($event!==null && $event['state']==='tentative' && json_decode((string)$event['menu_plan'],true)['estimate_cents']===97500,
    'Event retains the immutable menu and operations source snapshots');
$eventId=(int)$event['id'];
$quoteService=$f['ctx']->service(Core\Leads\QuoteService::class);
[$coreQuoteId,$quoteError,$responseToken]=$quoteService->save('cajunwebpro/catering',[
    'inquiry_id'=>(int)$row['core_inquiry_id'],'customer_name'=>$row['name'],'customer_email'=>$row['email'],
    'status'=>'sent','currency'=>'USD','discount'=>'0','tax'=>'0','valid_until'=>date('Y-m-d',strtotime('+30 days')),
], [['description'=>'Accepted catering plan','quantity'=>1,'unit_price'=>'1175.00']]);
verify($quoteError===null && $coreQuoteId!==null && is_string($responseToken) && $quoteService->respond($responseToken,true),
    'Core quotation can be accepted for the Catering inquiry');
verify($router->dispatch(cateringRequest('GET','/admin/catering/events'))->status()===200,'Event workspace renders and synchronizes accepted Core quotes');
$event=$db->fetch('SELECT * FROM `'.$db->table('catering_events').'` WHERE id=?',[$eventId]);
verify($event['state']==='confirmed' && (int)$event['core_quote_id']===$coreQuoteId
    && (int)$db->fetchColumn('SELECT COUNT(*) FROM `'.$db->table('catering_events').'` WHERE inquiry_id=?',[(int)$row['id']])===1,
    'Accepted Core quote confirms the existing event without duplicating it');
$eventPath='/admin/catering/events/'.$eventId;
$eventUpdate=['_csrf'=>$token,'action'=>'update_event','event_id'=>(string)$eventId,'state'=>'confirmed','title'=>'Final Wedding Plan',
    'event_date'=>$row['event_date'],'event_time'=>'18:00','final_guest_count'=>'30','final_headcount_due'=>date('Y-m-d',strtotime($row['event_date'].' -7 days')),
    'venue'=>'Grand Hall','run_of_show'=>'4:00 Load in\n6:00 Dinner','internal_notes'=>'Chef reviewed','delivery_status'=>'scheduled',
    'driver_name'=>'Driver One','route_order'=>'2','setup_instructions'=>'Use south entrance','proof_reference'=>'Setup photo pending'];
verify($router->dispatch(cateringRequest('POST',$eventPath,$eventUpdate))->status()===302,'Event plan, final headcount, timeline, and delivery manifest can be updated');
verify($router->dispatch(cateringRequest('POST',$eventPath,['_csrf'=>$token,'action'=>'add_task','event_id'=>(string)$eventId,'title'=>'Confirm rental count','category'=>'rentals','due_date'=>date('Y-m-d',strtotime($row['event_date'].' -10 days')),'assignee'=>'Event captain','notes'=>'Check linens']))->status()===302,'Event task can be assigned');
verify($router->dispatch(cateringRequest('POST',$eventPath,['_csrf'=>$token,'action'=>'add_staff','event_id'=>(string)$eventId,'position'=>'Server','staff_name'=>'Team Member','starts_at'=>$row['event_date'].'T16:00','ends_at'=>$row['event_date'].'T20:00','hourly_cost'=>'20.00','notes'=>'Dining room']))->status()===302,'Event staffing line can be scheduled');
verify($router->dispatch(cateringRequest('POST',$eventPath,['_csrf'=>$token,'action'=>'add_equipment','event_id'=>(string)$eventId,'item_name'=>'Chafing dish','quantity'=>'10','replacement_cost'=>'5.00','notes'=>'Full-size']))->status()===302,'Equipment pull line can be added');
$equipmentId=(int)$db->fetchColumn('SELECT id FROM `'.$db->table('catering_event_equipment').'` WHERE event_id=?',[$eventId]);
verify($router->dispatch(cateringRequest('POST',$eventPath,['_csrf'=>$token,'action'=>'update_equipment','event_id'=>(string)$eventId,'id'=>(string)$equipmentId,'packed_quantity'=>'10','returned_quantity'=>'4','status'=>'damaged']))->status()===302,'Packing and equipment returns can be reconciled');
verify($router->dispatch(cateringRequest('POST','/admin/catering/production',['_csrf'=>$token,'action'=>'add_ingredient','name'=>'Test rice','unit'=>'lb','cost'=>'2.00','supplier'=>'Local supplier','allergens'=>[]]))->status()===302,'Verified ingredient cost can be added');
$ingredientId=(int)$db->fetchColumn('SELECT id FROM `'.$db->table('catering_ingredients').'` WHERE name=?',['Test rice']);
verify($router->dispatch(cateringRequest('POST','/admin/catering/production',['_csrf'=>$token,'action'=>'add_recipe_ingredient','menu_item_id'=>(string)$items[0]['id'],'ingredient_id'=>(string)$ingredientId,'quantity_per_guest'=>'0.1000','waste_percent'=>'0','station'=>'Hot line','prep_lead_days'=>'1','label_notes'=>'Keep hot','prep_notes'=>'Rinse before cooking']))->status()===302,'Recipe quantity can be linked to a menu item');
$recipeId=(int)$db->fetchColumn('SELECT id FROM `'.$db->table('catering_recipe_ingredients').'` WHERE menu_item_id=? AND ingredient_id=?',[(int)$items[0]['id'],$ingredientId]);
verify($router->dispatch(cateringRequest('POST','/admin/catering/production',['_csrf'=>$token,'action'=>'update_ingredient','id'=>(string)$ingredientId,'name'=>'Test rice','unit'=>'lb','cost'=>'2.00','supplier'=>'Updated supplier','active'=>'1','allergens'=>[]]))->status()===302
    && $db->fetchColumn('SELECT supplier FROM `'.$db->table('catering_ingredients').'` WHERE id=?',[$ingredientId])==='Updated supplier','Ingredient costs and supplier data can be maintained');
verify($router->dispatch(cateringRequest('POST','/admin/catering/production',['_csrf'=>$token,'action'=>'update_recipe_ingredient','id'=>(string)$recipeId,'menu_item_id'=>(string)$items[0]['id'],'ingredient_id'=>(string)$ingredientId,'quantity_per_guest'=>'0.1000','waste_percent'=>'0','station'=>'Hot line','prep_lead_days'=>'1','label_notes'=>'Keep hot','prep_notes'=>'Rinse twice']))->status()===302
    && $db->fetchColumn('SELECT prep_notes FROM `'.$db->table('catering_recipe_ingredients').'` WHERE id=?',[$recipeId])==='Rinse twice','Recipe production instructions can be maintained');
$workspace=new CajunWebPro\Catering\EventWorkspace($db);
$plannedEvent=$workspace->find($eventId); $production=$workspace->productionPlan($plannedEvent,$quoteService->find((int)$coreQuoteId,'cajunwebpro/catering'));
verify($production['guests']===30 && $production['food_cost_minor']===600 && $production['labor_cost_minor']===8000
    && $production['equipment_exposure_minor']===3000 && $production['revenue_minor']===117500
    && isset($production['prep_groups'][date('Y-m-d',strtotime($row['event_date'].' -1 day'))]['Hot line']),
    'Production worksheet scales ingredients, schedules stations, and uses accepted Core quote revenue');
verify($router->dispatch(cateringRequest('POST',$eventPath,['_csrf'=>$token,'action'=>'add_cost','event_id'=>(string)$eventId,'category'=>'food','description'=>'Final ingredients','estimated'=>'100.00','actual'=>'110.00','notes'=>'Invoice received']))->status()===302,'Estimated and actual event costs can be recorded');
$plannedEvent=$workspace->find($eventId); $production=$workspace->productionPlan($plannedEvent,$quoteService->find((int)$coreQuoteId,'cajunwebpro/catering'));
verify($production['estimated_cost_minor']===10000 && $production['actual_cost_minor']===11000
    && $production['estimated_margin_minor']===107500 && $production['actual_margin_minor']===106500,
    'Event costing compares accepted revenue with estimated and actual operations costs');
verify($workspace->purchasingPlan([$plannedEvent])[0]['quantity']===3.0,'Purchasing plan combines upcoming event ingredient requirements');
$eventPage=$router->dispatch(cateringRequest('GET',$eventPath));
verify($eventPage->status()===200 && str_contains($eventPage->body(),'Printable production worksheet') && str_contains($eventPage->body(),'Test rice'),
    'Event workspace renders kitchen, packing, staffing, and delivery details');
$tastingId=(int)$db->fetchColumn('SELECT id FROM `'.$db->table('catering_tastings').'` WHERE inquiry_id=?',[(int)$row['id']]);
verify($router->dispatch(cateringRequest('POST','/admin/catering/tastings',['_csrf'=>$token,'id'=>(string)$tastingId,'eligibility'=>'eligible','status'=>'scheduled','preferred_date'=>date('Y-m-d',strtotime('+30 days')),'notes'=>'Two guests']))->status()===302
    && $db->fetchColumn('SELECT status FROM `'.$db->table('catering_tastings').'` WHERE id=?',[$tastingId])==='scheduled','Tasting request can be reviewed and scheduled');
$contentTypes=$f['ctx']->contentTypes();
verify(isset($contentTypes['catering_venue'],$contentTypes['catering_case_study'],$contentTypes['catering_review']),
    'Venue, case-study, and verified-review editing use Core content types');
$now=date('Y-m-d H:i:s');
foreach ([
    ['catering_venue','Partner Hall',['name'=>'Partner Hall','city'=>'Lafayette, LA','website'=>'https://example.test/venue','description'=>'A flexible event venue.','service_notes'=>'Use the loading entrance.']],
    ['catering_case_study','Fundraiser Gala',['event_name'=>'Fundraiser Gala','event_type'=>'Nonprofit','summary'=>'Dinner for 200 guests.','menu'=>'Cajun buffet','result'=>'Service completed on schedule.']],
    ['catering_review','Verified Client',['customer_name'=>'Verified Client','event_type'=>'Wedding','rating'=>'5','review'=>'The team made the evening effortless.','verified'=>'1']],
] as [$type,$title,$fields]) $db->insert('content_items',['package'=>'cajunwebpro/catering','type'=>$type,'title'=>$title,'status'=>'published','data'=>json_encode($fields,JSON_THROW_ON_ERROR),'sort'=>0,'created_by'=>null,'updated_by'=>null,'created_at'=>$now,'updated_at'=>$now]);
verify(str_contains($router->dispatch(cateringRequest('GET','/'))->body(),'The team made the evening effortless.'),'Published verified review replaces the sample home testimonial');
verify(str_contains($router->dispatch(cateringRequest('GET','/venues'))->body(),'Partner Hall') && str_contains($router->dispatch(cateringRequest('GET','/event-stories'))->body(),'Fundraiser Gala'),
    'Published Core content renders venue partners and event case studies');
foreach (['/', '/services', '/menus', '/menus/' . $categories[0]['slug'], '/packages', '/packages/' . $package['slug'], '/gallery', '/venues', '/event-stories', '/about', '/service-area', '/faq', '/contact', '/request-a-quote', '/services/weddings', '/services/corporate-events', '/services/private-celebrations', '/services/drop-off-catering', '/services/school-catering', '/services/nonprofit-events', '/services/holiday-catering', '/services/memorial-gatherings', '/admin/catering/menus', '/admin/catering/packages', '/admin/catering/menu-groups', '/admin/catering/events', '/admin/catering/production', '/admin/catering/settings', $eventPath] as $path) {
    $response = $router->dispatch(cateringRequest('GET', $path));
    verify($response->status() === 200, 'Public/admin route renders: ' . $path);
}
$cateringNav = array_values(array_filter(
    $f['ctx']->adminNavItems(),
    static fn (array $item): bool => ($item['category'] ?? null) === 'Catering',
));
verify(array_column($cateringNav, 'icon') === ['content', 'products', 'patterns', 'orders', 'operations', 'settings', 'forms', 'marketplace'],
    'Every Catering administration link declares a distinct Core icon');
$menuAdmin = $router->dispatch(cateringRequest('GET', '/admin/catering/menus'));
verify(str_contains($menuAdmin->body(), 'Open menu quick manager'), 'Detailed menu editor links to Core shared catalog management');
$packageAdmin = $router->dispatch(cateringRequest('GET', '/admin/catering/packages'));
verify(str_contains($packageAdmin->body(), 'Open package quick manager'), 'Detailed package editor links to Core shared catalog management');
$limited = false;
for ($i = 0; $i < 95; $i++) {
    if ($router->dispatch(cateringRequest('POST', '/catering/estimate', $estimateInput))->status() === 429) { $limited = true; break; }
}
verify($limited, 'Estimate endpoint is rate limited');
// Native test databases use an isolated random prefix; remove only this fixture's own tables.
if ($db->driver() === 'mysql') {
    $db->pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $db->fetchAll('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [$db->prefix() . '%']);
    foreach ($tables as $table) {
        $name = (string) array_values($table)[0];
        if (str_starts_with($name, $db->prefix())) $db->pdo()->exec('DROP TABLE `' . str_replace('`', '``', $name) . '`');
    }
    $db->pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
}
echo "Module integration ({$db->driver()}): {$checks} checks passed.\n";
