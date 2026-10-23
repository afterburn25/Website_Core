<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Admin\Nav;
use Core\Auth\AuthService;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\SystemMail;
use Core\Modules\ModuleContext;
use Core\Security\Csrf;
use Core\Security\RateLimiter;
use Core\Settings\SettingsRepository;
use Core\View\View;

final class CateringController
{
    private const STATUSES = [
        'new', 'reviewing', 'more-info', 'quote-in-progress', 'quote-sent',
        'accepted', 'deposit-pending', 'booked', 'completed', 'declined', 'cancelled',
    ];

    public function __construct(
        private ModuleContext $ctx,
        private string $ns,
    ) {}

    public function home(Request $request): Response
    {
        return $this->page($request, 'home', [
            'featuredItems' => $this->db()->fetchAll(
                'SELECT * FROM `' . $this->db()->table('catering_menu_items')
                . '` WHERE visible = 1 AND featured = 1 ORDER BY sort_order, name LIMIT 3'
            ),
            'packages' => $this->visiblePackages(3),
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
        $items = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('catering_menu_items')
            . '` WHERE category_id = ? AND visible = 1 ORDER BY sort_order, name',
            [(int) $category['id']]
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
        $package = $this->db()->fetch(
            'SELECT * FROM `' . $this->db()->table('catering_packages') . '` WHERE slug = ? AND visible = 1',
            [(string) ($request->attributes['slug'] ?? '')]
        );
        if ($package === null) {
            return Response::notFound();
        }
        return $this->page($request, 'package-detail', ['package' => $package],
            (string) $package['name'], (string) ($package['description'] ?? ''));
    }

    public function gallery(Request $request): Response
    {
        return $this->page($request, 'gallery', [], 'Gallery',
            'A visual look at beautifully catered weddings, celebrations, and corporate gatherings.');
    }

    public function about(Request $request): Response
    {
        return $this->page($request, 'about', [], 'Our Story',
            'Meet the people and philosophy behind the catering experience.');
    }

    public function serviceArea(Request $request): Response
    {
        $settings = $this->settings();
        return $this->page($request, 'service-area', [
            'serviceArea' => $settings->get('catering.service_area', 'Your city and surrounding communities'),
            'travelNote' => $settings->get('catering.travel_note', 'Travel and delivery fees may apply outside our primary service area.'),
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
            return Response::redirect($request->basePath . '/contact?sent=1');
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
        return Response::redirect($request->basePath . '/contact?sent=1');
    }

    public function quote(Request $request, ?string $error = null, array $old = []): Response
    {
        [$categories] = $this->menuData();
        return $this->page($request, 'quote', [
            'csrf' => $this->ctx->service(Csrf::class),
            'error' => $error,
            'sent' => $request->string('sent') === '1',
            'old' => $old,
            'categories' => $categories,
        ], 'Request a Quote', 'Share the details of your event and receive a personalized catering proposal.', true);
    }

    public function submitQuote(Request $request): Response
    {
        if (!$this->ctx->service(Csrf::class)->validate($request)) {
            return Response::text('Invalid CSRF token.', 419);
        }
        if ($request->string('website') !== '') {
            return Response::redirect($request->basePath . '/request-a-quote?sent=1');
        }
        if (!$this->ctx->service(RateLimiter::class)->attempt('catering-quote:' . $request->ip(), 4, 900)) {
            return $this->quote($request, 'Too many submissions. Please wait and try again.', $request->post);
        }
        $data = $this->quoteInput($request);
        $errors = $this->quoteErrors($data);
        if ($errors !== []) {
            return $this->quote($request, implode(' ', $errors), $data);
        }
        $this->saveInquiry($request, 'quote', $data);
        return Response::redirect($request->basePath . '/request-a-quote?sent=1');
    }

    public function adminDashboard(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        $db = $this->db();
        return $this->adminPage($request, 'admin-dashboard', [
            'counts' => [
                'new' => (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_inquiries') . "` WHERE status = 'new'"),
                'all' => (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_inquiries') . '`'),
                'items' => (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_menu_items') . '`'),
                'packages' => (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_packages') . '`'),
            ],
            'recent' => $db->fetchAll('SELECT * FROM `' . $db->table('catering_inquiries') . '` ORDER BY id DESC LIMIT 8'),
        ], 'Catering');
    }

    public function adminMenus(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        [$categories, $items] = $this->menuData(false);
        return $this->adminPage($request, 'admin-menus', [
            'categories' => $categories, 'items' => $items,
            'saved' => $request->string('saved') === '1',
        ], 'Catering Menus');
    }

    public function adminMenusSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        $db = $this->db();
        $action = $request->string('action');
        if ($action === 'add_category') {
            $name = mb_substr($request->string('name'), 0, 190);
            if ($name !== '') {
                $db->insert('catering_menu_categories', [
                    'slug' => $this->uniqueSlug('catering_menu_categories', $name),
                    'name' => $name,
                    'description' => mb_substr($request->string('description'), 0, 2000),
                    'sort_order' => (int) $request->string('sort_order', '0'),
                    'visible' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } elseif ($action === 'add_item') {
            $name = mb_substr($request->string('name'), 0, 190);
            $categoryId = (int) $request->string('category_id');
            $categoryExists = $db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $db->table('catering_menu_categories') . '` WHERE id = ?',
                [$categoryId]
            );
            if ($name !== '' && (int) $categoryExists === 1) {
                $db->insert('catering_menu_items', [
                    'category_id' => $categoryId,
                    'slug' => $this->uniqueSlug('catering_menu_items', $name),
                    'name' => $name,
                    'description' => mb_substr($request->string('description'), 0, 3000),
                    'price_cents' => max(0, (int) round(((float) $request->string('price', '0')) * 100)),
                    'price_unit' => mb_substr($request->string('price_unit', 'per person'), 0, 80),
                    'dietary' => mb_substr($request->string('dietary'), 0, 255),
                    'featured' => $request->string('featured') === '1' ? 1 : 0,
                    'visible' => 1,
                    'sort_order' => (int) $request->string('sort_order', '0'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } elseif ($action === 'toggle_item') {
            $db->run('UPDATE `' . $db->table('catering_menu_items') . '` SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END WHERE id = ?',
                [(int) $request->string('id')]);
        } elseif ($action === 'delete_item') {
            $db->run('DELETE FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [(int) $request->string('id')]);
        }
        return Response::redirect($request->basePath . '/admin/catering/menus?saved=1');
    }

    public function adminPackages(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        return $this->adminPage($request, 'admin-packages', [
            'packages' => $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_packages') . '` ORDER BY sort_order, name'),
            'saved' => $request->string('saved') === '1',
        ], 'Catering Packages');
    }

    public function adminPackagesSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        $db = $this->db();
        $action = $request->string('action');
        if ($action === 'add_package') {
            $name = mb_substr($request->string('name'), 0, 190);
            if ($name !== '') {
                $db->insert('catering_packages', [
                    'slug' => $this->uniqueSlug('catering_packages', $name),
                    'name' => $name,
                    'eyebrow' => mb_substr($request->string('eyebrow'), 0, 120),
                    'description' => mb_substr($request->string('description'), 0, 3000),
                    'price_cents' => max(0, (int) round(((float) $request->string('price', '0')) * 100)),
                    'price_note' => mb_substr($request->string('price_note', 'starting per guest'), 0, 120),
                    'guest_min' => max(1, (int) $request->string('guest_min', '10')),
                    'guest_max' => $request->string('guest_max') !== '' ? max(1, (int) $request->string('guest_max')) : null,
                    'inclusions' => mb_substr($request->string('inclusions'), 0, 5000),
                    'featured' => $request->string('featured') === '1' ? 1 : 0,
                    'visible' => 1,
                    'sort_order' => (int) $request->string('sort_order', '0'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } elseif ($action === 'toggle_package') {
            $db->run('UPDATE `' . $db->table('catering_packages') . '` SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END WHERE id = ?',
                [(int) $request->string('id')]);
        } elseif ($action === 'delete_package') {
            $db->run('DELETE FROM `' . $db->table('catering_packages') . '` WHERE id = ?', [(int) $request->string('id')]);
        }
        return Response::redirect($request->basePath . '/admin/catering/packages?saved=1');
    }

    public function adminInquiries(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.inquiries')) {
            return $forbidden;
        }
        $status = $request->string('status');
        $sql = 'SELECT * FROM `' . $this->db()->table('catering_inquiries') . '`';
        $params = [];
        if (in_array($status, self::STATUSES, true)) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY id DESC LIMIT 250';
        return $this->adminPage($request, 'admin-inquiries', [
            'rows' => $this->db()->fetchAll($sql, $params),
            'statuses' => self::STATUSES,
            'selectedStatus' => $status,
            'saved' => $request->string('saved') === '1',
        ], 'Catering Leads');
    }

    public function adminInquirySave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.inquiries')) {
            return $forbidden;
        }
        $status = $request->string('status');
        if (in_array($status, self::STATUSES, true)) {
            $this->db()->update('catering_inquiries', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int) $request->string('id')]);
        }
        return Response::redirect($request->basePath . '/admin/catering/inquiries?saved=1');
    }

    public function adminSettings(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        $settings = $this->settings();
        return $this->adminPage($request, 'admin-settings', [
            'values' => [
                'phone' => $settings->get('catering.phone', '(555) 123-4567'),
                'service_area' => $settings->get('catering.service_area', 'Your city and surrounding communities'),
                'travel_note' => $settings->get('catering.travel_note', 'Travel and delivery fees may apply outside our primary service area.'),
                'minimum_guests' => $settings->get('catering.minimum_guests', '20'),
                'lead_time' => $settings->get('catering.lead_time', '14'),
                'quote_expiry' => $settings->get('catering.quote_expiry', '14'),
            ],
            'saved' => $request->string('saved') === '1',
        ], 'Catering Settings');
    }

    public function adminSettingsSave(Request $request): Response
    {
        if ($forbidden = $this->guard('catering.manage')) {
            return $forbidden;
        }
        $settings = $this->settings();
        $settings->set('catering.phone', mb_substr($request->string('phone'), 0, 80));
        $settings->set('catering.service_area', mb_substr($request->string('service_area'), 0, 500));
        $settings->set('catering.travel_note', mb_substr($request->string('travel_note'), 0, 1000));
        $settings->set('catering.minimum_guests', max(1, (int) $request->string('minimum_guests', '20')));
        $settings->set('catering.lead_time', max(0, (int) $request->string('lead_time', '14')));
        $settings->set('catering.quote_expiry', max(1, (int) $request->string('quote_expiry', '14')));
        return Response::redirect($request->basePath . '/admin/catering/settings?saved=1');
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
        $shared = [
            'basePath' => $request->basePath,
            'currentPath' => rtrim($request->path, '/') ?: '/',
            'siteName' => (string) $settings->get('site.name', 'Maison & Meadow Catering'),
            'tagline' => (string) $settings->get('site.tagline', 'Gather beautifully.'),
            'phone' => (string) $settings->get('catering.phone', '(555) 123-4567'),
            'contactEmail' => (string) $settings->get('site.contact_email', 'hello@example.com'),
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
        return Response::html($view->render($this->ns . ':layout', array_merge($shared, ['content' => $body])));
    }

    private function adminPage(Request $request, string $template, array $data, string $title): Response
    {
        $auth = $this->ctx->service(AuthService::class);
        $view = $this->ctx->service(View::class);
        $view->share('user', $auth->user());
        $view->share('csrf', $this->ctx->service(Csrf::class));
        $view->share('basePath', $request->basePath);
        $view->share('currentPath', $request->path);
        $view->share('nav', $this->ctx->service(Nav::class)->visible($auth->user()));
        $view->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        $view->share('pageTitle', $title);
        $view->share('flash', null);
        return Response::html($view->renderInLayout($this->ns . ':' . $template, 'admin:layouts/admin', $data));
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
            'menu_interest' => $data['menu_interest'] ?? '',
            'budget' => $data['budget'] ?? '',
            'dietary' => $data['dietary'] ?? '',
            'message' => $data['message'] ?? '',
            'source_ip' => $request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ];
        $id = $this->db()->insert('catering_inquiries', $record);
        $to = (string) $this->settings()->get('site.contact_email', '');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $lines = [];
            foreach ($record as $key => $value) {
                if ($value !== null && $value !== '') {
                    $lines[] = ucwords(str_replace('_', ' ', $key)) . ': ' . (string) $value;
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

    private function contactInput(Request $request): array
    {
        return [
            'name' => mb_substr($request->string('name'), 0, 190),
            'email' => mb_substr($request->string('email'), 0, 190),
            'phone' => mb_substr($request->string('phone'), 0, 80),
            'message' => mb_substr($request->string('message'), 0, 5000),
        ];
    }

    private function quoteInput(Request $request): array
    {
        return [
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
            'menu_interest' => mb_substr($request->string('menu_interest'), 0, 190),
            'budget' => mb_substr($request->string('budget'), 0, 100),
            'dietary' => mb_substr($request->string('dietary'), 0, 3000),
            'message' => mb_substr($request->string('message'), 0, 5000),
        ];
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

    private function quoteErrors(array $data): array
    {
        $errors = $this->contactErrors([
            'name' => $data['name'], 'email' => $data['email'],
            'message' => $data['message'] !== '' ? $data['message'] : 'Event details supplied',
        ]);
        if ($data['event_type'] === '') {
            $errors[] = 'Please choose an event type.';
        }
        if ($data['event_date'] === '') {
            $errors[] = 'Please provide an event date.';
        }
        if ((int) $data['guest_count'] < 1 || (int) $data['guest_count'] > 10000) {
            $errors[] = 'Guest count must be between 1 and 10,000.';
        }
        return $errors;
    }

    private function menuData(bool $visibleOnly = true): array
    {
        $where = $visibleOnly ? ' WHERE visible = 1' : '';
        return [
            $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_menu_categories') . '`' . $where . ' ORDER BY sort_order, name'),
            $this->db()->fetchAll('SELECT * FROM `' . $this->db()->table('catering_menu_items') . '`' . $where . ' ORDER BY category_id, sort_order, name'),
        ];
    }

    private function visiblePackages(?int $limit = null): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('catering_packages')
            . '` WHERE visible = 1 ORDER BY sort_order, name' . ($limit !== null ? ' LIMIT ' . $limit : '')
        );
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

    private function serviceData(): array
    {
        return [
            'weddings' => [
                'title' => 'Wedding Catering', 'short' => 'Weddings',
                'summary' => 'A celebration designed around your story, from the first toast to the final sweet bite.',
                'kicker' => 'Your table, your story', 'image' => 'wedding-table.webp',
                'intro' => 'We shape the menu, service rhythm, and finishing details around the way you want your wedding to feel—warm, polished, and entirely personal.',
                'features' => ['Private menu consultation', 'Tasting experience', 'Cocktail-hour bites', 'Plated, buffet, or station service', 'Professional service team', 'Rental and timeline coordination'],
                'process' => ['Share your vision and venue', 'Taste and refine your menu', 'Finalize service and timeline', 'Celebrate while we handle the details'],
            ],
            'corporate-events' => [
                'title' => 'Corporate Catering', 'short' => 'Corporate Events',
                'summary' => 'Reliable, polished food service for meetings, conferences, launches, and company celebrations.',
                'kicker' => 'Business, beautifully hosted', 'image' => 'corporate-service.webp',
                'intro' => 'From an executive breakfast to a multi-day conference, our corporate menus are designed for timely service, clear planning, and an excellent guest experience.',
                'features' => ['Breakfast and lunch service', 'Boxed and individually packaged meals', 'Conference buffets', 'Cocktail receptions', 'Dietary meal labeling', 'Recurring-event planning'],
                'process' => ['Tell us the schedule', 'Choose service and menu', 'Confirm delivery details', 'Host with confidence'],
            ],
            'private-celebrations' => [
                'title' => 'Private Celebrations', 'short' => 'Private Celebrations',
                'summary' => 'Birthdays, anniversaries, showers, graduations, and gatherings made effortless.',
                'kicker' => 'Make the moment yours', 'image' => 'hero.webp',
                'intro' => 'Whether the guest list is intimate or expansive, we create a relaxed hospitality experience with food that fits the people and the occasion.',
                'features' => ['Flexible menu formats', 'Cocktail and dinner service', 'Family-style options', 'Celebration desserts', 'Bartending coordination', 'Home and venue service'],
                'process' => ['Choose the occasion', 'Build the menu', 'Add service details', 'Gather and enjoy'],
            ],
            'drop-off-catering' => [
                'title' => 'Drop-Off Catering', 'short' => 'Drop-Off Catering',
                'summary' => 'Freshly prepared, ready-to-serve catering for offices, family gatherings, and casual events.',
                'kicker' => 'Easy to serve, hard to forget', 'image' => 'plated-dinner.webp',
                'intro' => 'Choose your menu and delivery window, and we will arrive with labeled dishes, serving guidance, and everything arranged for a smooth meal.',
                'features' => ['Scheduled delivery or pickup', 'Disposable service ware', 'Hot and cold packaging', 'Clear dish labels', 'Dietary accommodations', 'Optional setup service'],
                'process' => ['Select a menu', 'Confirm headcount', 'Choose delivery or pickup', 'Unpack and serve'],
            ],
        ];
    }

    private function faqData(): array
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
