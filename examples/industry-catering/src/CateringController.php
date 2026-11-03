<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Audit\AuditLog;
use Core\Auth\AuthService;
use Core\Business\BusinessProfile;
use Core\Content\ContentItems;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\SystemMail;
use Core\Leads\InquiryService;
use Core\Leads\QuoteService;
use Core\Media\MediaService;
use Core\Modules\ModuleContext;
use Core\Security\Csrf;
use Core\Security\RateLimiter;
use Core\Settings\SettingsRepository;
use Core\View\View;

final class CateringController
{
    private const DISH_IMAGES = [
        'boudin-balls.webp' => 'Boudin Balls',
        'crawfish-bread.webp' => 'Crawfish Bread',
        'cajun-shrimp-grits-bites.webp' => 'Cajun Shrimp & Grits Bites',
        'chicken-andouille-gumbo.webp' => 'Chicken & Andouille Gumbo',
        'crawfish-etouffee.webp' => 'Crawfish Étouffée',
        'crawfish-bisque.webp' => 'Crawfish Bisque',
        'shrimp-fettuccine.webp' => 'Shrimp Fettuccine',
        'cajun-jambalaya.webp' => 'Cajun Jambalaya',
        'blackened-redfish.webp' => 'Blackened Redfish',
        'bananas-foster-bread-pudding.webp' => 'Bananas Foster Bread Pudding',
    ];

    private const EVENT_TYPES = [
        'Wedding', 'Corporate Event', 'Private Celebration',
        'Drop-Off Catering', 'Holiday Event', 'Other',
    ];

    private const SERVICE_STYLES = [
        'Plated dinner', 'Buffet', 'Food stations', 'Cocktail reception',
        'Family style', 'Drop-off or pickup',
    ];

    private const BUDGET_RANGES = [
        'Under $2,500', '$2,500–$5,000', '$5,000–$10,000',
        '$10,000–$20,000', '$20,000+',
    ];

    private const QUOTE_FIELDS = [
        'company' => 'Company',
        'phone' => 'Phone number',
        'event_time' => 'Start time',
        'venue' => 'Venue or event location',
        'service_style' => 'Service style',
        'budget' => 'Estimated food and service budget',
        'menu_interest' => 'Menu preferences or other interests',
        'dietary' => 'Dietary needs, allergies and handling requests',
        'message' => 'Additional planning notes',
    ];

    public function __construct(
        private ModuleContext $ctx,
        private string $ns,
    ) {}

    public function home(Request $request): Response
    {
        [$availability, $availabilityParams] = $this->availabilityClause();
        $reviews = $this->ctx->service(ContentItems::class)->items($this->ctx->packageId(), 'catering_review', true);
        return $this->page($request, 'home', [
            'featuredItems' => $this->db()->fetchAll(
                'SELECT * FROM `' . $this->db()->table('catering_menu_items')
                . '` WHERE visible = 1 AND featured = 1 AND ' . $availability . ' ORDER BY sort_order, name LIMIT 3',
                $availabilityParams
            ),
            'packages' => $this->visiblePackages(3),
            'review' => $reviews[0] ?? null,
        ], 'Home', 'Thoughtful menus and polished service for memorable gatherings.');
    }

    public function services(Request $request): Response
    {
        return $this->page($request, 'services', ['services' => $this->serviceData()],
            'Catering Services', 'Explore wedding, corporate, private-event, and drop-off catering.');
    }

    public function serviceDetail(Request $request): Response
    {
        $slug = (string) ($request->attributes['slug'] ?? '');
        $service = $this->serviceData()[$slug] ?? null;
        if ($service === null) {
            return Response::notFound();
        }
        return $this->page($request, 'service-detail', ['service' => $service],
            $service['title'], $service['summary']);
    }

    public function menus(Request $request): Response
    {
        [$categories, $items] = $this->menuData();
        return $this->page($request, 'menus', ['categories' => $categories, 'items' => $items],
            'Menus', 'Browse seasonal catering menus, dietary options, and service styles.');
    }

