# Changelog

## 0.1.0-dev (unreleased)

### Added — M0 + M1 first increment
- Project skeleton, boundary/architecture/status docs, CI (lint+unit
  matrix, native MySQL/MariaDB, core-only boot).
- Runnable kernel: container, request/response, router, PDO connection,
  journaled migrator, view engine, security headers, error handling.
- One-page key-free installer with environment checks, ownership claim
  (seeded token or bound-session), schema+owner creation, self-disable.
- Administrator security: roles/capabilities, sessions w/ rotation +
  revocation + idle/absolute expiry, CSRF, rate limiting, TOTP MFA with
  replay protection, single-use recovery codes, audit log.
- Admin shell: dashboard, settings, profile, administrators, modules,
  basic pages (draft/publish/revisions).
- Package groundwork: manifest schema v1 validator, registry with
  lifecycle states/journal/dependency ordering/quarantine, ModuleContext
  v1, namespaced asset resolver.
- Jobs/outbox + token-guarded/CLI cron runner.
- Base theme + public page rendering; root/subfolder + no-rewrite support.

### Added — M2 content, media, localization
- Media library: safe-upload validation, private/protected storage,
  folders, usage tracking.
- Navigation builder (shared `Nav` service), page preview tokens,
  revisions, per-page SEO/noindex/canonical/OG fields, redirects,
  sitemap + robots.
- HTMLPurifier sanitization on page bodies (stored-XSS protection).
- Layered i18n: `Translator` (core → theme → module precedence),
  `site/locale`, `site/lang/en.php` + `site/lang/fr.php`, locale
  normalization, fallback, positional placeholders.
- Reusable content sections.

### Added — M3 package platform
- `ArchiveInspector`: traversal/symlink/case-collision/size safety gates.
- `PackageManager` lifecycle: stage → validate → install → enable →
  disable → uninstall (data-preserving) → explicit purge; journal +
  operation lock + package migrations on install; dependency ordering.
- Per-package `ModuleContext` with `route`/`adminRoute`/`views`/
  `service`/`on`/`adminPage`/`scheduledJob`/`contentType`/`permission`.
- Generic content types: `ctx->contentType()` declares a validated field
  set; core stores items in `content_items` and generates admin CRUD at
  `/admin/content` (text/textarea/html/number/url/select/checkbox,
  required, option, URL-scheme, and cross-type-boundary enforcement).
  Restaurant `special` type uses it end-to-end.
- Module Manager admin UI: upload, review, actions; recovery-mode flag
  boots trusted core only.
- SDK scaffold tool + roundtrip test.

### Added — M4 operations, updates, recovery
- BackupService (portable SQL dump; db + config keys + uploads) +
  restore + admin UI.
- Health page (versions, jobs, oldest job, backups, package failures,
  storage, disk, recent system-message queue rows), audit page,
  diagnostics bundle (config keys only, never values).
- PHPMailer SMTP transport + `system_messages` queue + cron drain;
  password write-only.
- `CoreUpdater`: staged apply with `update.json` (per-file sha256,
  `expires`, `min_core`, optional Ed25519 signature), re-inspect before
  write, zero-writes-on-hash-mismatch, site-owned-path protection,
  pre-update backups, journal/audit/migrations, opcache reset,
  maintenance-window gate (`trust.require_window`).
- `SignatureVerifier` via ext-sodium or `paragonie/sodium_compat`.
- Recovery mode; `paths.modules`/`paths.themes` config-driven.

### Added — M5 reference packages + proofs
- `examples/addon-inquiry` (form, CSRF, honeypot, persisted submissions,
  queued notification mail, protected admin list), `industry-restaurant`
  (categories/items, `/menu`), `industry-salon` (services/staff,
  `/services`), themes `terra` + `noir`.
- `ReferencePackagesTest` + `ReferenceSitesParityTest`: real lifecycle,
  real dispatch, two isolated sites, byte-identical shared add-on,
  theme-switch preserves data, uninstall preserves / purge drops.
- Router most-specific-match dispatch; public routes register after
  module boot (fixes package-route shadowing).
- Appearance manager (`theme.active` only); accessibility floor test.
- `Dispatcher` lifecycle events (`page.*`, `media.uploaded`,
  `backup.*`, `package.*`) with fault-isolated listeners.

### Added — release tooling
- `tools/build-packages.php`: FULL-PROJECT / COMPLETE-UPLOAD (prod-only
  vendor) / UPDATE (update.json, signable) / package zips / SHA256SUMS.
- `tools/verify-artifact.php`: extract + provision + serve a
  COMPLETE-UPLOAD artifact (fresh-install dogfood).
- CI `artifacts` job asserting the whole contract every push.

### Added — generic content types
- `ctx->contentType()` is now a real feature: `content_items` migration,
  `ContentItems` service (declared-key filtering, required/typed/url
  validation, html through the purifier boundary, cross-type protection),
  generated admin CRUD at `/admin/content`, and a restaurant `special`
  type rendered end-to-end on `/menu`.

### Fixed — hardening sweep
- Package purge now deletes shared `content_items` rows keyed by package
  id (only prefix-named tables were dropped).
- Backups now cover config/upload/modules/themes (matching the doc
  claims), centralize the root allowlist for create+restore, validate
  every archive member name before extraction, and normalize manifest
  paths to '/' — fixing Windows-created backups losing files on Linux
  restore. BACKUP_RESTORE.md rewritten to describe actual behavior.
- Update archives can no longer overwrite installed themes (site-owned);
  core-owned `themes/base/` still updates.
- Fresh installs now seed a published 'home' page and site.name/
  site.tagline — '/' no longer 404s and themes render the real name.
- 404 pages shared the layout without `tagline` — fixed via a single
  shareGlobals() path.
- CI: installer acceptance over real HTTP on both MySQL and MariaDB
  legs (claim session → real POST → lock/config/self-disable assertions).

### Added — administration lifecycle
- `POST /admin/users/create`: real administrator creation (username/
  email/password validation matching the installer, owner-role gated to
  owners, audited `admin.created`) — the installer owner is no longer
  the only admin the product can produce.
- `POST /admin/users/{id}/reset-password`: owner-delegated credential
  reset with session revocation so old passwords die immediately.
- Installer seeds `system.cron_token` — web-triggered cron works before
  the first settings save.

### Fixed — consistency sweep
- Theme overrides for package views resolve at
  `templates/modules/<pkg-id>/` matching THEME_API (was `modules/`).
- Sitemap excludes `noindex` pages — no longer contradicts per-page
  robots meta.
- THREAT_MODEL mitigations corrected to describe shipped controls
  (no rollback-floor/encrypted-backup claims).

### Fixed — update posture + i18n consistency
- `CoreUpdater::inspect` now enforces the rollback floor: archives older
  than the running core are refused outright (intentional recovery is
  backup restore, not an update artifact), and a manifest `version` that
  disagrees with the bundled bootstrap is rejected as lying metadata.
- Base-theme 404 uses its own catalog keys (`page.not_found*`,
  `nav.home`) — shipped catalogs no longer carry dead keys.

### Fixed — job queue + SQL identifier hardening
- `JobQueue::claim` was racy: its guard allowed re-claiming `running`
  jobs so concurrent workers executed the same job twice, and expired
  leases were never reclaimed — a crashed worker's job stayed invisible
  forever. Claims are now atomic (rowCount-checked guarded update) and
  dead leases return to `uncertain` for retry.
- `Response::header()` throws on control characters; redirect targets
  containing CR/LF are rejected at save.
- `Connection::table()/insert()/update()` allowlist SQL identifiers —
  interpolated names can no longer carry a backtick escape.
