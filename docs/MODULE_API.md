# Module API (extension API v1)

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
| `adminPage($label, $route, $capability)` | Add an admin nav item; authorization is still checked server-side per route. |
| `scheduledJob($type, $handler)` | Register an idempotent job handler executed by the queue runner. |
| `contentType($type, $label, $fields)` | Register a structured content type — validated storage in `content_items` plus generated admin CRUD under `/admin/content`. |
| `permission($capability, $description)` | Declare a package capability. Owner always holds it; `admin` does by default. Packages can never grant core capabilities. |
| `views($dir)` | Register a view directory, resolving as `mod-<slug>:<name>`; active-theme overrides at `templates/modules/<pkg-id>/<name>.php` win, package views fall back. Returns the namespace. |
| `service($id)` | Resolve a core service (`View`, `SettingsRepository`, `JobQueue`, `SystemMail`, `ContentItems`, …). Packages are trusted in-process code — this is the supported way to reach shared infrastructure. |
| `packageId()` | The package's own `publisher/name` id. |
| `assetBase($basePath = '')` | Return the rewrite-independent public asset prefix for the installed package directory. Use this for CSS, JavaScript, images, and fonts instead of deriving a route from the manifest id. |
| `routeBase($basePath = '')` | Return the rewrite-independent `index.php?p=` prefix used by package views that append application routes. |
| `url($basePath, $path = '/', $query = [])` | Build a complete rewrite-independent application URL, including additional query parameters. |

Each enabled package receives its **own** `ModuleContext` stamped with its
packageId — registrations are attributed to the package and aggregated by
the `ModuleRegistry` (`adminNavItems()`, `jobHandlers()`, `contexts()`).

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

## Content types

`contentType($type, $label, $fields)` registers a structured content type.
Each field is `['name' => snake_case, 'label' => ..., 'type' => ...,
'required' => bool, 'options' => [...] (select only)]` with `type` one of
`text`, `textarea`, `html` (HTMLPurifier-sanitized), `number`, `url`
(http/https/mailto), `select`, `checkbox`. Undeclared keys are dropped at
save time and `required`/`type` violations are rejected.

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
