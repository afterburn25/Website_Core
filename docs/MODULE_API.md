# Module API (extension API v1)

All packages must also follow the permanent ownership, administration,
lifecycle, and release rules in the root `MOD_RULES.md` and detailed
`MODULE_RULES.md`.

Packages interact with core exclusively through `Core\Modules\ModuleContext`,
passed to the package provider's `register()` at boot. Anything not on this
surface is internal and may change without notice.

## API version

`WC_API_VERSION` (currently **1**). A package declares `requires.api`; major
mismatches block enablement. Patch/minor core releases keep API-1 stable;
breaking changes bump the major and ship with a migration guide.

## Registration surface (v1)

| Method | Purpose |
|--------|---------|
| `route($methods, $pattern, $handler, $middleware = [])` | Register HTTP routes (`{param:regex}` syntax). Module routes register before the public slug catch-all. |
| `adminRoute($methods, $pattern, $handler)` | Register a route inside the same authenticated-session + CSRF pipeline as every core admin screen. The handler must still check its own declared capability. |
| `on($event, $listener)` | Subscribe to core lifecycle events (fault-isolated). |
| `adminPage($label, $route, $capability, $category = null, $icon = 'modules')` | Add an admin nav item inside the package's own collapsible category, optionally using a safe Core icon token; authorization is still checked server-side per route. |
| `adminResource(..., $category = 'Industry', $options = [])` | Register a Core-rendered package resource; `options.icon` assigns its safe administration navigation icon alongside reorder and bulk-action options. |
| `adminView($request, $view, $data, $title, $capability)` | Render a registered module view inside the authenticated Core admin shell with a server-side capability check. |
| `adminResource(..., $delete = null, $category = 'Industry', $options = [])` | Register a validated list/editor workspace with optional visible-row ordering and allowlisted bulk actions. |
| `scheduledJob($type, $handler)` | Register an idempotent job handler executed by the queue runner. |
| `contentType($type, $label, $fields)` | Register a structured content type — validated storage in `content_items` plus generated admin CRUD under `/admin/content`. |
| `seedContent($type, $items)` | Seed declared structured content once while its package partition is empty; administrator edits and intentional deletion remain final. |
| `structuredData($key, $provider)` | Contribute bounded package-scoped Schema.org objects to public HTML. Core validates types/properties, supplies `@context`, safely encodes JSON-LD and isolates provider failures. |
| `starterForm($key, $label, $fields, $options = [], $validator = null, $onSubmitted = null)` | Declare or deliberately adopt one Core-owned starter form while retaining package business validation and record creation. |
| `formSubmission($submissionId)` | Read a protected submission, consent, notification status and record links only when it belongs to this package. |
| `linkFormSubmission($submissionId, $recordType, $recordId)` | Idempotently connect a package record to its Core submission without copying the shared form/inbox. |
| `presentationBlocks()` | Read Core's canonical FAQ, gallery, testimonial, service, team and CTA block definitions for module editors/rendering. |
| `coreServiceVersion($service)` | Read the installed semantic version of a named Core service contract declared in `requires.services`. |
| `serviceArea($defaults = [])` | Enable the shared structured service-area profile with package defaults. |
| `geocodingProvider($name, $geocoder, $attribution)` | Register this package's server-side geocoder without exposing or storing provider credentials. |
| `configureServiceArea($input, $provider = '')` | Validate/geocode and save the shared center/radius through a provider registered by this package. |
| `checkServiceAddress($address, $provider = '')` | Resolve a package-scoped inside/outside/unknown decision through a registered provider, including an audited override. |
| `overrideServiceAddress($address, $inside, $reason, $adminId, $expiresAt = null)` | Record an audited, optionally expiring staff decision for an address requiring review. |
| `serviceAreaMap($label = 'Service area')` | Render the accessible attributed no-JavaScript radius component and text fallback. |
| `themeCollection($label, $variants)` | Register package-confined layouts/assets, an optional packaged preview thumbnail, contrast-safe palettes and Core Media slots; Core renders the visual cards, palette dropdown and Activate action. |
| `activeModuleTheme($draft = false)` | Read the package's published or draft module-theme selection with safe layout/asset paths. |
| `checkoutValidator($validator, $options = [])` | Register one fail-closed package checkout validator with bounded timeout/context and supported outcomes. |
| `quoteAttachment($quoteId, $mediaId, $label, $adminId = null)` | Attach a public Core Media record to a package-owned quote. |
| `quoteReminder($quoteId, $dueAt, $recipient, $message, $channel = 'email')` | Schedule an idempotent package-owned quote reminder through the Core queue. |
| `quoteDocument($quoteId, $kind, $title, $html, $adminId = null)` | Save a sanitized, integrity-checked package-rendered proposal/contract/summary snapshot. |
| `dashboardWidget($key, $label, $provider, $capability = null, $url = null)` | Contribute a capability-filtered live metric to the Core dashboard. |
| `adminSearchProvider($provider, $capability = null)` | Contribute capability-filtered results to Core global search. |
| `healthCheck($key, $label, $provider)` | Contribute an isolated `ok`, `warn`, or `error` check to System Health. |
| `navigationMenu($items, $location = 'primary', $label = 'Primary navigation')` | Declare a module-specific starting menu. Core imports it once into the editable navigation tree without overwriting an existing or intentionally cleared menu. |
| `navigationTree($location = 'primary')` | Read the current Core-managed navigation tree for module rendering, falling back to the declared defaults before import. |
| `brandAssets($assets)` | Declare package-relative starting `logo`, `favicon`, and/or `social` images. Core validates and copies them into Media once without overwriting or repopulating administrator choices. |
| `brandAssetUrl($request, $role, $width = null)` | Resolve the current Core-owned public URL for a logo, favicon, or social image in module layouts. |
| `copyrightNotice($siteName)` | Resolve Core's custom copyright text after expanding `{year}` and `{site_name}`. Escape it when rendering. |
| `socialLinks($location = null)` | Read Core's validated, nonblank social profiles, optionally filtered to `header` or `footer`. |
| `coreFeature($feature, $options = [])` | Opt into one allowlisted optional Core surface. Undeclared features remain hidden and unreachable. |
| `commerce($options = [])` | Opt into selected `catalog`, `cart`, `checkout`, and `orders` surfaces and supply store currency/fulfillment defaults. |
| `customerAccounts($options = [])` | Opt into public customer registration, sign-in, recovery, profile/order history, and Core customer administration. |
| `permission($capability, $description)` | Declare a package capability. Owner always holds it; `admin` does by default. Packages can never grant core capabilities. |
| `views($dir)` | Register a view directory, resolving as `mod-<slug>:<name>`; active-theme overrides at `templates/modules/<pkg-id>/<name>.php` win, package views fall back. Returns the namespace. |
| `service($id)` | Resolve a core service (`View`, `SettingsRepository`, `JobQueue`, `SystemMail`, `ContentItems`, …). Packages are trusted in-process code — this is the supported way to reach shared infrastructure. |
| `packageId()` | The package's own `publisher/name` id. |
| `assetBase($basePath = '')` | Return the rewrite-independent public asset prefix for the installed package directory. Use this for CSS, JavaScript, images, and fonts instead of deriving a route from the manifest id. |
| `routeBase($basePath = '')` | Return the rewrite-independent `index.php?p=` prefix used by package views that append application routes. |
| `url($basePath, $path = '/', $query = [])` | Build a complete rewrite-independent application URL, including additional query parameters. |

