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
| `on($event, $listener)` | Subscribe to core events (in-transaction dispatch). |
| `adminPage($label, $route, $capability)` | Add an admin nav item; authorization is still checked server-side per route. |
| `scheduledJob($type, $handler)` | Register an idempotent job handler executed by the queue runner. |
| `contentType($type, $label, $fields)` | Register a custom content type/field set. |
| `packageId()` | The package's own `publisher/name` id. |

## Contracts

- **Persistence** — a module owns its tables (created by its own
  migrations, named `<package>_<table>`). It may never read or write
  another package's tables.
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