    public function menuDetail(Request $request): Response
    {
        $slug = (string) ($request->attributes['slug'] ?? '');
        $category = $this->db()->fetch(
            'SELECT * FROM `' . $this->db()->table('catering_menu_categories') . '` WHERE slug = ? AND visible = 1',
            [$slug]
        );
        if ($category === null) {
            return Response::notFound();
        }
        [$availability, $availabilityParams] = $this->availabilityClause();
        $items = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('catering_menu_items')
            . '` WHERE category_id = ? AND visible = 1 AND ' . $availability . ' ORDER BY sort_order, name',
            array_merge([(int) $category['id']], $availabilityParams)
        );
        return $this->page($request, 'menu-detail', ['category' => $category, 'items' => $items],
            (string) $category['name'], (string) ($category['description'] ?? ''));
    }

    public function packages(Request $request): Response
    {
        return $this->page($request, 'packages', ['packages' => $this->visiblePackages()],
            'Catering Packages', 'Compare flexible catering experiences and find the right starting point.');
    }

    public function packageDetail(Request $request): Response
    {
        [$availability, $availabilityParams] = $this->availabilityClause();
        $package = $this->db()->fetch(
            'SELECT * FROM `' . $this->db()->table('catering_packages') . '` WHERE slug = ? AND visible = 1 AND ' . $availability,
            array_merge([(string) ($request->attributes['slug'] ?? '')], $availabilityParams)
        );
        if ($package === null) {
            return Response::notFound();
        }
        $group = !empty($package['menu_group_id']) ? $this->menuGroup((int) $package['menu_group_id'], true) : null;
        if (!empty($package['menu_group_id']) && $group === null) return Response::notFound();
        return $this->page($request, 'package-detail', ['package' => $package, 'menuGroup' => $group],
            (string) $package['name'], (string) ($package['description'] ?? ''));
    }

    public function gallery(Request $request): Response
    {
        return $this->page($request, 'gallery', [], 'Gallery',
            'A visual look at beautifully catered weddings, celebrations, and corporate gatherings.');
    }

    public function venues(Request $request): Response
    {
        return $this->page($request, 'venues', [
            'venues' => $this->contentWithMedia($request, 'catering_venue', ['hero_image']),
        ], 'Venue Partners', 'Preferred venues and location guidance for catered events.');
    }

    public function eventStories(Request $request): Response
    {
        return $this->page($request, 'event-stories', [
            'stories' => $this->contentWithMedia($request, 'catering_case_study', ['gallery']),
            'reviews' => $this->contentWithMedia($request, 'catering_review', ['portrait']),
        ], 'Event Stories', 'Real catering events, results, and verified client feedback.');
    }

    public function about(Request $request): Response
    {
        return $this->page($request, 'about', [], 'Our Story',
            'Meet the people and philosophy behind the catering experience.');
    }

    public function serviceArea(Request $request): Response
    {
        $settings = $this->settings();
        $profile = $this->ctx->service(BusinessProfile::class)->get();
        return $this->page($request, 'service-area', [
            'serviceArea' => $profile['service_area'] !== ''
                ? $profile['service_area']
                : 'Your city and surrounding communities',
            'travelNote' => $settings->get('catering.travel_note', 'Travel and delivery fees may apply outside our primary service area.'),
            'serviceAreaMap' => $this->ctx->serviceAreaMap('Our service area'),
        ], 'Service Area', 'Delivery, pickup, venue, and travel information.');
    }

    public function faq(Request $request): Response
    {
        return $this->page($request, 'faq', ['faqs' => $this->faqData()],
            'Frequently Asked Questions', 'Answers about availability, tastings, guest counts, dietary needs, and booking.');
    }

    public function contact(Request $request, ?string $error = null, array $old = []): Response
    {
        return $this->page($request, 'contact', [
            'csrf' => $this->ctx->service(Csrf::class),
            'error' => $error,
            'sent' => $request->string('sent') === '1',
            'old' => $old,
        ], 'Contact', 'Talk with our catering team about an upcoming gathering.');
    }

    public function submitContact(Request $request): Response
    {
        if (!$this->ctx->service(Csrf::class)->validate($request)) {
            return Response::text('Invalid CSRF token.', 419);
        }
        if ($request->string('website') !== '') {
            return Response::redirect($this->ctx->url($request->basePath, '/contact', ['sent' => 1]));
        }
        if (!$this->ctx->service(RateLimiter::class)->attempt('catering-contact:' . $request->ip(), 5, 600)) {
            return $this->contact($request, 'Too many submissions. Please wait a few minutes and try again.', $request->post);
        }
        $data = $this->contactInput($request);
        $errors = $this->contactErrors($data);
        if ($errors !== []) {
            return $this->contact($request, implode(' ', $errors), $data);
        }
        $this->saveInquiry($request, 'contact', $data);
        return Response::redirect($this->ctx->url($request->basePath, '/contact', ['sent' => 1]));
    }

    public function quote(Request $request, ?string $error = null, array $old = []): Response
    {
        [$categories, $items, $packages] = $this->plannerCatalog();
        $quoteConfig = $this->quoteConfiguration();
        $operations = $this->operationsConfiguration($quoteConfig['service_styles']);
        $prefill = $request->string('package');
        if ($old === [] && $prefill !== '') {
            foreach ($packages as $package) {
                if ($prefill === $package['name'] || $prefill === $package['slug']) $old['package_id'] = (string) $package['id'];
            }
        }
        if ($request->method === 'GET') {
            $old['event_type'] ??= $request->string('event_type');
            $old['menu_interest'] ??= $request->string('menu_interest');
        }
        $initialPlan = null;
        if (isset($old['guest_count'])) {
            $initialPlan = (new MenuPlanner())->calculate($old, $items, $packages,
                max(1, (int) $this->settings()->get('catering.minimum_guests', 20)));
            $initialPlan['operations'] = (new EventOperations())->evaluate($old, $operations, $initialPlan['estimate_cents'], null, false);
            $this->applyServiceAreaDecision($initialPlan['operations'], $old);
        }
        return $this->page($request, 'quote', [
            'csrf' => $this->ctx->service(Csrf::class), 'error' => $error,
            'sent' => $request->string('sent') === '1', 'old' => $old,
            'categories' => $categories, 'items' => $items, 'packages' => $packages,
            'dietaryLabels' => MenuPlanner::DIETARY, 'allergenLabels' => MenuPlanner::ALLERGENS,
            'eventTypes' => $quoteConfig['event_types'],
            'serviceStyles' => $quoteConfig['service_styles'],
            'budgetRanges' => $quoteConfig['budget_ranges'],
            'quoteFields' => $quoteConfig['fields'],
            'operations' => $operations,
            'minimumGuests' => max(1, (int) $this->settings()->get('catering.minimum_guests', 20)),
            'initialPlan' => $initialPlan,
            'estimateUrl' => $this->ctx->url($request->basePath, '/catering/estimate'),
        ], 'Plan Your Menu', 'Build your menu and request a personalized catering proposal.', true);
    }

    public function estimate(Request $request): Response
    {
        if (!$this->ctx->service(Csrf::class)->validate($request)) return Response::json(['errors' => ['Your session expired. Refresh the page.']], 419)->header('Cache-Control', 'private, no-store');
        if (!$this->ctx->service(RateLimiter::class)->attempt('catering-estimate:' . $request->ip(), 90, 60)) {
            return Response::json(['errors' => ['Please wait a moment before updating your estimate.']], 429)->header('Cache-Control', 'private, no-store');
        }
        [, $items, $packages] = $this->plannerCatalog();
        $plan = (new MenuPlanner())->calculate($request->post, $items, $packages,
            max(1, (int) $this->settings()->get('catering.minimum_guests', 20)));
        $quoteConfig = $this->quoteConfiguration();
        $operations = (new EventOperations())->evaluate(
            $request->post,
            $this->operationsConfiguration($quoteConfig['service_styles']),
            $plan['estimate_cents'],
            null,
            false
        );
        $this->applyServiceAreaDecision($operations, $request->post);
        $plan['operations'] = $operations;
        $plan['errors'] = array_values(array_unique(array_merge($plan['errors'], $operations['errors'])));
        $response = Response::json($plan, $plan['errors'] === [] ? 200 : 422);
        $response->header('Cache-Control', 'private, no-store');
        return $response;
    }

    public function submitQuote(Request $request): Response
    {
        if (!$this->ctx->service(Csrf::class)->validate($request)) {
            return Response::text('Invalid CSRF token.', 419);
        }
        if ($request->string('website') !== '') {
            return Response::redirect($this->ctx->url($request->basePath, '/request-a-quote', ['sent' => 1]));
        }
        if ($request->string('preview_plan') === '1') {
            if (!$this->ctx->service(RateLimiter::class)->attempt('catering-estimate:' . $request->ip(), 90, 60)) {
                return $this->quote($request, 'Please wait a moment before calculating again.', $request->post);
            }
            return $this->quote($request, null, $request->post);
        }
        if (!$this->ctx->service(RateLimiter::class)->attempt('catering-quote:' . $request->ip(), 4, 900)) {
            return $this->quote($request, 'Too many submissions. Please wait and try again.', $request->post);
        }
        $quoteConfig = $this->quoteConfiguration();
        $data = $this->quoteInput($request, $quoteConfig);
        $errors = $this->quoteErrors($data, $quoteConfig);
        [, $items, $packages] = $this->plannerCatalog();
        $plan = (new MenuPlanner())->calculate($request->post, $items, $packages,
            max(1, (int) $this->settings()->get('catering.minimum_guests', 20)));
        $operations = (new EventOperations())->evaluate(
            array_merge($request->post, $data),
            $this->operationsConfiguration($quoteConfig['service_styles']),
            $plan['estimate_cents']
        );
        $this->applyServiceAreaDecision($operations, $data);
        $errors = array_merge($errors, $plan['errors'], $operations['errors']);
        if ($request->string('acknowledged') !== '1') $errors[] = 'Please acknowledge that this is a quote request, not a booking.';
        $data['menu_items'] = is_array($request->input('menu_items')) ? $request->input('menu_items') : [];
        $data['extras'] = is_array($request->input('extras')) ? $request->input('extras') : [];
        $data['package_id'] = $request->string('package_id');
        $data['acknowledged'] = $request->string('acknowledged');
        $data['menu_plan'] = json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $data['operations_plan'] = json_encode($operations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($errors !== []) {
            return $this->quote($request, implode(' ', $errors), $data);
        }
        $this->saveInquiry($request, 'quote', $data);
        return Response::redirect($this->ctx->url($request->basePath, '/request-a-quote', ['sent' => 1]));
    }

    public function adminMenus(Request $request, ?string $error = null, array $old = []): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        [$categories, $items] = $this->menuData(false);
        $editing = null;
        $editId = (int) ($old['id'] ?? $request->string('edit_item'));
        foreach ($items as $item) if ((int) $item['id'] === $editId) $editing = $item;
        return $this->adminPage($request, 'admin-menus', [
            'categories' => $categories, 'items' => $items, 'dishImages' => self::DISH_IMAGES,
            'saved' => $request->string('saved') === '1', 'error' => $error,
            'old' => $old, 'editing' => $editing,
            'dietaryLabels' => MenuPlanner::DIETARY, 'allergenLabels' => MenuPlanner::ALLERGENS,
            'pricingLabels' => MenuPlanner::PRICING,
            'quickManagerUrl' => $this->resourceRoute('menu-items'),
        ], 'Catering Menus');
    }

    public function adminMenusSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        if (!$this->ctx->service(Csrf::class)->validate($request)) return Response::text('Invalid CSRF token.', 419);
        $db = $this->db();
        $action = $request->string('action');
        try {
            if ($action === 'add_category') {
                $name = $request->string('name');
                if ($name === '' || mb_strlen($name) > 190) throw new \InvalidArgumentException('Category name is required and must be at most 190 characters.');
                $db->transaction(function () use ($db, $request, $name): void {
                    $id = $db->insert('catering_menu_categories', [
                        'slug' => $this->uniqueSlug('catering_menu_categories', $name), 'name' => $name,
                        'description' => mb_substr($request->string('description'), 0, 2000),
                        'sort_order' => (int) $request->string('sort_order', '0'), 'visible' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->audit('catering.category.created', (int) $id);
                });
            } elseif (in_array($action, ['add_item', 'update_item'], true)) {
                $name = $request->string('name');
                if ($name === '' || mb_strlen($name) > 190) throw new \InvalidArgumentException('Dish name is required and must be at most 190 characters.');
                $categoryId = (int) $request->string('category_id');
                if (!(bool) $db->fetchColumn('SELECT id FROM `' . $db->table('catering_menu_categories') . '` WHERE id = ?', [$categoryId])) {
                    throw new \InvalidArgumentException('Choose an existing menu category.');
                }
                $dietary = $this->validatedTags($request->input('dietary_tags', []), MenuPlanner::DIETARY);
                $allergens = $this->validatedTags($request->input('allergens', []), MenuPlanner::ALLERGENS);
                $image = $request->string('image');
                if ($image !== '' && !array_key_exists($image, self::DISH_IMAGES)) throw new \InvalidArgumentException('Choose an available dish image.');
                $availability = $this->availabilityInput($request);
                $data = $this->pricingInput($request) + [
                    'category_id' => $categoryId, 'name' => $name,
                    'description' => mb_substr($request->string('description'), 0, 3000),
                    'price_unit' => mb_substr($request->string('price_unit'), 0, 80),
                    'dietary' => mb_substr($request->string('dietary'), 0, 255),
                    'dietary_tags' => json_encode($dietary, JSON_THROW_ON_ERROR),
                    'allergens' => json_encode($allergens, JSON_THROW_ON_ERROR),
                    'allergen_notes' => mb_substr($request->string('allergen_notes'), 0, 1000),
                    'image' => $image, 'featured' => $request->string('featured') === '1' ? 1 : 0,
                    'sort_order' => (int) $request->string('sort_order', '0'),
                ] + $availability;
                $db->transaction(function () use ($db, $request, $action, $name, $data): void {
                    if ($action === 'update_item') {
                        $id = $this->existingId($request, 'catering_menu_items');
                        $db->update('catering_menu_items', $data, 'id = ?', [$id]);
                    } else {
                        $id = (int) $db->insert('catering_menu_items', $data + [
                            'slug' => $this->uniqueSlug('catering_menu_items', $name), 'visible' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    $this->audit('catering.menu.' . $action, $id);
                });
            } elseif (in_array($action, ['toggle_item', 'delete_item'], true)) {
                $db->transaction(function () use ($db, $request, $action): void {
                    $id = $this->existingId($request, 'catering_menu_items');
                    if ($action === 'toggle_item') {
                        $db->run('UPDATE `' . $db->table('catering_menu_items') . '` SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
                    } else {
                        if ($request->string('confirm_delete') !== '1') throw new \InvalidArgumentException('Confirm dish deletion.');
                        if ($db->tableExists('catering_menu_group_items')) {
                            $db->run('DELETE FROM `' . $db->table('catering_menu_group_items') . '` WHERE item_id = ?', [$id]);
                        }
                        $db->run('DELETE FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$id]);
                    }
                    $this->audit('catering.menu.' . $action, $id);
                });
            } else throw new \InvalidArgumentException('Unknown menu action.');
        } catch (\InvalidArgumentException $e) {
            return $this->adminMenus($request, $e->getMessage(), $request->post);
        }
        return Response::redirect($this->ctx->url($request->basePath, '/admin/catering/menus', ['saved' => 1]));
    }

    public function adminPackages(Request $request, ?string $error = null, array $old = []): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        $packages = $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_packages') . '` ORDER BY sort_order, name');
        [$categories] = $this->menuData(false);
        $editing = null;
        $editId = (int) ($old['id'] ?? $request->string('edit_package'));
        foreach ($packages as $package) if ((int) $package['id'] === $editId) $editing = $package;
        return $this->adminPage($request, 'admin-packages', [
            'packages' => $packages, 'categories' => $categories,
            'saved' => $request->string('saved') === '1', 'error' => $error,
            'old' => $old, 'editing' => $editing, 'pricingLabels' => MenuPlanner::PRICING,
            'menuGroups' => $this->menuGroups(false),
            'quickManagerUrl' => $this->resourceRoute('packages'),
        ], 'Catering Packages');
    }

    public function adminPackagesSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        if (!$this->ctx->service(Csrf::class)->validate($request)) return Response::text('Invalid CSRF token.', 419);
        $db = $this->db();
        $action = $request->string('action');
        try {
            if (in_array($action, ['add_package', 'update_package'], true)) {
                $name = $request->string('name');
                if ($name === '' || mb_strlen($name) > 190) throw new \InvalidArgumentException('Package name is required and must be at most 190 characters.');
                $min = $this->boundedInt($request->string('guest_min'), 1, 10000, 'Minimum guests');
                $max = $request->string('guest_max') === '' ? null : $this->boundedInt($request->string('guest_max'), $min, 10000, 'Maximum guests');
                $menuGroupId = $request->string('menu_group_id') === '' ? null : (int) $request->string('menu_group_id');
                if ($menuGroupId !== null && !(bool) $db->fetchColumn(
                    'SELECT id FROM `' . $db->table('catering_menu_groups') . '` WHERE id = ?', [$menuGroupId]
                )) throw new \InvalidArgumentException('Choose an existing menu collection.');
                $availability = $this->availabilityInput($request);
                $data = $this->pricingInput($request) + [
                    'name' => $name, 'eyebrow' => mb_substr($request->string('eyebrow'), 0, 120),
                    'description' => mb_substr($request->string('description'), 0, 3000),
                    'price_note' => mb_substr($request->string('price_note'), 0, 120),
                    'guest_min' => $min, 'guest_max' => $max,
                    'inclusions' => mb_substr($request->string('inclusions'), 0, 5000),
                    'choice_rules' => json_encode($this->packageRules($request), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'menu_group_id' => $menuGroupId,
                    'featured' => $request->string('featured') === '1' ? 1 : 0,
                    'sort_order' => (int) $request->string('sort_order', '0'),
                ] + $availability;
                $db->transaction(function () use ($db, $request, $action, $name, $data): void {
                    if ($action === 'update_package') {
                        $id = $this->existingId($request, 'catering_packages');
                        $db->update('catering_packages', $data, 'id = ?', [$id]);
                    } else {
                        $id = (int) $db->insert('catering_packages', $data + [
                            'slug' => $this->uniqueSlug('catering_packages', $name), 'visible' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    $this->audit('catering.package.' . $action, $id);
                });
            } elseif (in_array($action, ['toggle_package', 'delete_package'], true)) {
                $db->transaction(function () use ($db, $request, $action): void {
                    $id = $this->existingId($request, 'catering_packages');
                    if ($action === 'toggle_package') {
                        $db->run('UPDATE `' . $db->table('catering_packages') . '` SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
                    } else {
                        if ($request->string('confirm_delete') !== '1') throw new \InvalidArgumentException('Confirm package deletion.');
                        $db->run('DELETE FROM `' . $db->table('catering_packages') . '` WHERE id = ?', [$id]);
                    }
                    $this->audit('catering.package.' . $action, $id);
                });
            } else throw new \InvalidArgumentException('Unknown package action.');
        } catch (\InvalidArgumentException $e) {
            return $this->adminPackages($request, $e->getMessage(), $request->post);
        }
        try {
            (new CommerceIntegration($this->ctx, $this->db(), $this->settings()))->syncProducts();
        } catch (\RuntimeException $e) {
            return $this->adminPackages($request, $e->getMessage(), $request->post);
        }
        return Response::redirect($this->ctx->url($request->basePath, '/admin/catering/packages', ['saved' => 1]));
    }

    public function adminMenuGroups(Request $request, ?string $error = null, array $old = []): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        [, $items] = $this->menuData(false);
        $groups = $this->menuGroups(false);
        $editing = null;
        $memberIds = [];
        $editId = (int) ($old['id'] ?? $request->string('edit_group'));
        foreach ($groups as $group) if ((int) $group['id'] === $editId) $editing = $group;
        if (is_array($old['item_ids'] ?? null)) {
            $memberIds = array_map('intval', $old['item_ids']);
        } elseif ($editing !== null) {
            $memberIds = array_map('intval', array_column($editing['items'], 'id'));
        }
        return $this->adminPage($request, 'admin-menu-groups', [
            'groups' => $groups, 'items' => $items, 'editing' => $editing,
            'memberIds' => $memberIds, 'old' => $old, 'error' => $error,
            'saved' => $request->string('saved') === '1',
        ], 'Catering Menu Collections');
    }

    public function adminMenuGroupsSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        if (!$this->ctx->service(Csrf::class)->validate($request)) return Response::text('Invalid CSRF token.', 419);
        $db = $this->db();
        $action = $request->string('action');
        try {
            if (in_array($action, ['add_group', 'update_group'], true)) {
                $name = $request->string('name');
                if ($name === '' || mb_strlen($name) > 190) throw new \InvalidArgumentException('Collection name is required and must be at most 190 characters.');
                $availability = $this->availabilityInput($request);
                $submitted = $request->input('item_ids');
                $submitted = is_array($submitted) ? $submitted : [];
                if (count($submitted) > 100) throw new \InvalidArgumentException('Choose no more than 100 dishes.');
                $itemIds = [];
                foreach ($submitted as $value) {
                    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($id === false || isset($itemIds[(int) $id])) throw new \InvalidArgumentException('Choose unique existing dishes.');
                    if (!(bool) $db->fetchColumn('SELECT id FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [(int) $id])) {
                        throw new \InvalidArgumentException('Choose unique existing dishes.');
                    }
                    $itemIds[(int) $id] = (int) $id;
                }
                $data = [
                    'name' => $name,
                    'description' => mb_substr($request->string('description'), 0, 3000),
                    'sort_order' => (int) $request->string('sort_order', '0'),
                ] + $availability;
                $db->transaction(function () use ($db, $request, $action, $name, $data, $itemIds): void {
                    if ($action === 'update_group') {
                        $id = $this->existingId($request, 'catering_menu_groups');
                        $db->update('catering_menu_groups', $data, 'id = ?', [$id]);
                    } else {
                        $id = (int) $db->insert('catering_menu_groups', $data + [
                            'slug' => $this->uniqueSlug('catering_menu_groups', $name),
                            'visible' => 1, 'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                    $db->run('DELETE FROM `' . $db->table('catering_menu_group_items') . '` WHERE group_id = ?', [$id]);
                    foreach (array_values($itemIds) as $order => $itemId) {
                        $db->insert('catering_menu_group_items', [
                            'group_id' => $id, 'item_id' => $itemId, 'sort_order' => ($order + 1) * 10,
                        ]);
                    }
                    $this->audit('catering.menu_group.' . $action, $id);
                });
            } elseif (in_array($action, ['toggle_group', 'delete_group'], true)) {
                $db->transaction(function () use ($db, $request, $action): void {
                    $id = $this->existingId($request, 'catering_menu_groups');
                    if ($action === 'toggle_group') {
                        $db->run('UPDATE `' . $db->table('catering_menu_groups') . '` SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
                    } else {
                        if ($request->string('confirm_delete') !== '1') throw new \InvalidArgumentException('Confirm collection deletion.');
                        $db->update('catering_packages', ['menu_group_id' => null], 'menu_group_id = ?', [$id]);
                        $db->run('DELETE FROM `' . $db->table('catering_menu_group_items') . '` WHERE group_id = ?', [$id]);
                        $db->run('DELETE FROM `' . $db->table('catering_menu_groups') . '` WHERE id = ?', [$id]);
                    }
                    $this->audit('catering.menu_group.' . $action, $id);
                });
            } else throw new \InvalidArgumentException('Unknown menu collection action.');
        } catch (\InvalidArgumentException $e) {
            return $this->adminMenuGroups($request, $e->getMessage(), $request->post);
        }
        return Response::redirect($this->ctx->url($request->basePath, '/admin/catering/menu-groups', ['saved' => 1]));
    }

    public function adminEvents(Request $request, ?string $error = null): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        $workspace = new EventWorkspace($this->db());
        $workspace->synchronizeAcceptedQuotes($this->ctx->service(QuoteService::class), $this->ctx->packageId());
        return $this->adminPage($request, 'admin-events', [
            'events' => $workspace->all(), 'inquiries' => $workspace->availableInquiries(),
            'tastings' => $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_tastings') . '` ORDER BY created_at DESC,id DESC LIMIT 100'),
            'saved' => $request->string('saved') === '1', 'error' => $error,
        ], 'Catering Events');
    }

    public function adminEvent(Request $request, ?string $error = null): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        $id = (int) ($request->attributes['id'] ?? 0);
        $workspace = new EventWorkspace($this->db());
        $event = $workspace->find($id);
        if ($event === null) return Response::notFound();
        $quote = !empty($event['core_quote_id'])
            ? $this->ctx->service(QuoteService::class)->find((int)$event['core_quote_id'], $this->ctx->packageId())
            : null;
        return $this->adminPage($request, 'admin-event', [
            'event' => $event, 'quote' => $quote, 'production' => $workspace->productionPlan($event, $quote),
            'states' => EventWorkspace::STATES, 'taskStatuses' => EventWorkspace::TASK_STATUSES,
            'staffStatuses' => EventWorkspace::STAFF_STATUSES, 'equipmentStatuses' => EventWorkspace::EQUIPMENT_STATUSES,
            'costCategories' => EventWorkspace::COST_CATEGORIES,
            'saved' => $request->string('saved') === '1', 'error' => $error,
        ], 'Event Operations');
    }

    public function adminEventsSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        if (!$this->ctx->service(Csrf::class)->validate($request)) return Response::text('Invalid CSRF token.', 419);
        $workspace = new EventWorkspace($this->db());
        $action = $request->string('action');
        $eventId = 0;
        try {
            if ($action === 'create_event') {
                $inquiryId = $this->boundedInt($request->string('inquiry_id'), 1, 999999999, 'Inquiry');
                $eventId = $workspace->createFromInquiryId($inquiryId) ?? 0;
                if ($eventId < 1) throw new \InvalidArgumentException('Choose an available catering quote request.');
                $this->audit('catering.event.created', $eventId);
            } else {
                $eventId = $this->boundedInt($request->string('event_id'), 1, 999999999, 'Event');
                $event = $workspace->find($eventId);
                if ($event === null) throw new \InvalidArgumentException('This event no longer exists.');
                if ($action === 'update_event') {
                    $state = $request->string('state');
                    if (!in_array($state, EventWorkspace::STATES, true)) throw new \InvalidArgumentException('Choose a valid event state.');
                    $eventDate = $this->optionalDate($request->string('event_date'), 'Event date');
                    $headcountDue = $this->optionalDate($request->string('final_headcount_due'), 'Final headcount deadline');
                    if ($headcountDue !== null && $eventDate !== null && $headcountDue > $eventDate) throw new \InvalidArgumentException('Final headcount must be due on or before the event date.');
                    $guestCount = $this->optionalBoundedInt($request->string('final_guest_count'), 1, 10000, 'Final guest count');
                    $data = [
                        'state'=>$state, 'title'=>mb_substr($request->string('title'),0,190),
                        'event_date'=>$eventDate, 'event_time'=>mb_substr($request->string('event_time'),0,20) ?: null,
                        'final_guest_count'=>$guestCount, 'final_headcount_due'=>$headcountDue,
                        'venue'=>mb_substr($request->string('venue'),0,255) ?: null,
                        'run_of_show'=>mb_substr($request->string('run_of_show'),0,10000) ?: null,
                        'internal_notes'=>mb_substr($request->string('internal_notes'),0,10000) ?: null,
                        'delivery_status'=>mb_substr($request->string('delivery_status','unassigned'),0,24),
                        'driver_name'=>mb_substr($request->string('driver_name'),0,190) ?: null,
                        'route_order'=>$this->optionalBoundedInt($request->string('route_order'),1,999,'Route order'),
                        'setup_instructions'=>mb_substr($request->string('setup_instructions'),0,5000) ?: null,
                        'proof_reference'=>mb_substr($request->string('proof_reference'),0,500) ?: null,
                        'revision'=>(int)$event['revision']+1, 'updated_at'=>date('Y-m-d H:i:s'),
                    ];
                    if ($data['title'] === '') throw new \InvalidArgumentException('Event title is required.');
                    if (!in_array($data['delivery_status'], ['unassigned','scheduled','out_for_delivery','delivered','completed','cancelled'], true)) throw new \InvalidArgumentException('Choose a valid delivery status.');
                    $this->db()->transaction(function () use ($eventId, $data, $event): void {
                        $this->db()->update('catering_events', $data, 'id=?', [$eventId]);
                        $changes=[];
                        foreach (['state','event_date','event_time','final_guest_count','final_headcount_due','venue','delivery_status','driver_name'] as $key) {
                            if ((string)($event[$key]??'') !== (string)($data[$key]??'')) $changes[] = str_replace('_',' ',$key) . ' changed';
                        }
                        $this->db()->insert('catering_event_revisions', ['event_id'=>$eventId,'revision'=>$data['revision'],'summary'=>$changes!==[]?implode('; ',$changes):'Planning notes updated','acknowledged_at'=>null,'created_at'=>date('Y-m-d H:i:s')]);
                    });
                    $this->audit('catering.event.updated', $eventId);
                } elseif ($action === 'add_task') {
                    $title=mb_substr($request->string('title'),0,190); if($title==='') throw new \InvalidArgumentException('Task title is required.');
                    $this->db()->insert('catering_event_tasks',['event_id'=>$eventId,'category'=>mb_substr($request->string('category','general'),0,40),'title'=>$title,'due_date'=>$this->optionalDate($request->string('due_date'),'Task due date'),'assignee'=>mb_substr($request->string('assignee'),0,190)?:null,'status'=>'open','notes'=>mb_substr($request->string('notes'),0,3000)?:null,'sort_order'=>0,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null]);
                    $this->audit('catering.event.task_added', $eventId);
                } elseif ($action === 'update_task') {
                    $this->updateChildStatus($request,'catering_event_tasks',EventWorkspace::TASK_STATUSES,$eventId);
                } elseif ($action === 'add_staff') {
                    $position=mb_substr($request->string('position'),0,120); if($position==='') throw new \InvalidArgumentException('Staff position is required.');
                    $this->db()->insert('catering_event_staff',['event_id'=>$eventId,'position'=>$position,'staff_name'=>mb_substr($request->string('staff_name'),0,190)?:null,'starts_at'=>mb_substr($request->string('starts_at'),0,30)?:null,'ends_at'=>mb_substr($request->string('ends_at'),0,30)?:null,'status'=>$request->string('staff_name')!==''?'assigned':'needed','hourly_cost_minor'=>MenuPlanner::cents($request->string('hourly_cost','0')),'notes'=>mb_substr($request->string('notes'),0,3000)?:null,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null]);
                    $this->audit('catering.event.staff_added', $eventId);
                } elseif ($action === 'update_staff') {
                    $this->updateChildStatus($request,'catering_event_staff',EventWorkspace::STAFF_STATUSES,$eventId);
                } elseif ($action === 'add_equipment') {
                    $name=mb_substr($request->string('item_name'),0,190); if($name==='') throw new \InvalidArgumentException('Equipment name is required.');
                    $this->db()->insert('catering_event_equipment',['event_id'=>$eventId,'item_name'=>$name,'quantity'=>$this->boundedInt($request->string('quantity','1'),1,10000,'Equipment quantity'),'packed_quantity'=>0,'returned_quantity'=>0,'replacement_cost_minor'=>MenuPlanner::cents($request->string('replacement_cost','0')),'status'=>'needed','notes'=>mb_substr($request->string('notes'),0,3000)?:null,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null]);
                    $this->audit('catering.event.equipment_added', $eventId);
                } elseif ($action === 'update_equipment') {
                    $id=$this->childId($request,'catering_event_equipment',$eventId); $status=$request->string('status');
                    if(!in_array($status,EventWorkspace::EQUIPMENT_STATUSES,true)) throw new \InvalidArgumentException('Choose a valid equipment status.');
                    $row=$this->db()->fetch('SELECT quantity FROM `'.$this->db()->table('catering_event_equipment').'` WHERE id=?',[$id]);
                    $packed=$this->boundedInt($request->string('packed_quantity','0'),0,(int)$row['quantity'],'Packed quantity');
                    $returned=$this->boundedInt($request->string('returned_quantity','0'),0,(int)$row['quantity'],'Returned quantity');
                    $this->db()->update('catering_event_equipment',['status'=>$status,'packed_quantity'=>$packed,'returned_quantity'=>$returned,'updated_at'=>date('Y-m-d H:i:s')],'id=?',[$id]);
                    $this->audit('catering.event.equipment_updated',$eventId);
                } elseif ($action === 'add_cost') {
                    $category=$request->string('category');
                    if(!in_array($category,EventWorkspace::COST_CATEGORIES,true)) throw new \InvalidArgumentException('Choose a valid cost category.');
                    $description=mb_substr($request->string('description'),0,190);
                    if($description==='') throw new \InvalidArgumentException('Cost description is required.');
                    $id=(int)$this->db()->insert('catering_event_costs',[
                        'event_id'=>$eventId,'category'=>$category,'description'=>$description,
                        'estimated_minor'=>MenuPlanner::cents($request->string('estimated','0')),
                        'actual_minor'=>$this->optionalMoney($request->string('actual')),
                        'notes'=>mb_substr($request->string('notes'),0,3000)?:null,
                        'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
                    ]);
                    $this->audit('catering.event.cost_added',$id);
                } elseif ($action === 'update_cost') {
                    $id=$this->childId($request,'catering_event_costs',$eventId);
                    $category=$request->string('category');
                    if(!in_array($category,EventWorkspace::COST_CATEGORIES,true)) throw new \InvalidArgumentException('Choose a valid cost category.');
                    $description=mb_substr($request->string('description'),0,190);
                    if($description==='') throw new \InvalidArgumentException('Cost description is required.');
                    $this->db()->update('catering_event_costs',[
                        'category'=>$category,'description'=>$description,
                        'estimated_minor'=>MenuPlanner::cents($request->string('estimated','0')),
                        'actual_minor'=>$this->optionalMoney($request->string('actual')),
                        'notes'=>mb_substr($request->string('notes'),0,3000)?:null,
                        'updated_at'=>date('Y-m-d H:i:s'),
                    ],'id=?',[$id]);
                    $this->audit('catering.event.cost_updated',$id);
                } elseif ($action === 'acknowledge_revision') {
                    $id=$this->childId($request,'catering_event_revisions',$eventId);
                    $this->db()->update('catering_event_revisions',['acknowledged_at'=>date('Y-m-d H:i:s')],'id=?',[$id]);
                    $this->audit('catering.event.revision_acknowledged',$eventId);
                } elseif (in_array($action, ['generate_proposal','generate_beo'], true)) {
                    if(empty($event['core_quote_id'])) throw new \InvalidArgumentException('Link an accepted Core quote before generating event documents.');
                    $quote=$this->ctx->service(QuoteService::class)->find((int)$event['core_quote_id'],$this->ctx->packageId());
                    if($quote===null) throw new \InvalidArgumentException('The linked Core quote is unavailable.');
                    $business=$this->ctx->service(BusinessProfile::class)->get();
                    $name=(string)($business['name']?:'Catering business');
                    $kind=$action==='generate_proposal'?'proposal':'summary';
                    $title=$action==='generate_proposal'?'Catering Proposal — '.$event['title']:'Banquet Event Order — '.$event['title'].' — Revision '.(int)$event['revision'];
                    $html=$action==='generate_proposal'?EventDocuments::proposal($event,$quote,$name):EventDocuments::beo($event,$quote,$name);
                    [, $documentError]=$this->ctx->quoteDocument((int)$quote['id'],$kind,$title,$html,$this->ctx->service(AuthService::class)->user()?->id);
                    if($documentError!==null) throw new \InvalidArgumentException((string)$documentError);
                    $this->audit('catering.event.document_generated',$eventId);
                } elseif ($action === 'schedule_headcount_reminder') {
                    if(empty($event['core_quote_id'])) throw new \InvalidArgumentException('Link an accepted Core quote before scheduling reminders.');
                    $due=trim($request->string('reminder_due'));
                    $message=mb_substr($request->string('reminder_message'),0,5000);
                    [, $reminderError]=$this->ctx->quoteReminder((int)$event['core_quote_id'],$due,(string)$event['customer_email'],$message,'email');
                    if($reminderError!==null) throw new \InvalidArgumentException((string)$reminderError);
                    $this->audit('catering.event.reminder_scheduled',$eventId);
                } else throw new \InvalidArgumentException('Unknown event action.');
            }
        } catch (\InvalidArgumentException $e) {
            return $eventId > 0 ? $this->adminEvent($request, $e->getMessage()) : $this->adminEvents($request, $e->getMessage());
        }
        return Response::redirect($request->routeUrl('/admin/catering/events/' . $eventId, ['saved'=>1]));
    }

    public function adminTastingsSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        if (!$this->ctx->service(Csrf::class)->validate($request)) return Response::text('Invalid CSRF token.',419);
        try {
            $id=$this->existingId($request,'catering_tastings'); $status=$request->string('status');
            if(!in_array($status,EventWorkspace::TASTING_STATUSES,true)) throw new \InvalidArgumentException('Choose a valid tasting status.');
            $eligibility=$request->string('eligibility');
            if(!in_array($eligibility,['review','eligible','ineligible'],true)) throw new \InvalidArgumentException('Choose a valid eligibility state.');
            $preferredDate=$this->optionalDate($request->string('preferred_date'),'Tasting date');
            if($status==='scheduled') {
                if($eligibility!=='eligible') throw new \InvalidArgumentException('Mark the tasting eligible before scheduling it.');
                if($preferredDate===null) throw new \InvalidArgumentException('Choose a tasting date before scheduling.');
                $lead=max(0,min(365,(int)$this->settings()->get('catering.tasting_lead_days',14)));
                $date=new \DateTimeImmutable($preferredDate);
                if($date < (new \DateTimeImmutable('today'))->modify('+'.$lead.' days')) throw new \InvalidArgumentException('Scheduled tastings require at least '.$lead.' days of lead time.');
                $start=$date->modify('monday this week')->format('Y-m-d');
                $end=$date->modify('sunday this week')->format('Y-m-d');
                $capacity=max(1,min(100,(int)$this->settings()->get('catering.tasting_capacity',8)));
                $scheduled=(int)$this->db()->fetchColumn('SELECT COUNT(*) FROM `'.$this->db()->table('catering_tastings').'` WHERE status=? AND preferred_date>=? AND preferred_date<=? AND id<>?',['scheduled',$start,$end,$id]);
                if($scheduled >= $capacity) throw new \InvalidArgumentException('That week has reached the configured tasting capacity of '.$capacity.'.');
            }
            $this->db()->update('catering_tastings',['status'=>$status,'eligibility'=>$eligibility,'preferred_date'=>$preferredDate,'notes'=>mb_substr($request->string('notes'),0,5000)?:null,'updated_at'=>date('Y-m-d H:i:s')],'id=?',[$id]);
            $this->audit('catering.tasting.updated',$id);
        } catch(\InvalidArgumentException $e) { return $this->adminEvents($request,$e->getMessage()); }
        return Response::redirect($request->routeUrl('/admin/catering/events',['saved'=>1]));
    }

    public function adminProduction(Request $request, ?string $error = null): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        $workspace = new EventWorkspace($this->db());
        $events = $workspace->all();
        return $this->adminPage($request,'admin-production',[
            'ingredients'=>$this->db()->fetchAll('SELECT * FROM `'.$this->db()->table('catering_ingredients').'` ORDER BY active DESC,name,unit'),
            'recipes'=>$this->db()->fetchAll('SELECT r.*,m.name menu_name,i.name ingredient_name,i.unit FROM `'.$this->db()->table('catering_recipe_ingredients').'` r INNER JOIN `'.$this->db()->table('catering_menu_items').'` m ON m.id=r.menu_item_id INNER JOIN `'.$this->db()->table('catering_ingredients').'` i ON i.id=r.ingredient_id ORDER BY m.name,i.name'),
            'menuItems'=>$this->db()->fetchAll('SELECT id,name FROM `'.$this->db()->table('catering_menu_items').'` ORDER BY name'),
            'purchasing'=>$workspace->purchasingPlan($events),
            'saved'=>$request->string('saved')==='1','error'=>$error,
        ],'Production Catalog');
    }

    public function adminProductionSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) return $forbidden;
        if (!$this->ctx->service(Csrf::class)->validate($request)) return Response::text('Invalid CSRF token.',419);
        try {
            $action=$request->string('action');
            if(in_array($action,['add_ingredient','update_ingredient'],true)) {
                $name=mb_substr($request->string('name'),0,190); $unit=mb_substr($request->string('unit'),0,40);
                if($name===''||$unit==='') throw new \InvalidArgumentException('Ingredient name and unit are required.');
                $tags=$this->validatedTags($request->input('allergens',[]),MenuPlanner::ALLERGENS);
                $active=$action==='add_ingredient'||$request->string('active')==='1'?1:0;
                $data=['name'=>$name,'unit'=>$unit,'cost_minor_per_unit'=>MenuPlanner::cents($request->string('cost','0')),'supplier'=>mb_substr($request->string('supplier'),0,190)?:null,'allergens'=>json_encode($tags,JSON_THROW_ON_ERROR),'active'=>$active,'updated_at'=>date('Y-m-d H:i:s')];
                if($action==='update_ingredient') {
                    $id=$this->existingId($request,'catering_ingredients');
                    $this->db()->update('catering_ingredients',$data,'id=?',[$id]);
                } else $id=(int)$this->db()->insert('catering_ingredients',$data+['created_at'=>date('Y-m-d H:i:s')]);
                $this->audit('catering.ingredient.'.($action==='add_ingredient'?'created':'updated'),$id);
            } elseif(in_array($action,['add_recipe_ingredient','update_recipe_ingredient'],true)) {
                $menuId=$this->boundedInt($request->string('menu_item_id'),1,999999999,'Menu item');
                $ingredientId=$this->boundedInt($request->string('ingredient_id'),1,999999999,'Ingredient');
                if(!(bool)$this->db()->fetchColumn('SELECT COUNT(*) FROM `'.$this->db()->table('catering_menu_items').'` WHERE id=?',[$menuId]) || !(bool)$this->db()->fetchColumn('SELECT COUNT(*) FROM `'.$this->db()->table('catering_ingredients').'` WHERE id=? AND active=1',[$ingredientId])) throw new \InvalidArgumentException('Choose an available menu item and ingredient.');
                $quantity=$request->string('quantity_per_guest'); $waste=$request->string('waste_percent','0');
                if(!preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D',$quantity)||(float)$quantity<=0) throw new \InvalidArgumentException('Quantity per guest must be a positive number with up to four decimals.');
                if(!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D',$waste)||(float)$waste>100) throw new \InvalidArgumentException('Waste percentage must be between 0 and 100.');
                $station=mb_substr($request->string('station','Kitchen'),0,80); if($station==='') throw new \InvalidArgumentException('Preparation station is required.');
                $data=['menu_item_id'=>$menuId,'ingredient_id'=>$ingredientId,'quantity_per_guest'=>(float)$quantity,'waste_percent'=>(float)$waste,
                    'station'=>$station,'prep_lead_days'=>$this->boundedInt($request->string('prep_lead_days','0'),0,365,'Preparation lead days'),
                    'label_notes'=>mb_substr($request->string('label_notes'),0,500)?:null,'prep_notes'=>mb_substr($request->string('prep_notes'),0,3000)?:null,'updated_at'=>date('Y-m-d H:i:s')];
                if($action==='update_recipe_ingredient') {
                    $id=$this->existingId($request,'catering_recipe_ingredients');
                    if((bool)$this->db()->fetchColumn('SELECT COUNT(*) FROM `'.$this->db()->table('catering_recipe_ingredients').'` WHERE menu_item_id=? AND ingredient_id=? AND id<>?',[$menuId,$ingredientId,$id])) throw new \InvalidArgumentException('That ingredient is already assigned to this recipe.');
                    $this->db()->update('catering_recipe_ingredients',$data,'id=?',[$id]);
                } else {
                    if((bool)$this->db()->fetchColumn('SELECT COUNT(*) FROM `'.$this->db()->table('catering_recipe_ingredients').'` WHERE menu_item_id=? AND ingredient_id=?',[$menuId,$ingredientId])) throw new \InvalidArgumentException('That ingredient is already assigned to this recipe.');
                    $id=(int)$this->db()->insert('catering_recipe_ingredients',$data+['created_at'=>date('Y-m-d H:i:s')]);
                }
                $this->audit('catering.recipe_ingredient.'.($action==='add_recipe_ingredient'?'created':'updated'),$id);
            } elseif($action==='delete_recipe_ingredient') {
                $id=$this->existingId($request,'catering_recipe_ingredients');
                if($request->string('confirm_delete')!=='1') throw new \InvalidArgumentException('Confirm recipe-line removal.');
                $this->db()->run('DELETE FROM `'.$this->db()->table('catering_recipe_ingredients').'` WHERE id=?',[$id]);
                $this->audit('catering.recipe_ingredient.deleted',$id);
            } else throw new \InvalidArgumentException('Unknown production action.');
        } catch(\Throwable $e) {
            if(!$e instanceof \InvalidArgumentException) $e=new \InvalidArgumentException('That production record could not be saved. It may already exist.');
            return $this->adminProduction($request,$e->getMessage());
        }
        return Response::redirect($request->routeUrl('/admin/catering/production',['saved'=>1]));
    }

    public function legacyInquiryRedirect(Request $request): Response
    {
        if ($forbidden = $this->guard('inquiries.manage')) return $forbidden;
        return Response::redirect($request->routeUrl('/admin/inquiries'));
    }

    public function adminSettings(Request $request, ?string $error = null, array $old = []): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        $settings = $this->settings();
        $quoteConfig = $this->quoteConfiguration();
        $operations = $this->operationsConfiguration($quoteConfig['service_styles']);
        $postedFields = is_array($old['quote_fields'] ?? null) ? $old['quote_fields'] : null;
        $fieldValues = $quoteConfig['fields'];
        if ($postedFields !== null) {
            foreach (self::QUOTE_FIELDS as $key => $_label) {
                $fieldValues[$key] = [
                    'enabled' => isset($postedFields[$key]['enabled']),
                    'required' => isset($postedFields[$key]['required']),
                ];
            }
        }
        return $this->adminPage($request, 'admin-settings', [
            'values' => [
                'travel_note' => $old['travel_note'] ?? $settings->get('catering.travel_note', 'Travel and delivery fees may apply outside our primary service area.'),
                'minimum_guests' => $old['minimum_guests'] ?? $settings->get('catering.minimum_guests', '20'),
                'lead_time' => $old['lead_time'] ?? $settings->get('catering.lead_time', '14'),
                'quote_expiry' => $old['quote_expiry'] ?? $settings->get('catering.quote_expiry', '14'),
                'event_types' => $old['event_types'] ?? implode("\n", $quoteConfig['event_types']),
                'service_styles' => $old['service_styles'] ?? implode("\n", $quoteConfig['service_styles']),
                'budget_ranges' => $old['budget_ranges'] ?? implode("\n", $quoteConfig['budget_ranges']),
                'capacity_guests' => $old['capacity_guests'] ?? $operations['capacity_guests'],
                'service_style_rules' => $old['service_style_rules'] ?? $this->formatServiceStyleRules($operations['styles']),
                'optional_extras' => $old['optional_extras'] ?? $this->formatOptionalExtras($operations['extras']),
                'blackout_dates' => $old['blackout_dates'] ?? implode("\n", $operations['blackout_dates']),
                'pickup_windows' => $old['pickup_windows'] ?? implode("\n", $operations['windows']['pickup']),
                'delivery_windows' => $old['delivery_windows'] ?? implode("\n", $operations['windows']['delivery']),
                'delivery_zones' => $old['delivery_zones'] ?? $this->formatDeliveryZones($operations['zones']),
                'tasting_capacity' => $old['tasting_capacity'] ?? $settings->get('catering.tasting_capacity', '8'),
                'tasting_lead_days' => $old['tasting_lead_days'] ?? $settings->get('catering.tasting_lead_days', '14'),
                'ordering_mode' => $old['ordering_mode'] ?? $settings->get('catering.ordering_mode', 'quote_only'),
            ],
            'quoteFields' => $fieldValues,
            'quoteFieldLabels' => self::QUOTE_FIELDS,
            'packages' => $this->db()->fetchAll('SELECT id,name,price_cents,price_note,visible FROM `' . $this->db()->table('catering_packages') . '` ORDER BY sort_order,name'),
            'directOrderPackageIds' => is_array($old['direct_order_package_ids'] ?? null)
                ? array_map('intval', $old['direct_order_package_ids'])
                : (new CommerceIntegration($this->ctx, $this->db(), $settings))->eligiblePackageIds(),
            'themePreviewUrl' => $this->ctx->url($request->basePath, '/', ['theme_preview' => 1]),
            'saved' => $request->string('saved') === '1',
            'error' => $error,
        ], 'Catering Settings');
    }

    public function adminSettingsSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        if (!$this->ctx->service(Csrf::class)->validate($request)) {
            return Response::text('Invalid CSRF token.', 419);
        }
        $settings = $this->settings();
        try {
            $eventTypes = $this->parseSettingList($request->string('event_types'), 'event types');
            $serviceStyles = $this->parseSettingList($request->string('service_styles'), 'service styles');
            $budgetRanges = $this->parseSettingList($request->string('budget_ranges'), 'budget ranges');
            $capacityGuests = $this->boundedInt($request->string('capacity_guests', '10000'), 1, 10000, 'Event capacity');
            $serviceRules = $this->parseServiceStyleRules($request->string('service_style_rules'), $serviceStyles);
            $optionalExtras = $this->parseOptionalExtras($request->string('optional_extras'));
            $blackoutDates = $this->parseBlackoutDates($request->string('blackout_dates'));
            $pickupWindows = $this->parseSettingList($request->string('pickup_windows'), 'pickup windows');
            $deliveryWindows = $this->parseSettingList($request->string('delivery_windows'), 'delivery windows');
            $deliveryZones = $this->parseDeliveryZones($request->string('delivery_zones'));
            $tastingCapacity = $this->boundedInt($request->string('tasting_capacity', '8'), 1, 100, 'Weekly tasting capacity');
            $tastingLeadDays = $this->boundedInt($request->string('tasting_lead_days', '14'), 0, 365, 'Tasting lead time');
            $orderingMode = $request->string('ordering_mode', 'quote_only');
            if (!in_array($orderingMode, CommerceIntegration::MODES, true)) throw new \InvalidArgumentException('Choose a valid ordering mode.');
            $eligibleInput = $request->input('direct_order_package_ids', []);
            $eligibleInput = is_array($eligibleInput) ? $eligibleInput : [];
            $directPackageIds = [];
            foreach ($eligibleInput as $value) {
                $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false || isset($directPackageIds[(int) $id])) throw new \InvalidArgumentException('Choose unique direct-order packages.');
                $package = $this->db()->fetch('SELECT id,price_cents,price_note FROM `' . $this->db()->table('catering_packages') . '` WHERE id=?', [(int) $id]);
                if (!$package || (int) $package['price_cents'] < 1 || !str_contains(mb_strtolower((string) $package['price_note']), 'per guest')) {
                    throw new \InvalidArgumentException('Direct-order packages require a configured per-guest price.');
                }
                $directPackageIds[(int) $id] = (int) $id;
            }
            $postedFields = $request->input('quote_fields');
            $postedFields = is_array($postedFields) ? $postedFields : [];
            $fieldConfig = [];
            foreach (self::QUOTE_FIELDS as $key => $_label) {
                $enabled = isset($postedFields[$key]['enabled']);
                $fieldConfig[$key] = [
                    'enabled' => $enabled,
                    'required' => $enabled && isset($postedFields[$key]['required']),
                ];
            }
            $this->db()->transaction(function () use ($settings, $request, $eventTypes, $serviceStyles, $budgetRanges,
                $capacityGuests, $serviceRules, $optionalExtras, $blackoutDates, $pickupWindows, $deliveryWindows,
                $deliveryZones, $fieldConfig, $tastingCapacity, $tastingLeadDays, $orderingMode, $directPackageIds): void {
                $settings->set('catering.travel_note', mb_substr($request->string('travel_note'), 0, 1000));
                $settings->set('catering.minimum_guests', max(1, min(10000, (int) $request->string('minimum_guests', '20'))));
                $settings->set('catering.lead_time', max(0, min(3650, (int) $request->string('lead_time', '14'))));
                $settings->set('catering.quote_expiry', max(1, min(365, (int) $request->string('quote_expiry', '14'))));
                $settings->set('catering.event_types', $eventTypes);
                $settings->set('catering.service_styles', $serviceStyles);
                $settings->set('catering.budget_ranges', $budgetRanges);
                $settings->set('catering.capacity_guests', $capacityGuests);
                $settings->set('catering.service_style_rules', $serviceRules);
                $settings->set('catering.optional_extras', $optionalExtras);
                $settings->set('catering.blackout_dates', $blackoutDates);
                $settings->set('catering.fulfillment_windows', ['pickup' => $pickupWindows, 'delivery' => $deliveryWindows]);
                $settings->set('catering.delivery_zones', $deliveryZones);
                $settings->set('catering.quote_fields', $fieldConfig);
                $settings->set('catering.tasting_capacity', $tastingCapacity);
                $settings->set('catering.tasting_lead_days', $tastingLeadDays);
                $settings->set('catering.ordering_mode', $orderingMode);
                $settings->set('catering.direct_order_package_ids', array_values($directPackageIds));
            });
            (new CommerceIntegration($this->ctx, $this->db(), $settings))->syncProducts();
            $this->audit('catering.settings.updated', 0);
        } catch (\Throwable $e) {
            return $this->adminSettings($request, $e->getMessage(), $request->post);
        }
        return Response::redirect($request->routeUrl('/admin/catering/settings', ['saved' => 1]));
    }

    private function page(
        Request $request,
        string $template,
        array $data,
        string $title,
        string $description,
        bool $noIndex = false,
    ): Response {
        $view = $this->ctx->service(View::class);
        $settings = $this->settings();
        $profile = $this->ctx->service(BusinessProfile::class)->get();
        $siteName = $profile['name'] !== '' ? $profile['name'] : (string) $settings->get('site.name', 'Catering Co.');
        $preview = false;
        if ($request->string('theme_preview') === '1') {
            $auth = $this->ctx->service(AuthService::class);
            $user = $auth->user();
            $preview = $user !== null && $auth->has($user, 'appearance.manage');
        }
        $theme = $this->ctx->activeModuleTheme($preview);
        $themeMedia = [];
        if ($theme !== null) {
            foreach ($theme['variant']['media_slots'] ?? [] as $slot => $definition) {
                $mediaId = (int) ($theme['media'][$slot] ?? 0);
                $media = $mediaId > 0 ? $this->ctx->service(MediaService::class)->find($mediaId) : null;
                $themeMedia[(string) $slot] = $media && !(int) ($media['is_private'] ?? 1)
                    ? rtrim($request->basePath, '/') . '/media/' . $mediaId . '/w/1800/' . rawurlencode((string) $media['original_name'])
                    : $this->ctx->assetBase($request->basePath) . '/' . (string) ($definition['default'] ?? 'assets/images/hero.webp');
            }
        }
        $ordering = new CommerceIntegration($this->ctx, $this->db(), $settings);
        $shared = [
            'basePath' => $this->ctx->routeBase($request->basePath),
            'assetBase' => $this->ctx->assetBase($request->basePath),
            'currentPath' => rtrim($request->path, '/') ?: '/',
            'siteName' => $siteName,
            'moduleVersion' => (string) (json_decode((string) file_get_contents(dirname(__DIR__) . '/addon.json'), true)['version'] ?? 'development'),
            'tagline' => (string) $settings->get('site.tagline', 'Gather beautifully.'),
            'phone' => $profile['phone'] !== '' ? $profile['phone'] : '(555) 123-4567',
            'contactEmail' => $profile['email'] !== '' ? $profile['email'] : (string) $settings->get('site.contact_email', 'hello@example.com'),
            'navigation' => $this->navigation(),
            'footerNavigation' => $this->ctx->navigationTree('footer'),
            'brandLogoUrl' => $this->ctx->brandAssetUrl($request, 'logo', 480),
            'faviconUrl' => $this->ctx->brandAssetUrl($request, 'favicon'),
            'copyrightNotice' => $this->ctx->copyrightNotice($siteName),
            'headerSocialLinks' => $this->ctx->socialLinks('header'),
            'footerSocialLinks' => $this->ctx->socialLinks('footer'),
            'dietaryLabels' => MenuPlanner::DIETARY,
            'allergenLabels' => MenuPlanner::ALLERGENS,
            'themeId' => (string) ($theme['variant_id'] ?? 'heritage-editorial'),
            'themeTokens' => is_array($theme['palette']['tokens'] ?? null) ? $theme['palette']['tokens'] : [],
            'themeMedia' => $themeMedia,
            'themePreview' => $preview,
            'orderingMode' => $ordering->mode(),
            'storeUrl' => $this->ctx->url($request->basePath, '/store'),
            'page' => [
                'title' => $title,
                'meta_title' => $title,
                'meta_description' => $description,
                'noindex' => $noIndex,
            ],
        ];
        foreach ($shared as $key => $value) {
            $view->share($key, $value);
        }
        $body = $view->render($this->ns . ':' . $template, array_merge($shared, $data));
        $layout = 'layout';
        $registered = (string) ($theme['variant']['layout'] ?? '');
        if (str_starts_with($registered, 'views/') && str_ends_with($registered, '.php')) {
            $layout = substr($registered, 6, -4);
        }
        return Response::html($view->render($this->ns . ':' . $layout, array_merge($shared, ['content' => $body])));
    }

    /** @return list<array> */
    private function navigation(): array
    {
        return $this->ctx->navigationTree();
    }

    /** @return list<array<string,mixed>> */
    private function contentWithMedia(Request $request, string $type, array $fields): array
    {
        $items = $this->ctx->service(ContentItems::class)->items($this->ctx->packageId(), $type, true);
        $mediaService = $this->ctx->service(MediaService::class);
        foreach ($items as &$item) {
            $values = is_array($item['fields'] ?? null) ? $item['fields'] : [];
            foreach ($fields as $field) {
                $item['media'][$field] = [];
                foreach (array_filter(array_map('trim', explode(',', (string) ($values[$field] ?? '')))) as $rawId) {
                    $id = ctype_digit($rawId) ? (int) $rawId : 0;
                    $media = $id > 0 ? $mediaService->find($id) : null;
                    if (!$media || (int) ($media['is_private'] ?? 1) !== 0 || !str_starts_with((string) ($media['mime'] ?? ''), 'image/')) continue;
                    $item['media'][$field][] = [
                        'url' => rtrim($request->basePath, '/') . '/media/' . $id . '/w/1400/' . rawurlencode((string) $media['original_name']),
                        'alt' => trim((string) ($media['alt_text'] ?? '')) ?: (string) $media['original_name'],
                    ];
                }
            }
        }
        unset($item);
        return $items;
    }

    private function adminPage(Request $request, string $template, array $data, string $title): Response
    {
        return $this->ctx->adminView($request, $this->ns . ':' . $template, $data, $title, 'catering.manage');
    }

    private function saveInquiry(Request $request, string $kind, array $data): void
    {
        $record = [
            'kind' => $kind,
            'status' => 'new',
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'company' => $data['company'] ?? '',
            'event_type' => $data['event_type'] ?? '',
            'event_date' => $data['event_date'] ?? '',
            'event_time' => $data['event_time'] ?? '',
            'guest_count' => ($data['guest_count'] ?? '') !== '' ? (int) $data['guest_count'] : null,
            'venue' => $data['venue'] ?? '',
            'service_style' => $data['service_style'] ?? '',
            'fulfillment_method' => $data['fulfillment_method'] ?? '',
            'fulfillment_window' => $data['fulfillment_window'] ?? '',
            'address_line_1' => $data['address_line_1'] ?? '',
            'address_line_2' => $data['address_line_2'] ?? '',
            'city' => $data['city'] ?? '',
            'region' => $data['region'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'tasting_requested' => !empty($data['tasting_requested']) ? 1 : 0,
            'menu_interest' => $data['menu_interest'] ?? '',
            'budget' => $data['budget'] ?? '',
            'dietary' => $data['dietary'] ?? '',
            'message' => $data['message'] ?? '',
            'source_ip' => $request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
            'menu_plan' => $data['menu_plan'] ?? null,
            'operations_plan' => $data['operations_plan'] ?? null,
        ];
        $id = (int) $this->db()->insert('catering_inquiries', $record);
        if ($kind === 'quote' && !empty($record['tasting_requested'])) {
            $this->db()->insert('catering_tastings', [
                'inquiry_id' => $id, 'event_id' => null,
                'customer_name' => $record['name'], 'customer_email' => $record['email'],
                'preferred_date' => null, 'guest_count' => 2,
                'status' => 'requested', 'eligibility' => 'review',
                'dietary' => $record['dietary'] ?: null,
                'notes' => 'Requested with quote submission.',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null,
            ]);
        }
        $this->linkInquiryToCore($id, $record);
        $profile = $this->ctx->service(BusinessProfile::class)->get();
        $to = $profile['email'] !== ''
            ? $profile['email']
            : (string) $this->settings()->get('site.contact_email', '');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $lines = [];
            foreach ($record as $key => $value) {
                if ($value !== null && $value !== '') {
                    if ($key === 'menu_plan') {
                        $plan = json_decode((string) $value, true) ?: [];
                        $lines[] = 'Selected package: ' . ($plan['package_name'] ?? 'Custom menu');
                        $lines[] = 'Selected dishes: ' . implode(', ', array_column($plan['items'] ?? [], 'name'));
                        $lines[] = 'Preliminary food estimate: ' . (isset($plan['estimate_cents']) ? '$' . number_format($plan['estimate_cents'] / 100, 2) : 'Team confirmation required');
                    } elseif ($key === 'operations_plan') {
                        $operations = json_decode((string) $value, true) ?: [];
                        $lines[] = 'Fulfillment: ' . trim(ucfirst((string) ($operations['fulfillment_method'] ?? '')) . ' ' . (string) ($operations['fulfillment_window'] ?? ''));
                        $lines[] = 'Configured operations estimate: ' . (isset($operations['operations_cents']) ? '$' . number_format($operations['operations_cents'] / 100, 2) : 'Team confirmation required');
                        $lines[] = 'Combined preliminary estimate: ' . (isset($operations['combined_estimate_cents']) ? '$' . number_format($operations['combined_estimate_cents'] / 100, 2) : 'Team confirmation required');
                    } else {
                        $lines[] = ucwords(str_replace('_', ' ', $key)) . ': ' . (string) $value;
                    }
                }
            }
            $this->ctx->service(SystemMail::class)->queue(
                $to,
                ($kind === 'quote' ? 'New catering quote request from ' : 'New catering message from ') . $data['name'],
                implode("\n", $lines),
                'catering-inquiry:' . $id
            );
        }
    }

    /**
     * Link preserved module questionnaires to Core's package-scoped sales
     * workspace in bounded, retry-safe batches. This runs after migrations
     * and never deletes or rewrites the detailed catering record.
     */
    public function synchronizeLegacyInquiries(int $limit = 100): void
    {
        $db = $this->db();
        $table = $db->table('catering_inquiries');
        if (!$db->tableExists('catering_inquiries') || !self::hasColumn($db, $table, 'core_inquiry_id')) {
            return;
        }
        $rows = $db->fetchAll(
            "SELECT * FROM `{$table}` WHERE core_inquiry_id IS NULL ORDER BY id ASC LIMIT " . max(1, min(250, $limit))
        );
        foreach ($rows as $row) {
            $this->linkInquiryToCore((int) $row['id'], $row);
        }
    }

    public static function pendingCoreLinks(Connection $db): int
    {
        if (!$db->tableExists('catering_inquiries')) {
            return 0;
        }
        $table = $db->table('catering_inquiries');
        if (!self::hasColumn($db, $table, 'core_inquiry_id')) {
            return (int) $db->fetchColumn("SELECT COUNT(*) FROM `{$table}`");
        }
        return (int) $db->fetchColumn("SELECT COUNT(*) FROM `{$table}` WHERE core_inquiry_id IS NULL");
    }

    private function linkInquiryToCore(int $localId, array $record): void
    {
        if ($localId < 1 || !self::hasColumn($this->db(), $this->db()->table('catering_inquiries'), 'core_inquiry_id')) {
            return;
        }
        $this->db()->transaction(function () use ($localId, $record): void {
            $linked = $this->db()->fetchColumn(
                'SELECT core_inquiry_id FROM `' . $this->db()->table('catering_inquiries') . '` WHERE id = ?'
                    . ($this->db()->driver() === 'mysql' ? ' FOR UPDATE' : ''),
                [$localId]
            );
            if ($linked !== false && $linked !== null && (int) $linked > 0) {
                return;
            }

            $kind = (string) ($record['kind'] ?? 'quote');
            [$coreId, $error] = $this->ctx->service(InquiryService::class)->submit(
                $this->ctx->packageId(),
                [
                    'name' => (string) ($record['name'] ?? ''),
                    'email' => (string) ($record['email'] ?? ''),
                    'phone' => (string) ($record['phone'] ?? ''),
                    'subject' => $kind === 'quote'
                        ? 'Catering quote request' . (($record['event_type'] ?? '') !== '' ? ': ' . $record['event_type'] : '')
                        : 'Catering contact inquiry',
                    'message' => $this->inquirySummary($record),
                ]
            );
            if ($error !== null || $coreId === null) {
                return;
            }
            $this->ctx->service(InquiryService::class)->update(
                $coreId,
                $this->ctx->packageId(),
                $this->coreInquiryStatus((string) ($record['status'] ?? 'new')),
                'normal',
                null
            );
            $this->db()->update('catering_inquiries', ['core_inquiry_id' => $coreId], 'id = ? AND core_inquiry_id IS NULL', [$localId]);
        });
    }

    private function inquirySummary(array $record): string
    {
        $labels = [
            'company' => 'Company', 'event_type' => 'Event type', 'event_date' => 'Event date',
            'event_time' => 'Start time', 'guest_count' => 'Estimated guests', 'venue' => 'Venue',
            'service_style' => 'Service style', 'menu_interest' => 'Menu interest', 'budget' => 'Budget',
            'fulfillment_method' => 'Fulfillment', 'fulfillment_window' => 'Fulfillment window',
            'address_line_1' => 'Delivery address', 'address_line_2' => 'Delivery address line 2',
            'city' => 'Delivery city', 'region' => 'Delivery state / region', 'postal_code' => 'Postal code',
            'tasting_requested' => 'Tasting requested',
            'dietary' => 'Dietary needs', 'message' => 'Customer message', 'created_at' => 'Originally received',
        ];
        $lines = [];
        foreach ($labels as $key => $label) {
            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }
        $plan = json_decode((string) ($record['menu_plan'] ?? ''), true);
        if (is_array($plan)) {
            $lines[] = 'Menu submission snapshot';
            $lines[] = 'Selected package: ' . ($plan['package_name'] ?? 'Custom menu');
            $lines[] = 'Selected dishes: ' . implode(', ', array_column($plan['items'] ?? [], 'name'));
            $lines[] = 'Preliminary food estimate: ' . (isset($plan['estimate_cents'])
                ? '$' . number_format($plan['estimate_cents'] / 100, 2) : 'Team confirmation required');
        }
        $operations = json_decode((string) ($record['operations_plan'] ?? ''), true);
        if (is_array($operations)) {
            $lines[] = 'Operations submission snapshot';
            foreach ($operations['lines'] ?? [] as $line) $lines[] = (string) ($line['name'] ?? 'Service') . ': $' . number_format(((int) ($line['total_cents'] ?? 0)) / 100, 2);
            $lines[] = 'Combined preliminary estimate: ' . (isset($operations['combined_estimate_cents'])
                ? '$' . number_format($operations['combined_estimate_cents'] / 100, 2) : 'Team confirmation required');
        }
        return $lines !== [] ? implode("\n", $lines) : 'Catering inquiry submitted through the website.';
    }

    private function coreInquiryStatus(string $status): string
    {
        return match ($status) {
            'reviewing', 'more-info' => 'contacted',
            'quote-in-progress' => 'qualified',
            'quote-sent' => 'quoted',
            'accepted', 'deposit-pending', 'booked', 'completed' => 'won',
            'declined', 'cancelled' => 'lost',
            default => 'new',
        };
    }

    private static function hasColumn(Connection $db, string $table, string $column): bool
    {
        if ($db->driver() === 'sqlite') {
            return in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true);
        }
        return (bool) $db->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
            [$table, $column]
        );
    }

    private function contactInput(Request $request): array
    {
        return [
            'name' => mb_substr($request->string('name'), 0, 190),
            'email' => mb_substr($request->string('email'), 0, 190),
            'phone' => mb_substr($request->string('phone'), 0, 80),
            'message' => mb_substr($request->string('message'), 0, 5000),
        ];
    }

    private function quoteInput(Request $request, array $config): array
    {
        $data = [
            'name' => mb_substr($request->string('name'), 0, 190),
            'email' => mb_substr($request->string('email'), 0, 190),
            'phone' => mb_substr($request->string('phone'), 0, 80),
            'company' => mb_substr($request->string('company'), 0, 190),
            'event_type' => mb_substr($request->string('event_type'), 0, 100),
            'event_date' => mb_substr($request->string('event_date'), 0, 20),
            'event_time' => mb_substr($request->string('event_time'), 0, 20),
            'guest_count' => mb_substr($request->string('guest_count'), 0, 10),
            'venue' => mb_substr($request->string('venue'), 0, 255),
            'service_style' => mb_substr($request->string('service_style'), 0, 100),
            'fulfillment_method' => mb_substr($request->string('fulfillment_method'), 0, 20),
            'fulfillment_window' => mb_substr($request->string('fulfillment_window'), 0, 120),
            'address_line_1' => mb_substr($request->string('address_line_1'), 0, 255),
            'address_line_2' => mb_substr($request->string('address_line_2'), 0, 255),
            'city' => mb_substr($request->string('city'), 0, 120),
            'region' => mb_substr($request->string('region'), 0, 120),
            'postal_code' => mb_substr(strtoupper($request->string('postal_code')), 0, 20),
            'tasting_requested' => $request->string('tasting_requested') === '1' ? 1 : 0,
            'menu_interest' => mb_substr($request->string('menu_interest'), 0, 190),
            'budget' => mb_substr($request->string('budget'), 0, 100),
            'dietary' => mb_substr($request->string('dietary'), 0, 3000),
            'message' => mb_substr($request->string('message'), 0, 5000),
        ];
        foreach (self::QUOTE_FIELDS as $key => $_label) {
            if (empty($config['fields'][$key]['enabled'])) $data[$key] = '';
        }
        if ($data['fulfillment_method'] !== 'delivery') {
            foreach (['address_line_1', 'address_line_2', 'city', 'region', 'postal_code'] as $field) $data[$field] = '';
        }
        return $data;
    }

    private function contactErrors(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Your name is required.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if ($data['message'] === '') {
            $errors[] = 'Please enter a message.';
        }
        return $errors;
    }

    private function quoteErrors(array $data, array $config): array
    {
        $errors = [];
        if ($data['name'] === '') $errors[] = 'Your name is required.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (!in_array($data['event_type'], $config['event_types'], true)) {
            $errors[] = 'Please choose a supported event type.';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['event_date']);
        if ($date === false || $date->format('Y-m-d') !== $data['event_date']) {
            $errors[] = 'Please provide a valid event date.';
        }
        if ($data['event_time'] !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $data['event_time'])) {
            $errors[] = 'Please provide a valid event time.';
        }
        if ($data['service_style'] !== '' && !in_array($data['service_style'], $config['service_styles'], true)) {
            $errors[] = 'Please choose a supported service style.';
        }
        if ($data['budget'] !== '' && !in_array($data['budget'], $config['budget_ranges'], true)) {
            $errors[] = 'Please choose a supported budget range.';
        }
        if ((int) $data['guest_count'] < 1 || (int) $data['guest_count'] > 10000) {
            $errors[] = 'Guest count must be between 1 and 10,000.';
        }
        if ($data['fulfillment_method'] === 'delivery') {
            if ($data['address_line_1'] === '') $errors[] = 'Delivery street address is required.';
            if ($data['city'] === '') $errors[] = 'Delivery city is required.';
            if ($data['region'] === '') $errors[] = 'Delivery state or region is required.';
        }
        foreach (self::QUOTE_FIELDS as $key => $label) {
            if (!empty($config['fields'][$key]['enabled']) && !empty($config['fields'][$key]['required'])
                && trim((string) ($data[$key] ?? '')) === '') {
                $errors[] = $label . ' is required.';
            }
        }
        return $errors;
    }

    /** Add Core's authoritative radius result without replacing postal-zone rules. */
    private function applyServiceAreaDecision(array &$operations, array $input): void
    {
        if (($input['fulfillment_method'] ?? '') !== 'delivery') return;
        $address = [
            'line1' => $input['address_line_1'] ?? '',
            'line2' => $input['address_line_2'] ?? '',
            'city' => $input['city'] ?? '',
            'region' => $input['region'] ?? '',
            'postal_code' => $input['postal_code'] ?? '',
            'country' => $input['country'] ?? '',
        ];
        if (trim((string) $address['line1']) === '' || trim((string) $address['city']) === ''
            || trim((string) $address['region']) === '') return;
        $decision = $this->ctx->checkServiceAddress($address);
        $operations['service_area'] = $decision;
        $operations['errors'] = is_array($operations['errors'] ?? null) ? $operations['errors'] : [];
        $operations['notes'] = is_array($operations['notes'] ?? null) ? $operations['notes'] : [];
        if (($decision['status'] ?? '') === 'outside') {
            $operations['errors'][] = 'This address is currently outside our service area.';
        } elseif (($decision['status'] ?? '') === 'unknown') {
            $operations['notes'][] = (string) ($decision['message'] ?? 'This address requires manual review.');
        } elseif (($decision['status'] ?? '') === 'inside') {
            $operations['notes'][] = 'Core verified this address inside the configured radius; postal delivery-zone rules still apply.';
        }
        $operations['errors'] = array_values(array_unique($operations['errors']));
        $operations['notes'] = array_values(array_unique($operations['notes']));
    }

    private function menuData(bool $visibleOnly = true): array
    {
        if ($visibleOnly) {
            [$availability, $params] = $this->availabilityClause('i');
            return [
                $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_menu_categories') . '` WHERE visible = 1 ORDER BY sort_order, name'),
                $this->db()->fetchAll('SELECT i.* FROM `' . $this->db()->table('catering_menu_items') . '` i INNER JOIN `'
                    . $this->db()->table('catering_menu_categories') . '` c ON c.id = i.category_id'
                    . ' WHERE i.visible = 1 AND c.visible = 1 AND ' . $availability
                    . ' ORDER BY i.category_id, i.sort_order, i.name', $params),
            ];
        }
        return [
            $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_menu_categories') . '` ORDER BY sort_order, name'),
            $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_menu_items') . '` ORDER BY category_id, sort_order, name'),
        ];
    }

    private function visiblePackages(?int $limit = null): array
    {
        [$availability, $params] = $this->availabilityClause();
        $packages = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('catering_packages')
            . '` WHERE visible = 1 AND ' . $availability . ' ORDER BY sort_order, name', $params
        );
        $visible = [];
        foreach ($packages as $package) {
            $group = !empty($package['menu_group_id']) ? $this->menuGroup((int) $package['menu_group_id'], true) : null;
            if (!empty($package['menu_group_id']) && $group === null) continue;
            $package['menu_group_name'] = $group['name'] ?? '';
            $package['menu_group_item_ids'] = json_encode(array_map('intval', array_column($group['items'] ?? [], 'id')), JSON_THROW_ON_ERROR);
            $visible[] = $package;
            if ($limit !== null && count($visible) >= $limit) break;
        }
        return $visible;
    }

    private function menuGroups(bool $availableOnly): array
    {
        $where = '';
        $params = [];
        if ($availableOnly) {
            [$availability, $params] = $this->availabilityClause('g');
            $where = ' WHERE g.visible = 1 AND ' . $availability;
        }
        $groups = $this->db()->fetchAll('SELECT g.* FROM `' . $this->db()->table('catering_menu_groups') . '` g'
            . $where . ' ORDER BY g.sort_order, g.name', $params);
        foreach ($groups as &$group) {
            $itemWhere = $availableOnly ? ' AND i.visible = 1 AND c.visible = 1' : '';
            $itemParams = [(int) $group['id']];
            if ($availableOnly) {
                [$itemAvailability, $dateParams] = $this->availabilityClause('i');
                $itemWhere .= ' AND ' . $itemAvailability;
                $itemParams = array_merge($itemParams, $dateParams);
            }
            $group['items'] = $this->db()->fetchAll('SELECT i.* FROM `' . $this->db()->table('catering_menu_group_items') . '` gi INNER JOIN `'
                . $this->db()->table('catering_menu_items') . '` i ON i.id = gi.item_id INNER JOIN `'
                . $this->db()->table('catering_menu_categories') . '` c ON c.id = i.category_id'
                . ' WHERE gi.group_id = ?' . $itemWhere . ' ORDER BY gi.sort_order, i.name', $itemParams);
        }
        unset($group);
        return $groups;
    }

    private function menuGroup(int $id, bool $availableOnly): ?array
    {
        foreach ($this->menuGroups($availableOnly) as $group) {
            if ((int) $group['id'] === $id) return $group;
        }
        return null;
    }

    private function uniqueSlug(string $table, string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'item';
        $candidate = $slug;
        $suffix = 2;
        while ($this->db()->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db()->table($table) . '` WHERE slug = ?',
            [$candidate]
        )) {
            $candidate = $slug . '-' . $suffix++;
        }
        return $candidate;
    }

    private function quoteConfiguration(): array
    {
        $fields = [];
        $stored = $this->settings()->get('catering.quote_fields', []);
        $stored = is_array($stored) ? $stored : [];
        foreach (self::QUOTE_FIELDS as $key => $_label) {
            $value = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $enabled = array_key_exists('enabled', $value) ? (bool) $value['enabled'] : true;
            $fields[$key] = [
                'enabled' => $enabled,
                'required' => $enabled && (bool) ($value['required'] ?? false),
            ];
        }
        return [
            'event_types' => $this->storedSettingList('catering.event_types', self::EVENT_TYPES),
            'service_styles' => $this->storedSettingList('catering.service_styles', self::SERVICE_STYLES),
            'budget_ranges' => $this->storedSettingList('catering.budget_ranges', self::BUDGET_RANGES),
            'fields' => $fields,
        ];
    }

    private function storedSettingList(string $key, array $default): array
    {
        $stored = $this->settings()->get($key, $default);
        if (!is_array($stored)) return $default;
        $clean = [];
        foreach ($stored as $value) {
            if (!is_scalar($value)) continue;
            $value = trim(mb_substr((string) $value, 0, 100));
            $normalized = mb_strtolower($value);
            if ($value !== '' && !isset($clean[$normalized])) $clean[$normalized] = $value;
        }
        return $clean !== [] ? array_values($clean) : $default;
    }

    private function parseSettingList(string $input, string $label): array
    {
        $lines = preg_split('/\R/u', $input) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (mb_strlen($line) > 100) throw new \InvalidArgumentException('Each ' . $label . ' entry must be at most 100 characters.');
            $normalized = mb_strtolower($line);
            if (!isset($clean[$normalized])) $clean[$normalized] = $line;
        }
        if ($clean === []) throw new \InvalidArgumentException('Enter at least one ' . $label . ' entry.');
        if (count($clean) > 24) throw new \InvalidArgumentException('Enter no more than 24 ' . $label . ' entries.');
        return array_values($clean);
    }

    private function operationsConfiguration(array $serviceStyles): array
    {
        $leadDays = max(0, min(3650, (int) $this->settings()->get('catering.lead_time', 14)));
        $storedRules = $this->settings()->get('catering.service_style_rules', []);
        $storedRules = is_array($storedRules) ? $storedRules : [];
        $rulesByName = [];
        foreach ($storedRules as $rule) {
            if (!is_array($rule) || !is_scalar($rule['name'] ?? null)) continue;
            $rulesByName[(string) $rule['name']] = $rule;
        }
        $styles = [];
        foreach ($serviceStyles as $name) {
            $rule = $rulesByName[$name] ?? [];
            $minimum = max(1, min(10000, (int) ($rule['minimum_guests'] ?? 1)));
            $maximum = max($minimum, min(10000, (int) ($rule['maximum_guests'] ?? 10000)));
            $styles[] = [
                'name' => $name,
                'minimum_guests' => $minimum,
                'maximum_guests' => $maximum,
                'lead_days' => max(0, min(3650, (int) ($rule['lead_days'] ?? $leadDays))),
            ];
        }
        $extras = $this->settings()->get('catering.optional_extras', []);
        $extras = is_array($extras) ? array_values(array_filter($extras, static fn ($extra): bool =>
            is_array($extra) && is_string($extra['key'] ?? null) && is_string($extra['name'] ?? null)
                && isset(EventOperations::EXTRA_PRICING[(string) ($extra['pricing_basis'] ?? '')])
        )) : [];
        $blackouts = $this->settings()->get('catering.blackout_dates', []);
        $blackouts = is_array($blackouts) ? array_values(array_filter($blackouts, 'is_string')) : [];
        $windows = $this->settings()->get('catering.fulfillment_windows', []);
        $windows = is_array($windows) ? $windows : [];
        $pickup = is_array($windows['pickup'] ?? null) && $windows['pickup'] !== [] ? array_values($windows['pickup']) : ['Pickup time confirmed with team'];
        $delivery = is_array($windows['delivery'] ?? null) && $windows['delivery'] !== [] ? array_values($windows['delivery']) : ['Delivery time confirmed with team'];
        $zones = $this->settings()->get('catering.delivery_zones', null);
        $zones = is_array($zones) ? array_values($zones) : [[
            'name' => 'Team-confirmed delivery area', 'postal_codes' => ['*'], 'fee_cents' => 0, 'minimum_order_cents' => 0,
        ]];
        return [
            'lead_days' => $leadDays,
            'capacity_guests' => max(1, min(10000, (int) $this->settings()->get('catering.capacity_guests', 10000))),
            'styles' => $styles,
            'extras' => $extras,
            'blackout_dates' => $blackouts,
            'windows' => ['pickup' => $pickup, 'delivery' => $delivery],
            'zones' => $zones,
        ];
    }

    private function parseServiceStyleRules(string $input, array $serviceStyles): array
    {
        $known = array_fill_keys($serviceStyles, true);
        $rules = [];
        foreach (preg_split('/\R/u', $input) ?: [] as $line) {
            if (trim($line) === '') continue;
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 4 || !isset($known[$parts[0]])) {
                throw new \InvalidArgumentException('Each service rule must use a configured style and the format Style|min guests|max guests|lead days.');
            }
            if (isset($rules[$parts[0]])) throw new \InvalidArgumentException('Each service style may have only one operations rule.');
            $minimum = $this->boundedInt($parts[1], 1, 10000, 'Service minimum guests');
            $maximum = $this->boundedInt($parts[2], $minimum, 10000, 'Service maximum guests');
            $lead = $this->boundedInt($parts[3], 0, 3650, 'Service lead time');
            $rules[$parts[0]] = ['name' => $parts[0], 'minimum_guests' => $minimum, 'maximum_guests' => $maximum, 'lead_days' => $lead];
        }
        foreach ($serviceStyles as $style) {
            $rules[$style] ??= ['name' => $style, 'minimum_guests' => 1, 'maximum_guests' => 10000,
                'lead_days' => max(0, min(3650, (int) $this->settings()->get('catering.lead_time', 14)))];
        }
        return array_values($rules);
    }

    private function formatServiceStyleRules(array $styles): string
    {
        return implode("\n", array_map(static fn (array $style): string => implode('|', [
            $style['name'], $style['minimum_guests'], $style['maximum_guests'], $style['lead_days'],
        ]), $styles));
    }

    private function parseOptionalExtras(string $input): array
    {
        $extras = [];
        foreach (preg_split('/\R/u', $input) ?: [] as $line) {
            if (trim($line) === '') continue;
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 4 || !preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $parts[0])
                || $parts[1] === '' || mb_strlen($parts[1]) > 100 || !isset(EventOperations::EXTRA_PRICING[$parts[3]])) {
                throw new \InvalidArgumentException('Each optional extra must use key|name|price|fixed or per_guest.');
            }
            if (isset($extras[$parts[0]])) throw new \InvalidArgumentException('Each optional extra key must be unique.');
            $extras[$parts[0]] = ['key' => $parts[0], 'name' => $parts[1], 'price_cents' => MenuPlanner::cents($parts[2]), 'pricing_basis' => $parts[3]];
        }
        if (count($extras) > 20) throw new \InvalidArgumentException('Configure no more than 20 optional extras.');
        return array_values($extras);
    }

    private function formatOptionalExtras(array $extras): string
    {
        return implode("\n", array_map(static fn (array $extra): string => implode('|', [
            $extra['key'], $extra['name'], number_format(((int) $extra['price_cents']) / 100, 2, '.', ''), $extra['pricing_basis'],
        ]), $extras));
    }

    private function parseBlackoutDates(string $input): array
    {
        $dates = [];
        foreach (preg_split('/\R/u', $input) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $line);
            if ($date === false || $date->format('Y-m-d') !== $line) throw new \InvalidArgumentException('Blackout dates must use YYYY-MM-DD.');
            $dates[$line] = true;
        }
        if (count($dates) > 366) throw new \InvalidArgumentException('Configure no more than 366 blackout dates.');
        ksort($dates);
        return array_keys($dates);
    }

    private function parseDeliveryZones(string $input): array
    {
        $zones = [];
        foreach (preg_split('/\R/u', $input) ?: [] as $line) {
            if (trim($line) === '') continue;
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 4 || $parts[0] === '' || mb_strlen($parts[0]) > 100) {
                throw new \InvalidArgumentException('Each delivery zone must use Name|postal codes|fee|minimum food order.');
            }
            $postals = [];
            foreach (explode(',', strtoupper($parts[1])) as $postal) {
                $postal = trim($postal);
                if ($postal === '' || !preg_match('/^(?:\*|[A-Z0-9][A-Z0-9 -]{1,19})$/D', $postal)) throw new \InvalidArgumentException('Delivery postal codes are invalid.');
                $postals[$postal] = true;
            }
            $zones[] = ['name' => $parts[0], 'postal_codes' => array_map('strval', array_keys($postals)),
                'fee_cents' => MenuPlanner::cents($parts[2]), 'minimum_order_cents' => MenuPlanner::cents($parts[3])];
        }
        if (count($zones) > 50) throw new \InvalidArgumentException('Configure no more than 50 delivery zones.');
        return $zones;
    }

    private function formatDeliveryZones(array $zones): string
    {
        return implode("\n", array_map(static fn (array $zone): string => implode('|', [
            $zone['name'], implode(',', $zone['postal_codes']), number_format(((int) $zone['fee_cents']) / 100, 2, '.', ''),
            number_format(((int) $zone['minimum_order_cents']) / 100, 2, '.', ''),
        ]), $zones));
    }

    private function availabilityInput(Request $request): array
    {
        $from = trim($request->string('available_from'));
        $until = trim($request->string('available_until'));
        foreach (['Available from' => $from, 'Available until' => $until] as $label => $value) {
            if ($value === '') continue;
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date === false || $date->format('Y-m-d') !== $value) {
                throw new \InvalidArgumentException($label . ' must be a valid date.');
            }
        }
        if ($from !== '' && $until !== '' && $from > $until) {
            throw new \InvalidArgumentException('Available until must be on or after available from.');
        }
        return ['available_from' => $from !== '' ? $from : null, 'available_until' => $until !== '' ? $until : null];
    }

    private function availabilityClause(string $alias = ''): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $today = date('Y-m-d');
        return [
            '((' . $prefix . 'available_from IS NULL OR ' . $prefix . "available_from = '' OR " . $prefix . 'available_from <= ?)'
                . ' AND (' . $prefix . 'available_until IS NULL OR ' . $prefix . "available_until = '' OR " . $prefix . 'available_until >= ?))',
            [$today, $today],
        ];
    }

    private function guard(string $capability): ?Response
    {
        $auth = $this->ctx->service(AuthService::class);
        $user = $auth->user();
        if ($user === null || !$auth->has($user, $capability)) {
            return Response::html('<h1>403 — Forbidden</h1>', 403);
        }
        return null;
    }

    private function db(): Connection
    {
        return $this->ctx->service(Connection::class);
    }

    private function settings(): SettingsRepository
    {
        return $this->ctx->service(SettingsRepository::class);
    }

    private function resourceRoute(string $key): string
    {
        foreach ($this->ctx->adminResources() as $resource) {
            if (($resource['key'] ?? null) === $key) return (string) ($resource['route'] ?? '');
        }
        return '';
    }

    private function plannerCatalog(): array
    {
        [$categories, $items] = $this->menuData();
        return [$categories, $items, $this->visiblePackages()];
    }

    private function pricingInput(Request $request): array
    {
        $basis = $request->string('pricing_basis', 'manual');
        if (!isset(MenuPlanner::PRICING[$basis])) throw new \InvalidArgumentException('Choose a supported pricing method.');
        return [
            'pricing_basis' => $basis, 'price_cents' => MenuPlanner::cents($request->string('price', '0')),
            'servings_per_tray' => $basis === 'per_tray' ? $this->boundedInt($request->string('servings_per_tray'), 1, 10000, 'Servings per tray') : 1,
            'minimum_quantity' => in_array($basis, ['per_guest', 'per_tray'], true) ? $this->boundedInt($request->string('minimum_quantity', '1'), 1, 10000, 'Minimum quantity') : 1,
            'guest_tiers' => json_encode($this->guestTiers($request), JSON_THROW_ON_ERROR),
        ];
    }

    private function guestTiers(Request $request): array
    {
        $input = $request->input('guest_tiers', []);
        if (!is_array($input) || count($input) > 5) throw new \InvalidArgumentException('Use up to five guest-count discount tiers.');
        $tiers = [];
        foreach ($input as $row) {
            if (!is_array($row)) throw new \InvalidArgumentException('Invalid guest-count discount tier.');
            $guests = is_string($row['minimum_guests'] ?? null) ? trim($row['minimum_guests']) : '';
            $percent = is_string($row['discount_percent'] ?? null) ? trim($row['discount_percent']) : '';
            if ($guests === '' && $percent === '') continue;
            $minimum = $this->boundedInt($guests, 1, 10000, 'Discount guest threshold');
            if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $percent) || MenuPlanner::cents($percent) > 10000) {
                throw new \InvalidArgumentException('Discount must be between 0 and 100 percent with at most two decimal places.');
            }
            if (isset($tiers[$minimum])) throw new \InvalidArgumentException('Each discount guest threshold must be unique.');
            $tiers[$minimum] = ['minimum_guests' => $minimum, 'discount_bps' => MenuPlanner::cents($percent)];
        }
        ksort($tiers, SORT_NUMERIC);
        return array_values($tiers);
    }

    private function boundedInt(string $raw, int $min, int $max, string $label): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,8})$/D', $raw) || (int) $raw < $min || (int) $raw > $max) {
            throw new \InvalidArgumentException($label . ' must be a whole number between ' . $min . ' and ' . $max . '.');
        }
        return (int) $raw;
    }

    private function optionalBoundedInt(string $raw, int $min, int $max, string $label): ?int
    {
        $raw = trim($raw);
        return $raw === '' ? null : $this->boundedInt($raw, $min, $max, $label);
    }

    private function optionalDate(string $raw, string $label): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) throw new \InvalidArgumentException($label . ' must be a valid date.');
        return $raw;
    }

    private function optionalMoney(string $raw): ?int
    {
        $raw = trim($raw);
        return $raw === '' ? null : MenuPlanner::cents($raw);
    }

    private function validatedTags(mixed $value, array $allowed): array
    {
        if (!is_array($value) || count($value) > count($allowed)) throw new \InvalidArgumentException('Choose supported dietary or allergen labels.');
        foreach ($value as $tag) if (!is_string($tag) || !isset($allowed[$tag])) throw new \InvalidArgumentException('Choose supported dietary or allergen labels.');
        return array_values(array_unique($value));
    }

    private function packageRules(Request $request): array
    {
        $input = $request->input('choices', []);
        if (!is_array($input) || count($input) > 100) throw new \InvalidArgumentException('Invalid package choice rules.');
        [$categories, $items] = $this->menuData();
        $available = [];
        foreach ($items as $item) $available[$item['category_id']] = ($available[$item['category_id']] ?? 0) + 1;
        $byId = array_column($categories, null, 'id');
        $rules = [];
        foreach ($input as $id => $rule) {
            if (!is_array($rule)) throw new \InvalidArgumentException('Invalid package choice rule.');
            if (($rule['enabled'] ?? '') !== '1') continue;
            if (!isset($byId[$id])) throw new \InvalidArgumentException('Package choices must use a visible category.');
            $min = $this->boundedInt(is_string($rule['min'] ?? null) ? $rule['min'] : '', 0, 100, 'Minimum choices');
            $max = $this->boundedInt(is_string($rule['max'] ?? null) ? $rule['max'] : '', max(1, $min), 100, 'Maximum choices');
            if ($min > ($available[$id] ?? 0)) throw new \InvalidArgumentException('There are not enough visible dishes to meet the minimum choices for ' . $byId[$id]['name'] . '.');
            $rules[(string) $id] = ['label' => $byId[$id]['name'], 'min' => $min, 'max' => $max];
        }
        return $rules;
    }

    private function existingId(Request $request, string $table): int
    {
        $id = $this->boundedInt($request->string('id'), 1, 999999999, 'Record ID');
        if (!$this->db()->fetchColumn('SELECT id FROM `' . $this->db()->table($table) . '` WHERE id = ?', [$id])) {
            throw new \InvalidArgumentException('This record no longer exists.');
        }
        return $id;
    }

    private function childId(Request $request, string $table, int $eventId): int
    {
        $id = $this->boundedInt($request->string('id'), 1, 999999999, 'Record ID');
        if (!(bool) $this->db()->fetchColumn('SELECT COUNT(*) FROM `' . $this->db()->table($table) . '` WHERE id=? AND event_id=?', [$id,$eventId])) {
            throw new \InvalidArgumentException('This event record no longer exists.');
        }
        return $id;
    }

    private function updateChildStatus(Request $request, string $table, array $allowed, int $eventId): void
    {
        $id = $this->childId($request,$table,$eventId);
        $status = $request->string('status');
        if (!in_array($status,$allowed,true)) throw new \InvalidArgumentException('Choose a valid status.');
        $this->db()->update($table,['status'=>$status,'updated_at'=>date('Y-m-d H:i:s')],'id=?',[$id]);
        $this->audit('catering.event.record_updated',$eventId);
    }

    private function audit(string $event, int $id): void
    {
        $user = $this->ctx->service(AuthService::class)->user();
        $this->ctx->service(AuditLog::class)->record($event, $user?->id, ['record_id' => $id]);
    }

    private function serviceData(): array
    {
        return self::publicServices();
    }

    public static function publicServices(): array
    {
        return [
            'weddings' => [
                'title' => 'Wedding Catering', 'short' => 'Weddings',
                'event_type' => 'Wedding',
                'summary' => 'A celebration designed around your story, from the first toast to the final sweet bite.',
                'kicker' => 'Your table, your story', 'image' => 'wedding-table.webp',
                'intro' => 'We shape the menu, service rhythm, and finishing details around the way you want your wedding to feel—warm, polished, and entirely personal.',
                'features' => ['Private menu consultation', 'Tasting experience', 'Cocktail-hour bites', 'Plated, buffet, or station service', 'Professional service team', 'Rental and timeline coordination'],
                'process' => ['Share your vision and venue', 'Taste and refine your menu', 'Finalize service and timeline', 'Celebrate while we handle the details'],
            ],
            'corporate-events' => [
                'title' => 'Corporate Catering', 'short' => 'Corporate Events',
                'event_type' => 'Corporate Event',
                'summary' => 'Reliable, polished food service for meetings, conferences, launches, and company celebrations.',
                'kicker' => 'Business, beautifully hosted', 'image' => 'corporate-service.webp',
                'intro' => 'From an executive breakfast to a multi-day conference, our corporate menus are designed for timely service, clear planning, and an excellent guest experience.',
                'features' => ['Breakfast and lunch service', 'Boxed and individually packaged meals', 'Conference buffets', 'Cocktail receptions', 'Dietary meal labeling', 'Recurring-event planning'],
                'process' => ['Tell us the schedule', 'Choose service and menu', 'Confirm delivery details', 'Host with confidence'],
            ],
            'private-celebrations' => [
                'title' => 'Private Celebrations', 'short' => 'Private Celebrations',
                'event_type' => 'Private Celebration',
                'summary' => 'Birthdays, anniversaries, showers, graduations, and gatherings made effortless.',
                'kicker' => 'Make the moment yours', 'image' => 'hero.webp',
                'intro' => 'Whether the guest list is intimate or expansive, we create a relaxed hospitality experience with food that fits the people and the occasion.',
                'features' => ['Flexible menu formats', 'Cocktail and dinner service', 'Family-style options', 'Celebration desserts', 'Bartending coordination', 'Home and venue service'],
                'process' => ['Choose the occasion', 'Build the menu', 'Add service details', 'Gather and enjoy'],
            ],
            'drop-off-catering' => [
                'title' => 'Drop-Off Catering', 'short' => 'Drop-Off Catering',
                'event_type' => 'Drop-Off Catering',
                'summary' => 'Freshly prepared, ready-to-serve catering for offices, family gatherings, and casual events.',
                'kicker' => 'Easy to serve, hard to forget', 'image' => 'plated-dinner.webp',
                'intro' => 'Choose your menu and delivery window, and we will arrive with labeled dishes, serving guidance, and everything arranged for a smooth meal.',
                'features' => ['Scheduled delivery or pickup', 'Disposable service ware', 'Hot and cold packaging', 'Clear dish labels', 'Dietary accommodations', 'Optional setup service'],
                'process' => ['Select a menu', 'Confirm headcount', 'Choose delivery or pickup', 'Unpack and serve'],
            ],
            'school-catering' => [
                'title' => 'School Catering', 'short' => 'Schools', 'event_type' => 'School Event',
                'summary' => 'Dependable meals for faculty gatherings, graduations, athletics, and campus celebrations.',
                'kicker' => 'Ready for the school day', 'image' => 'corporate-service.webp',
                'intro' => 'Clear delivery windows, labeled meal options, and practical service plans help school teams feed groups without disrupting the day.',
                'features' => ['Faculty meals', 'Graduation receptions', 'Team banquets', 'Individually labeled meals', 'Allergy-request review', 'Delivery coordination'],
                'process' => ['Share the schedule', 'Review menu and dietary requests', 'Confirm counts and access', 'Receive a labeled, organized service'],
            ],
            'nonprofit-events' => [
                'title' => 'Nonprofit Event Catering', 'short' => 'Nonprofit Events', 'event_type' => 'Fundraiser',
                'summary' => 'Mission-minded catering plans for fundraisers, volunteer meals, and community gatherings.',
                'kicker' => 'Hospitality that supports the mission', 'image' => 'hero.webp',
                'intro' => 'We build an efficient plan around the program, donor experience, venue, and budget while keeping service polished and welcoming.',
                'features' => ['Fundraiser receptions', 'Volunteer meals', 'Sponsor hospitality', 'Budget-aware menu design', 'Service staffing', 'Run-of-show coordination'],
                'process' => ['Define the mission and budget', 'Shape the guest experience', 'Coordinate venue and program', 'Serve with confidence'],
            ],
            'holiday-catering' => [
                'title' => 'Holiday Catering', 'short' => 'Holiday Events', 'event_type' => 'Holiday Party',
                'summary' => 'Seasonal menus and warm service for company parties, family celebrations, and community traditions.',
                'kicker' => 'Gather around the season', 'image' => 'plated-dinner.webp',
                'intro' => 'Choose full service, delivery, or pickup and let the team coordinate a festive menu around your timing, guest count, and venue.',
                'features' => ['Seasonal menu planning', 'Office celebrations', 'Family-style service', 'Cocktail bites', 'Dessert displays', 'Pickup and delivery'],
                'process' => ['Choose the gathering style', 'Reserve an available date', 'Finalize menu and count', 'Celebrate together'],
            ],
            'memorial-gatherings' => [
                'title' => 'Memorial Gathering Catering', 'short' => 'Memorial Gatherings', 'event_type' => 'Memorial Gathering',
                'summary' => 'Compassionate, straightforward meal service for families gathering to remember someone important.',
                'kicker' => 'Quiet support when it matters', 'image' => 'wedding-table.webp',
                'intro' => 'Our team keeps planning gentle and practical, with clear choices, respectful service, and flexible delivery or setup.',
                'features' => ['Simple consultation', 'Comforting menu options', 'Home or venue delivery', 'Buffet setup', 'Disposable service ware', 'Dietary-request review'],
                'process' => ['Tell us what is needed', 'Choose a fitting menu', 'Confirm place and timing', 'Let our team handle service'],
            ],
        ];
    }

    private function faqData(): array
    {
        return self::publicFaqs();
    }

    public static function publicFaqs(): array
    {
        return [
            ['How far in advance should we book?', 'For full-service events, begin the conversation as early as possible. Smaller drop-off orders may require less lead time, depending on the date and menu.'],
            ['Can you accommodate dietary needs?', 'Yes. Share allergies and dietary requirements during planning so the kitchen can recommend appropriate selections and handling.'],
            ['Do you provide tastings?', 'Tastings can be scheduled for qualifying full-service events after an initial consultation and menu direction are established.'],
            ['When is the final guest count due?', 'Your proposal and agreement will state the final-count deadline. This allows the culinary and service teams to prepare accurately.'],
            ['Do you provide staff and rentals?', 'Staffing, service ware, linens, china, glassware, and rental coordination can be included according to the selected service level.'],
            ['Is the website pricing a final quote?', 'No. Displayed prices are starting points. Venue requirements, staffing, travel, rentals, taxes, and menu changes affect the final proposal.'],
        ];
    }
}