Relative image and font references inside package CSS are automatically
rebased onto the rewrite-independent asset route by the core asset server.

### Reuse platform services (Core 2.6)

Packages may declare compatible shared-service ranges in `addon.json`:

```json
{
  "requires": {
    "core": ">=2.6.0,<3.0.0",
    "api": "^1.0",
    "services": {
      "content.presentation": "^1.0",
      "leads.quotes": "^2.0",
      "business.service_area": "^1.0",
      "appearance.collections": "^1.0",
      "commerce.checkout_validation": "^1.0"
    }
  }
}
```

Core checks these constraints before package provider code runs. An unknown or
incompatible service quarantines the package. These contracts are optional:
existing API-v1 packages that do not declare `requires.services` remain
compatible.

Geocoding and checkout callbacks execute server-side, are package scoped and
fail closed. Keep provider credentials captured in callback configuration,
never in returned data. Quote document HTML is sanitized before Core stores an
immutable snapshot; modules own the source template and business language.
Module-theme paths must resolve inside the installed package, while media slots
refer to public Core Media IDs. See `docs/2.6.0_RELEASE_NOTES.md` for the full
contract and remaining packaged-host/adoption gates.

Each enabled package receives its **own** `ModuleContext` stamped with its
packageId — registrations are attributed to the package and aggregated by
the `ModuleRegistry` (`adminNavItems()`, `jobHandlers()`, `contexts()`).
Registration values are validated. Admin pages/routes must stay under
`/admin/`, contribution keys are unique within the package, navigation links
must use safe supported destinations, and brand defaults remain package
relative. Invalid registration quarantines that package rather than taking
down Core.

