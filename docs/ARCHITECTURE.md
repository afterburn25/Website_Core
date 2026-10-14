# Architecture

## Overview

A single PHP application — no microservices, no home-grown framework
beyond a deliberately small kernel. All HTTP enters through `site/index.php`;
the installer has its own thin entry (`site/install.php`) that is removed or
permanently blocked after setup.

```
Request → index.php → bootstrap.php → Application
  → bootServices (config, PDO, session, csrf, repos, view resolvers)
  → registerRoutes (core routes) → bootModules (enabled packages)
  → Router dispatch (middleware pipeline) → Response + security headers
```

## Layers

- **Container** (`Core\Container`) — explicit bindings + reflection
  autowiring. No scopes/proxies.
- **HTTP** (`Core\Http\Request|Response`) — base-path aware request for
  root/subfolder deploys and the `?p=` no-rewrite fallback; response value
  object with headers.
- **Routing** (`Core\Routing\Router`) — `{param:regex}` placeholders,
  groups, per-route + global middleware. Modules register via
  `ModuleContext::route()` — never by editing the router.
- **Database** (`Core\Database\Connection|Migrator|Migration`) — thin PDO
  wrapper, prepared statements only, table prefix. Migrations are ordered,
  journaled (`migrations` table: id, batch, checksum), and written to be
  re-runnable; MySQL DDL is non-transactional by nature — recovery is
  restore-based, not rollback-based.
- **Auth** (`Core\Auth\*`) — administrators only (customers are an add-on
  concern): roles owner/admin/editor + capability registry, staged
  password→MFA login, TOTP (RFC 6238) with last-counter replay protection,
  single-use hashed recovery codes, persistent session rows for
  revocation, atomic last-owner guard.
- **Security** (`Core\Security\*`) — session manager (hardened cookies,
  rotation, idle+absolute expiry), CSRF, DB-backed bounded rate limiter.
- **Settings** (`Core\Settings\SettingsRepository`) — typed JSON
  key/value store with request cache. Secrets live in `config.php`, not
  the DB.
- **View** (`Core\View\View`) — PHP templates with namespace resolvers:
  `admin:*`, `theme:*` (active theme → `base` fallback), module views with
  theme override lookup. `e()`/`e_attr()`/`e_url()` are the only raw-output
  interpolation paths.
- **Modules** (`Core\Modules\*`) — addon.json manifest validation
  (schema v1), registry discovery + lifecycle states + journal,
  deterministic dependency-ordered boot, quarantine of throwing providers.
- **Jobs** (`Core\Jobs\*`) — durable queue with worker leases, bounded
  batches, linear backoff, uncertain-state marking. Runner: `site/cron.php`
  (CLI or token-guarded HTTP) — at-least-once, handlers idempotent.
- **Events** (`Core\Events\Dispatcher`) — in-process listeners inside the
  caller's transaction; post-commit effects go through the jobs/outbox
  table, never "exactly-once" claims.
- **Audit** (`Core\Audit\AuditLog`) — durable mutations log with a
  sensitive-key redaction safety net.

## Decisions (with alternatives)

- **Small kernel, no framework** — required for shared-host portability
  and auditability. Alternative (Laravel/Symfony) rejected: runtime weight,
  hosting assumptions, upgrade cadence.
- **PHP templates, not a template language** — trusted-code model already
  (templates execute); escaping enforced via helpers + review. A sandboxed
  editor template language is a future optional feature.
- **Table-prefix, not separate DB users** — shared-host reality.
- **File-based session, PHP native** — persistent `admin_sessions` rows
  provide revocation without replacing the session engine.
- **Package PHP is in-process trusted code** — capability manifest is
  policy/documentation, not a sandbox (see THREAT_MODEL).
- **sqlite dev driver** — exists purely for local dev/test; production and
  CI acceptance are MySQL/MariaDB only.

## Namespaces

`Core\*` → `site/core/`, `Admin\*` → `site/admin/`, `Install\*` →
`site/install/`; module PSR-4 prefixes are registered per-manifest.
