# Changelog

## 0.1.8-dev (unreleased)

### Fixed — deploy-time cleanup and rewrite-free assets
- Restored the thin public `install.php` entry and companion `install/`
  directory to deployment bundles. Successful setup now physically removes
  both from the deployed site; completed sites retry the same exact cleanup
  on every request.
- Restored fail-closed cleanup behavior. If either canonical installer path
  remains, the website and administration area show the styled recovery page
  until **Recheck cleanup** confirms both paths are gone.
- Changed module asset URLs to the supported `index.php?p=...` fallback. This
  works on nginx/shared hosts that do not apply the bundled `.htaccess`, while
  still working on Apache and LiteSpeed.
- Added rewrite-independent package route helpers and moved every catering
  public link and form action onto the fallback route prefix, so navigation
  works on the same nginx/shared-host configuration.
- Incremented the catering module to `0.1.8`.

## 0.1.7-dev (unreleased)

### Fixed — package-owned public assets
- Added `ModuleContext::assetBase()` so every extension receives the correct
  public asset URL for its installed directory. This prevents manifest ids,
  display names, or guessed short names from producing broken asset routes.
- Updated the catering site to use the core-supplied asset base for its CSS,
  JavaScript, and service images. Subfolder installs now request assets from
  `industry-catering`, matching the bundled module directory.
- Incremented the catering module to `0.1.7`.

## 0.1.6-dev (unreleased)

### Changed — ownership-independent installer architecture
- Removed the public `install.php` entry and `install/` directory from new
  packages. The one-page installer now runs through the normal root front
  controller while the implementation and view live under the protected core.
- Successful installation is switched atomically by the validated generated
  configuration plus permanent installation marker. There are no public
  installer files or folders that must be deleted, so a hosting panel that
  denies PHP changes to document-root entries can no longer strand the site
  behind a cleanup error.
- Installed sites still retry exact cleanup of legacy pre-0.1.6 artifacts.
  Legacy `/install` and `/install.php` requests are explicitly denied, and
  ownership-blocked residue no longer takes the public site or admin offline.
- Incremented the catering module to `0.1.6`.

## 0.1.5-dev

### Fixed — shared-host same-parent installer retirement
- Ported the proven same-parent retirement fallback from earlier CajunWebPro
  installers into shared Website Core cleanup. When a hosting panel permits
  PHP to rename entries in the site root but rejects a move into a different
  storage parent, `install/` and `install.php` are atomically renamed out of
  their canonical public paths and then physically erased where permissions
  allow.
- Retired names are randomized and dot-prefixed, the retired entry point loses
  its PHP extension, and Apache/LiteSpeed deny rules are safely added while
  preserving existing root rules. Protected ownership residue is retried on
  every later site load.
- Cleanup now applies safe writable modes before recursive erasure and still
  never uses FTP, shell ownership changes, or mode 0777.
- Incremented the catering module to `0.1.5`.

## 0.1.4-dev

### Fixed — installer self-removal and recovery page
- Replaced in-place recursive cleanup with an exact, allow-listed installer
  cleanup routine. It deletes `install.php` and `install/` directly, then
  atomically retires only a blocked artifact into the protected private
  runtime before erasing it there. This works on split-ownership hosts when
  the web-root parent permits rename, without FTP credentials or 0777 modes.
- Every public/admin site load now repeats this cleanup when both a valid
  generated configuration and the permanent installation marker confirm a
  completed installation. Installer files re-uploaded long after deployment
  are removed automatically; a marker without a valid config never authorizes
  deletion. Protected retired remnants are also retried on later loads until
  the filesystem permits physical erasure.
- Moved the cleanup screen outside the removable `install/` directory. A
  partially removed installer can no longer fall through to an unstyled PHP
  error when `install.php` is opened directly.
- Added a fully inline-styled, responsive cleanup screen with correctly
  resolved root/subfolder logo links, **Recheck cleanup**, **Visit website**,
  and **Open admin** actions. Recheck opens the public site automatically as
  soon as both public installer artifacts are gone.
- Added separate **Visit website** and **Open admin** actions to the successful
  installation screen.
- Reinstallation can safely target an existing Website Core database. Pending
  migrations run, the selected owner is created or refreshed, existing home
  content and cron tokens are preserved, and bundled license/package records
  are updated idempotently. Database tables alone do not mark a site installed.
- Incremented the catering module to `0.1.4`.

## 0.1.3-dev (unreleased)