### Module-owned navigation defaults

Industry modules declare the navigation that suits their site. Core imports
that declaration into **Website → Navigation Menu** the first time the module
boots, then owns the saved copy so administrators can rename, reorder, nest,
retarget, add, or remove links. Later module boots and upgrades never replace
an existing menu or repopulate one the administrator intentionally cleared.

```php
$ctx->navigationMenu([
    ['label' => 'Our Story', 'url' => '/about'],
    ['label' => 'Menus', 'url' => '/menus'],
    ['label' => 'Contact', 'url' => '/contact', 'target' => 'tab'],
]);
```

Public module layouts render `$ctx->navigationTree()` rather than maintaining
a separate hard-coded header menu. Modules may also declare a footer location:

```php
$ctx->navigationMenu([
    ['label' => 'Privacy', 'url' => '/privacy'],
    ['label' => 'Contact', 'url' => '/contact'],
], 'footer', 'Footer navigation');

$footerLinks = $ctx->navigationTree('footer');
$copyright = $ctx->copyrightNotice($siteName);
$headerSocial = $ctx->socialLinks('header');
$footerSocial = $ctx->socialLinks('footer');
```

Core exposes both locations in Website → Navigation Menu. Header and footer
defaults are imported independently and administrator changes remain final.
Modules render only the returned social records, using Core's `SocialIcon`
helper when they need the standard dependency-free SVG marks. Core applies one
global placement—header, footer, or both—to every configured profile; a blank
profile appears nowhere.

### Optional Core features

Commerce and customer accounts are present in Core but dormant by default.
An enabled industry module must opt in; disabling that module immediately
removes the corresponding public routes, administrator navigation, and role
choices without deleting records. Re-enabling the module restores them.

```php
$ctx->commerce([
    'store_name' => 'Catering Co. Store',
    'currency' => 'USD',
    'fulfillment' => ['pickup', 'delivery'],
    // Optional: 'features' => ['catalog', 'cart', 'checkout', 'orders'],
]);
$ctx->customerAccounts([
    'registration' => true,
    'account_label' => 'My catering account',
]);
```

Core owns generic products, inventory, session carts, order snapshots,
customer identities, and password recovery. The declaring module remains the
owner key on every product/order/account. Modules own product meaning,
merchandising, and industry rules. Gateway SDKs, automatic payment
confirmation, tax calculation, and industry-specific fulfillment stay in
separate packages. Core's built-in manual methods are Venmo, PayPal, and Cash
App; administrators enable them and provide instructions. Orders remain
`payment pending` until staff explicitly records receipt.

A module may seed a starter catalog once through the supported
`ProductRepository` service, guarded by a package-specific setting marker.
Only seed when that package has no Core products; never overwrite live Core
catalog records on boot or upgrade. Explicit package purge removes both the
package partition and its commerce seed marker so a clean reinstall can seed
again.

### Module-supplied brand defaults

An industry module may ship presentation-ready starting images, but it never
owns the live brand settings. Core confines each path to the installed package,
validates the image through the Media pipeline, copies it into protected Core
storage, records its usage, and marks the package seed complete. Existing Core
selections win. The marker remains when an administrator later clears an
asset, so a request or upgrade cannot silently restore it.

```php
$ctx->brandAssets([
    'logo' => 'assets/images/company-logo.png',
    'favicon' => 'assets/images/favicon.png', // optional
]);

$logoUrl = $ctx->brandAssetUrl($request, 'logo', 480);
$faviconUrl = $ctx->brandAssetUrl($request, 'favicon');
```

Use only package-relative paths declared in `addon.json` assets. Public and
module-admin layouts must resolve the current Core value with
`brandAssetUrl()`; they must not link directly to the shipped default after
installation.

### Shared admin surfaces

The Core `/admin` Dashboard is the only overview. Industry modules contribute
compact, capability-filtered summaries through `dashboardWidget()` and link
those summaries to their real workspaces; they do not register a duplicate
module Dashboard page. Module categories contain actionable management screens.

```php
$ctx->dashboardWidget(
    'open-items', 'Open items',
    fn () => ['value' => 12, 'detail' => 'Needs attention'],
    'vendor.items.view', '/admin/vendor/items'
);

$ctx->adminSearchProvider(fn (string $query) => [[
    'type' => 'Vendor item', 'title' => 'Matching record',
    'detail' => 'Optional context', 'url' => '/admin/vendor/items',
]], 'vendor.items.view');

$ctx->healthCheck('storage', 'Package storage',
    fn () => ['status' => 'ok', 'detail' => 'Ready']);
```

Provider exceptions are isolated and logged. Dashboard and search entries
are filtered by capability; health checks appear only on the protected
System Health screen.

## Events fired by core

`on()` listeners receive a payload array; a throwing listener is logged and
isolated — it never aborts the operation that fired the event.

| Event | Payload | Fired when |
|-------|---------|-----------|
| `page.saved` | `page`, `slug`, `by` | a page is written (draft or edit) |
| `page.published` / `page.draft` | `page`, `by` | publish/unpublish |
| `media.uploaded` | `media`, `by` | a media file is stored |
| `backup.created` / `backup.restored` | `file`, `by` | a backup finishes / restores |
| `package.installed` / `package.upgraded` | `package`, `version`, `dir` | install completes |
| `package.enabled` / `package.disabled` | `package` | lifecycle flips |
| `package.uninstalled` | `package`, `data` | files removed, data preserved |
| `package.purged` | `package` | package-owned tables dropped |
| `core.update` | audit entry | a core self-update applies |
| `commerce.order.placed` | `id`, `order_number`, `payment_method`, `total_minor`, `currency` | an order transaction commits |
| `form.submitted` | `id`, `form_id`, `owner_package`, `form_key` | a validated form submission is stored; field data remains limited to the package-bound `onSubmitted` callback and retry duplicates do not fire again |

## Content types

`contentType($type, $label, $fields)` registers a structured content type.
Each field is `['name' => snake_case, 'label' => ..., 'type' => ...,
'required' => bool, 'options' => [...] (select only)]` with `type` one of
`text`, `textarea`, `html` (HTMLPurifier-sanitized), `number`, `url`
(http/https/mailto), `select`, `checkbox`, `media`, and `media_list`.
Media fields select public Core Media records and retain alt text, focal crop,
replacement, and usage tracking instead of storing copied file paths.
Undeclared keys are dropped at save time and `required`/`type` violations are
rejected.

Core stores items in `content_items` and generates the admin CRUD at
`/admin/content` (capability `content.manage`). Packages read their own
items back — presentation stays package-owned:

```php
$items = $ctx->service(\Core\Content\ContentItems::class)
    ->items($ctx->packageId(), 'faq', publishedOnly: true);
```