### Changed — credential-free ownership repair
- Removed the FTP fallback and every FTP credential field from setup.
- When packaged `config/` or `storage/` directories have foreign ownership,
  setup now discovers or creates a deterministic mode-0700 private runtime
  outside the public document root. The bootstrap and fail-closed cleanup gate
  automatically reuse that location after installation.
- Added strict persistence and safety checks: public paths, temporary paths,
  symlinks, and locations that fail real create/read/rename/delete probes are
  rejected. No 0777 permissions are used.
- The three installer overview steps now update live. Completed steps turn
  green, display a checkmark and “Ready,” while the next incomplete step is
  highlighted. Installation unlocks after all checks, a successful database
  test, and valid owner details.
- Incremented the catering module to `0.1.3`.

## 0.1.2-dev (unreleased)

### Fixed — shared-host ownership fallback
- Replaced the failed assumption that PHP can repair foreign-owned uploaded
  directories with the proven local FTP provisioning fallback used by prior
  CajunWebPro installers.
- Readiness now accepts either a verified direct write or an available local
  FTP fallback, so foreign-owned `config/` and `storage/` no longer remain red
  when the server can repair them automatically.
- Added a polished ownership-repair section and **Test hosting access** action.
  FTP is hard-locked to loopback, credentials exist only for the request, and
  the password is never echoed, logged, or saved.
- The fallback detects the current site using packaged fingerprints,
  provisions storage with safe 0750/0775 permissions, writes and reads back
  the generated configuration, writes install locks, and retries removal of
  `install/` and `install.php` through the hosting account. It never uses 0777.
- Incremented the catering module to `0.1.2`.

## 0.1.1-dev (unreleased)

### Changed — secure branded installer
- Incremented Website Core to `0.1.1-dev` and the catering module to `0.1.1`;
  newly generated test bundles now carry the new version in their filenames
  and installed configuration.
- Reworked setup into a polished responsive CajunWebPro-branded experience
  using the approved primary logo, readable wrapped server paths, clear
  readiness states, and a live MySQL/MariaDB **Test connection** action.
- Kept the approved logo fully transparent across setup, success, and cleanup
  screens, and separated readiness labels from their path/details for clean
  wrapping and copy/paste output. Every installer logo links back to
  `https://cajunwebpro.com` in a new tab.
- SSL/HTTPS is now a required installer check and Base URLs must use
  `https://`. Common shared-host and reverse-proxy SSL indicators are
  recognized.
- Writable directory checks can create or safely repair `config/` and
  `storage/` without using 0777 permissions.
- Successful setup removes the complete `install/` directory and root
  `install.php`. Cleanup failures lock public, admin, and cron entry points
  behind a 503 page with manual DirectAdmin/FTP removal instructions.

### Added — first commercial industry package
- Added `examples/industry-catering`, a self-contained catering website
  package based on the approved ivory/forest/gold editorial concept. It
  supplies distinct full-size pages for the home, services and four service
  details, menus and category details, packages and package details, gallery,
  story, service area, FAQ, contact, and quote-request experience.
- Added module-owned menu, package, and inquiry tables with editable seeded
  demonstration content. The four-step quote planner uses CSRF protection,
  honeypot and database rate limiting, persists leads, and queues an optional
  notification through core system mail.
- Added Catering and Catering Leads administration screens for dashboard
  metrics, menus, packages, inquiry status workflow, and business defaults.
- Added four project-owned, generated event/food photographs optimized as
  WebP assets; the finished package has no third-party image hotlinks.
- Added package coverage for installation, migrations, public page rendering,
  menu detail routing, and end-to-end quote capture.

### Added — keyless testing bundles
- `tools/build-packages.php test-bundle <industry> [outdir]` creates a clearly
  labeled industry bundle without `license_key` for development and customer
  testing. Licensed and test bundles now include a clean production-only
  Composer runtime. TEST-BUNDLE files use a direct web-root layout, making
  them complete uploads rather than source-only archives.
- The browser installer now detects, migrates, registers, and enables bundled
  industry modules whether or not they carry a license key. Keyless test
  bundles make no licensing API request and create no license row; licensed
  production bundles retain the existing validation and enforcement path.

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

### Added — M4 updates, backup, recovery, jobs, mail
- `BackupService`: full backup/restore (schema + data hex-literal, per-table
  bounded SELECTs, meta.json manifest, file-tree tarball + SHA256 map,
  integrity check on restore, staging path validation, `.htaccess` denial
  guards). Admin UI with list/inspect/delete/restore.