Each item exposes `id`, `title` (derived from the first text-ish field or
an explicit `title`), `status` (`draft`/`published`), `sort`, and a decoded
`fields` map. The restaurant reference package uses this for `special`
(daily specials) end-to-end.

Starter records may be declared after the type. Core inserts them only before
that package/type has live records and records a durable package marker. Later
boots and upgrades never replace edits or repopulate content an administrator
intentionally removed:

```php
$ctx->seedContent('faq', [
    ['question' => 'How far do you travel?', 'answer' => 'See our service area.'],
]);
```

### Package structured data

Packages may describe their live records without writing script tags or
editing Core's schema renderer. Providers receive the current request and
return one Schema.org object or a list. Core removes package-supplied
`@context`, accepts only bounded JSON values and allowlisted business/content
types, escapes JSON-LD against markup termination, and combines successful
providers into one `@graph`. Exceptions are logged and isolated.

```php
$ctx->structuredData('services', fn (\Core\Http\Request $request) => [[
    '@type' => 'Service',
    '@id' => 'https://' . $request->host() . '/services#weddings',
    'name' => 'Wedding catering',
]]);
```

## Shared forms and notifications (Core 2.5)

Declare a starter form during package registration. The declaration seeds only
when that package/key does not already exist; the administrator-owned live
copy is never replaced during later boots or upgrades.

```php
$ctx->starterForm(
    'event_request',
    'Event request',
    [
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ['name' => 'guests', 'label' => 'Guest count', 'type' => 'number', 'required' => true],
        ['name' => 'dietary', 'label' => 'Dietary needs', 'type' => 'textarea'],
    ],
    [
        'recipient' => 'events@example.com',
        'subject' => 'Event request {{submission_id}}',
        'notification_template' => "{{email}} requested service for {{guests}} guests.\n\n{{dietary}}",
        'success_message' => 'Thank you. We will review your event request.',
        'submit_label' => 'Request information',
    ],
    validator: function (array $data): array {
        return (int) $data['guests'] < 1
            ? ['guests' => 'Enter at least one guest.'] : [];
    },
    onSubmitted: function (array $event) use ($ctx, $inquiries): void {
        [$inquiryId, $error] = $inquiries->submit(
            $ctx->packageId(),
            [
                'email' => $event['data']['email'],
                'subject' => 'Event request',
                'message' => 'Guest count: ' . $event['data']['guests'],
            ],
            $event['id'],
        );
        if ($error === null) {
            $ctx->linkFormSubmission($event['id'], 'inquiry', $inquiryId);
        }
    },
);
```

Core validates and stores generic field types, CSRF, honeypot and rate-limit
checks, consent evidence, inbox state, CSV safety, notification queuing and
delivery status. A validator receives cleaned Core fields and the current form
record. It returns `[]`, `null`, or `true` for success; a string/list for
general errors; or `field_name => message` for labeled field errors. Exceptions
fail closed and are logged without exposing internals.

The optional submission handler runs only after a new durable submission is
stored. It receives the same protected ID as `form.submitted`. Package failures
are isolated so the customer response remains in Core for manual recovery.
Browser retries with the same generated token return the original submission,
do not call the handler/event again, and reuse the attached mail evidence.

Use `adopt_id` only during a deliberate migration from an existing Core form:

```php
$ctx->starterForm('event_request', 'Event request', $fields, [
    'adopt_id' => 12,
]);
```

Core keeps that form's edited schema and reattributes its existing submissions;
it never guesses by name. A package cannot adopt another package's form.
Disable and uninstall preserve everything and make the public form unavailable
while its package validation code is absent. Explicit purge removes only the
package partition.

## Shared business, inquiries, and quotes (Core 2.2)

`businessProfile()` opts a module into the shared business identity and may
seed blank values once. Later module boots never overwrite administrator
edits. Read the current record through `BusinessProfile`:

```php
$ctx->businessProfile([
    'name' => 'Example Company',
    'service_area' => 'Lafayette and surrounding communities',
]);
$profile = $ctx->service(\Core\Business\BusinessProfile::class)->get();
```

`leadManagement()` enables package-scoped inquiries and, by default, quotes.
The module decides when its validated industry form becomes an inquiry:

```php
$ctx->leadManagement(['quotes' => true, 'label' => 'Event sales']);
[$id, $error] = $ctx->service(\Core\Leads\InquiryService::class)->submit(
    $ctx->packageId(),
    ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $summary],
    $coreSubmissionId,
);
```

The optional source-submission id deduplicates retries. Core owns assignment,
priority, internal notes, itemized quote arithmetic, expiry, and secure
customer acceptance. Industry questionnaires, eligibility, estimation,
per-guest/per-acre formulas, contracts, and scheduling stay module-owned.
Disable/uninstall hides these surfaces and preserves rows; explicit package
purge removes the package partition.

## Reusable module administration (Core 2.2–2.3)

After registering a view namespace with `views()`, `adminView()` renders the
package view inside the complete Core shell and enforces its capability.
For ordinary module records, `adminResource()` generates a consistent
list/editor workflow while persistence stays in callbacks:

```php
$ctx->adminResource(
    'services', 'Services', 'catering.manage',
    [['name' => 'name', 'label' => 'Name', 'required' => true]],
    [['name' => 'name', 'label' => 'Name']],
    fn (string $search, int $page, int $perPage) => $repo->page($search, $page, $perPage),
    fn (int $id) => $repo->find($id),
    fn (?int $id, array $data) => $repo->save($id, $data),
    fn (int $id) => $repo->delete($id),
    'Catering',
    [
        'reorder' => fn (array $visibleIds) => $repo->reorderVisible($visibleIds),
        'bulk' => fn (array $ids, string $action) => $repo->bulk($ids, $action),
        'bulk_actions' => ['publish' => 'Publish', 'archive' => 'Archive'],
    ],
);
```

Supported generated field types are `text`, `textarea`, `email`, `url`,
`number`, `date`, `select`, and `checkbox`. Core supplies allowlisted input,
validation, search, pagination, permission checks, CSRF, confirmation, audit,
empty/error states, and responsive presentation. Callbacks must still enforce
package ownership and domain-specific rules.

The optional `reorder` callback receives the unique positive IDs visible on
the current page, in their submitted order. It must update only records owned
by the registering package; search and pagination mean the list is not a
promise of every package record. The optional `bulk` callback receives up to
100 unique positive IDs plus one action token declared in `bulk_actions`.
Core rejects undeclared actions and malformed IDs before invoking either
callback. Return `true` or `null` for success, or a safe administrator-facing
error string. Core supplies selection/order controls, confirmation, permission
and CSRF enforcement, bounded input, success feedback, redirects and audit
events; the module remains responsible for transactional persistence and
record ownership.

## Contracts

- **Persistence** — a module owns its tables (created by its own
  migrations, named `<package>_<table>`, declared via the manifest
  `migrations` directory of `Migration`-returning PHP files run by
  `PackageManager::install()`). It may never read or write another
  package's tables. Uninstall preserves them; `purgeData()` drops only
  `<prefix><package-slug>_*` after typed-id confirmation.
- **Views** — module templates resolve `pkg-id/view` with theme overrides
  at `themes/<active>/modules/<pkg-id>/<view>.php`; missing/incompatible
  overrides fall back to the module's shipped view.
- **Assets** — declared in the manifest, served via
  `/_a/modules/<pkg-id>/...`, scoped to the module (no global restyling).
- **Dependencies** — PHP library deps are resolved at package build time
  and vendored inside the package ZIP; modules never run Composer on the
  customer host or overwrite shared vendor files.
- **Trust** — package PHP executes in-process as trusted code. The
  capability manifest documents intent for review/policy; it cannot
  contain malicious code (see THREAT_MODEL).

## Not in v1 (documented extension points for later)

- SSO/auth-provider implementations beyond the administrator realm.
- Marketplace/acquisition integrations; packages arrive via trusted
  download/upload in 1.0.
- Webhook/signature helpers — generic inbound route registration exists
  today; provider-specific verification is package code.