- `CoreUpdater`: Ed25519 signature verification (sodium_compat polyfill),
  `ArchiveInspector` reuse, `update.json` manifest (per-file SHA256, version,
  min_core, expires), re-inspect TOCTOU guard, pre-write hash verification,
  apply with file-system isolation (skips config/installed.lock/storage/upload/
  installed modules/themes), per-update backup, migrations, opcache reset,
  admin stage/apply UI with `require_window` gate (`storage/maintenance.flag`).
- Transactional mail queue (`SystemMail`, `system_messages` table,
  PHPMailer SMTP + capture fallback, dedupe key, SMTP host/user/password/
  secure config), cron drain (`Runner::run()` bounded batch, at-least-once
  idempotent handlers, expired-lease sweep).
- Job queue (`JobQueue`, `jobs` table): at-least-once semantics, atomic
  claim (guarded `pending/uncertain` → `running` transition, rowCount check),
  expired-lease sweep, dedicated `Runner` with core handlers + package
  `scheduledJob()` registration.
- Health/audit/diagnostics pages: real versions, queue depth/age, backup state,
  failed packages, storage writable, disk free, config key listing (values
  withheld), audit log tail.
- Recovery-mode flag (`storage/recovery.flag`): boots trusted core only,
  packages skipped — broken extensions can never lock the admin out.

### Added — Self-service password reset
- `/admin/forgot` + `/admin/reset`: rate-limited (5/10min per IP on request,
  10/10min per IP on consume), sha256 token (raw only in email link, hash stored),
  30-min expiry, single-use, sessions revoked on completion, unknown accounts
  silent (no enumeration). Migration 0007 adds `reset_token_hash` and
  `reset_expires_at` columns.

### Fixed — Concurrency races
- Job queue double-claim: atomic rowCount-guarded transitions + expired-lease
  sweep (no wedged jobs).
- Mail flush double-send: atomic `sending` claims + 5-minute lease sweep.
- Update apply race: exclusive `flock` on `storage/update.lock` + temp-write+rename
  per member (no interleaved writes).
- Migrator race: DB-level sentinel lock (`__migrate_lock__`) with 120s stale
  reclaim (parallel installs prevented).
- Backup restore race: exclusive `flock` on `storage/restore.lock`.

### Fixed — Security correctness
- Header injection: `Response::header()` rejects CR/LF/NUL/DEL.
- SQL identifier injection: allowlist regex `[a-zA-Z0-9_]+` on all table/column.
- Upload error surfacing: shared Controller helpers map every `UPLOAD_ERR_*` +
  discarded-body detection across package/media/update uploads.
- `e_url` scheme-obfuscation bypass: probe control-char-stripped copy before
  scheme allowlist.
- Slug shadow guard: reserved list + concrete-route check; unreachable-page
  badge on Content index.
- Cron boot: service boot + module boot so package `scheduledJob()` handlers
  exist; CI regression assertions added.
- Entry-point sweep: all 3 root `.php` files have correct boot/exception handling.
- Unsigned manifests: flipped default `allow_unsigned=false`; installer writes
  the key; real artifact dogfood proven.
- Maintenance enforcement: `dispatch()` 503s while `storage/maintenance.flag`
  exists; `/admin/login`/`/admin/forgot`/`/admin/reset` bypassed; cron honors
  the flag.

### Changed — Performance and integrity
- Hot-path indexes: migration 0005 (jobs, system_messages, page_revisions,
  menu_items, media_usage).
- No-rewrite fallback: verified live on raw artifact (`?p=` + PATH_INFO,
  POST + CSRF).

### Changed — Hardening posture
- MFA replay protection: persisted `mfa_last_counter`, hashed single-use
  recovery codes.
- Password rotation: `PASSWORD_DEFAULT` + `password_needs_rehash` on every
  successful login.
- CSRF: session-bound tokens, global middleware on all admin/module admin
  POST routes.
- Rate limiting: atomic DB buckets (login 8/10min, MFA 6/5min, inquiry 5/10min).
- Redirect targets: reject control characters before persistence.
- Upload validation: `finfo` MIME signature, extension allowlist, image re-
  encoding, polyglot rejection.
- Security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options:
  SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- `.htaccess` matrix: root rewrite rule forbids all private paths; per-dir
  deny files for config/core/storage/upload/admin/controllers/admin/views/
  vendor/etc.; mod_php `display_errors off`.
- Real-host testing checklist added: `docs/REAL_HOSTING_TESTING.md` provides
  step-by-step verification for Apache/LiteSpeed/cPanel hosts (installer,
  no-rewrite fallback, cron, packages, password reset, backup/restore, updates).

### Added — M6 accessibility and performance
- Accessibility controls: aria-label on navigation regions (admin sidebar,
  site nav), for labels on all form inputs, descriptive alt text on media
  thumbnails, focus-visible outlines on all interactive elements,
  prefers-reduced-motion media query support (admin layout, base theme,
  noir theme, terra theme).
- AccessibilityTest extended: asserts admin layout controls, theme reduced-
  motion support across all 3 themes, media thumbnail alt text not empty.
- Tests: 149 tests / 582 assertions.
- Performance posture documented: intentionally simple architecture for
  shared hosting (in-request settings cache, HTMLPurifier serializer cache,
  opcache reset after updates, no complex caching layers, database indexes,
  no N+1 queries).

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

### Added — DRM licensing system
- `LicenseValidation` service for phone-home validation with domain
  binding and periodic re-validation via `LicenseValidationJob` cron.
- `license` table for storing license key, domain, validity, deactivation.
- PackageManager license checks before module install/enable; `disable()`
  is exempt so an invalid license can never trap recovery.
- Installer validates license key if a bundled module is detected.
- Admin settings shows license status (key, domain, validity, last
  validated) plus a "Validate now" action that revalidates on demand —
  success clears `license_deactivated` immediately.
- `tools/build-packages.php bundle <industry> <license_key>` creates
  bundled ZIPs with embedded license_key in module manifest.
- `license_key` field added to manifest schema v1.
- `docs/LICENSING_API.md` documents the Cajunwebpro.com API contract.
- `LicenseValidationTest` for license storage/status/binding.
- `LicenseApiContractTest` (9 tests) — real `php -S` mock of the
  licensing endpoint verifies the wire contract, explicit-denial vs
  inconclusive-failure semantics, verdict preservation on transient
  failures, and auto-reactivation.
- `BundledModuleInstallTest` — real example module installed the way
  the installer does it (license row, packages row, module migrations,
  enabled, booted).
- Installer registers, migrates and enables bundled modules (packages
  row + `bundled-install` journal entry) and reports bundle detection
  in capability checks.
- License-gated public 503: explicit server denial takes public pages
  offline while `/admin` stays reachable; transient API/network failures
  never deactivate (inconclusive attempts preserve the stored verdict
  and never create a license row); successful validation clears
  `license_deactivated`.
- Revalidation cadence: 24h while valid, hourly while deactivated.
- Validation reports the install's actual served domain, not the stale
  stored one — a site moved to an unregistered host self-denies at its
  next check; `HTTP_HOST` is deliberately untrusted (preview/IP access
  must never cause a false denial); `license_domain` refreshes to the
  last domain actually validated.
- `app.url` is an admin-editable settings override (`/admin/settings`)
  used by license validation + sitemap/robots — blank keeps the
  installed `config.php` value, a value wins explicitly, so an owner can
  complete a domain move and revalidate without editing config.
- Validation endpoint is the rewrite-free `{license.api_url}/validate.php`;
  `WC_LICENSE_API_URL` env overrides the base for install-time
  validation and generated config.
- `license.validate` job deduplication — one pending/running job max.
- Native CI leg proves the cycle end-to-end over HTTP on real
  MySQL/MariaDB: staged bundle detection → licensed install → license
  row + enabled package + module tables → cron-driven revalidation →
  remote denial taking public pages to 503 with `/admin` reachable →
  reactivation on domain re-registration — all against the
  `tests/fixtures/license-api` mock of the Cajunwebpro contract.

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
- CI: installer acceptance against real MySQL and MariaDB through the SSL
  proxy contract (claim session → real POST → lock/config/full-cleanup
  assertions).

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

### Fixed — indexes, upload errors, inquiry rate-limit
- Migration 0005 indexes real hot paths: jobs claim filter, mail flush,
  page revisions, menu_items join (every public render), media_usage.
- Upload failures surface accurate messages: post_max_size body discard,
  INI/FORM size limits (with the configured ceiling), partial uploads,
  and host-side errors across package/media/update paths.
- Migrator exceptions name the failed migration id; stale "failure row"
  docblock corrected.
- The shared inquiry add-on rate-limits submissions (5/IP/10min) via
  the core RateLimiter — CSRF/honeypot paths never burn the bucket.

### Fixed — routing + URL escaping
- Page save refuses unreachable slugs: reserved path segments and any
  slug a module route already handles (concrete routes always outscore
  the page catch-all). Content index flags already-dead pages
  'unreachable'.
- e_url() rejected "javascript:" but not browser-de-obfuscated forms
  ("java\tscript:", " javascript:") — control-char probe added.
