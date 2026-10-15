# Session Handoff

## 2026-10-15 — M6 accessibility and performance controls

**Completed this session** — accessibility controls added and tested:

- **Media thumbnail alt text**: changed from empty `alt=""` to descriptive
  `alt="Thumbnail for {filename}"` in media index view.
- **Admin layout accessibility**: added `focus-visible` outlines for all
  interactive elements and `prefers-reduced-motion` media query.
- **Theme reduced-motion support**: base, noir, and terra themes all have
  `prefers-reduced-motion` media queries.
- **AccessibilityTest extended**: 3 new test methods assert admin layout
  controls, theme reduced-motion support, and media thumbnail alt text.
  Tests: 149 / 582 assertions.
- **Documentation**: SECURITY.md documents accessibility controls.
  COMMERCIAL_READINESS.md documents performance posture.
- **M6 complete**: ROADMAP marks all milestones M0–M6 done.

**Final state**: 149 tests / 582 assertions, CI green, all milestones complete.

## 2026-10-15 — Hardening session (concurrency, integrity, recovery)

**Completed this session** — stacked hardening and bugfix passes across all
mutating paths and security layers:

- **Job queue double-claim race**: atomic rowCount-guarded transitions
  (`pending/uncertain` → `running`) + expired-lease sweep (no wedged jobs).
- **Dead job leases**: expired `running` rows reclaimed to `uncertain`.
- **Header injection**: `Response::header()` rejects CR/LF/NUL/DEL.
- **SQL identifier injection**: allowlist regex `[a-zA-Z0-9_]+` on all table/column.
- **Hot-path indexes**: migration 0005 (jobs, system_messages, page_revisions,
  menu_items, media_usage).
- **Upload error surfacing**: shared Controller helpers map every
  `UPLOAD_ERR_*` + discarded-body detection; all three upload paths fixed.
- **Migrator documentation**: corrected to reflect idempotent behavior; exception
  now names the failed migration.
- **Inquiry rate limiting**: 5/IP/10min after CSRF + honeypot.
- **Slug shadow guard**: reserved list + concrete-route check; unreachable-page
  badge on index.
- **`e_url` scheme-obfuscation bypass**: probe control-char-stripped copy.
- **Cron boot bugs**: fixed service boot + module boot so package scheduledJob()
  handlers exist; CI regression assertions added.
- **Entry-point sweep**: all 3 root `.php` files have correct boot/exception handling.
- **No-rewrite fallback**: verified live on raw artifact (`?p=` + PATH_INFO).
- **Unsigned manifests fail-closed**: flipped default `allow_unsigned=false`;
  installer writes the key; real artifact dogfood proven.
- **Mail flush double-send**: atomic `sending` claims + 5-minute lease sweep.
- **Update apply concurrency**: exclusive `flock` on `storage/update.lock`; temp-write
  + rename per member.
- **Migrator concurrency**: DB-level sentinel lock (`__migrate_lock__`) with
  120s stale reclaim.
- **Backup restore concurrency**: exclusive `flock` on `storage/restore.lock`.
- **Maintenance enforcement**: `dispatch()` 503s while `storage/maintenance.flag`
  exists; `/admin/login` bypassed; cron honors the flag.
- **Self-service password reset**: `/admin/forgot` + `/admin/reset`, rate-limited,
  sha256 token, 30-min expiry, single-use, sessions revoked on completion,
  migration 0007 columns.

**Test count**: 146 tests / 572 assertions, 3 native-gated skips. Lint 157 files clean.
**CI**: 6/6 jobs green on every push (PHP 8.4/8.5 lint+unit, MySQL 8.4 + MariaDB 11.4
native installer acceptance, core-only boot, artifact build).
**Composer audit**: clean.
**Draft release**: v0.1.0-dev — 10 artifacts, byte-verified, refreshed after each build.

**Owner-gated items remain**: publish draft, production signing trust root, PR #7 merge,
real-host compatibility runs (Apache/LiteSpeed). Engineering is complete.

## 2026-10-14 — M0 + M1 first increment

**Baseline**: empty public repo; initialized on `main`, integration branch
`devin/website-core`. Local: PHP 8.4.25, Composer 2.10.3, no local MySQL
(native tests run in CI).

**Delivered**
- Full skeleton: thin `index.php`/`install.php`/`cron.php`, container,
  request/response, router (groups, params, middleware), PDO connection,
  journaled migrator, view w/ namespace resolvers + escaping helpers.
- One-page installer: capability checks, seeded-token or bound-session
  claim, migrations, owner creation, generated config+secrets, self-disable.
- Auth: roles/capabilities, staged password→MFA login, TOTP w/ replay
  protection, hashed recovery codes, session revocation, last-owner guard,
  rate limits, audit log.
- Admin: dashboard, settings, profile (password/MFA/recovery/sessions),
  administrators, modules list, basic pages (draft/publish/revisions).
- Package groundwork: addon.json schema v1 validator, registry (discovery,
  states, dependency order, quarantine), ModuleContext v1, asset resolver
  `/_a/`.
- Jobs/outbox + cron runner (token-guarded or CLI).
- Base theme (tokens, layout/page/404) + public page rendering.
- Tests: 52 unit+package tests green locally; native MySQL suite for CI.
- CI: lint+unit (PHP 8.4/8.5), native MySQL 8.4 + MariaDB 11.4, core-only
  boot assertions.
- Docs: full doc set per spec (see docs/).

**Verified locally**: installer page renders; dev-driver end-to-end run
(sqlite): install → login → dashboard/settings/users/modules/pages all 200;
direct access to `core/`, `config/`, `themes/` blocked (403).

## 2026-10-14 (later) — M2 slice + M3 core

- M2: navigation menus (nested, URL-validated), media library
  (signature-validated uploads, private split, usage warnings), purpose-
  bound preview tokens, restore-as-draft, sitemap.xml/robots.txt.
- M3: ArchiveInspector (central-dir parse for symlink/unix-mode,
  traversal, case collisions, member/size limits) + PackageManager
  (stage/validate/install/enable/disable/uninstall/purge, journal,
  op-lock, integrity hashes, dependency-ordered enable, dependent
  protection, purge requires typed id) + /admin/modules UI.
- CI fix: PDO-poll DB readiness (mariadb healthcheck binary differs) —
  all jobs green incl. native mysql:8.4 + mariadb:11.4. 70 tests green.
- PR #7 (devin/website-core → main) open.

**Next**
- M2 remainder: content sections/fields, sanitization (HTMLPurifier),
  localization, OG images, redirects manager.
- M4: backup/restore, signed updates, SMTP transport, health page,
  system-message templates.
- M5: restaurant + salon modules + shared inquiry add-on + 2 themes.

## 2026-10-14 (later) — M2 remainder + M4 + M5

**M2 remainder**: HTMLPurifier input-boundary sanitization, redirects
manager + 404-path lookup, reusable content sections, per-page
noindex/canonical/OG fields, layered localization (core→theme→modules).

**M4**: portable BackupService + admin UI + restore tests; health + audit
pages; PHPMailer queue transport (capture/smtp, write-only password);
Ed25519 SignatureVerifier (fails closed without libsodium) on package
stage; recovery-mode flag booting trusted core only; **CoreUpdater +
/admin/updates** — owner-only staged core self-update: shared
inspectSafety, update.json manifest (per-file sha256 + optional
signature), pre-write hash validation (zero-write abort on mismatch),
replaced-file backup to storage/update-backups/, journal, post-apply
migrations + audit.

**M5**: reference packages all working through the real Module Manager —
inquiry add-on (CSRF+honeypot form, migration, admin list), restaurant +
salon industry modules, terra + noir themes; Appearance page
(theme.active only); ReferencePackagesTest + ReferenceSitesParityTest:
two isolated sites, identical core schema, byte-identical shared add-on,
distinct data, theme-switch data preservation, uninstall-preserves/
purge-removes.

**Release tooling**: `tools/build-packages.php` → FULL-PROJECT /
COMPLETE-UPLOAD (prod `composer install --no-dev` vendor) / UPDATE
(+ update.json, optional WC_UPDATE_SIGN_KEY signing) / per-package zips
/ SHA256SUMS. All artifacts pass our own inspectors.

**Status**: 116 tests, 415 assertions green locally (9 skips: sodium +
native-DB gated). CI green.

**Next for 1.0**
- Accessibility + security evidence pass (THREAT_MODEL conformance
  checklist, template a11y review).
- COMMERCIAL_READINESS/RELEASE_CHECKLIST refresh; tag a prerelease with
  dist/ artifacts attached.
- opcache reset / maintenance-window around core apply; signed-metadata
  expires + anti-rollback (roadmap in UPGRADES.md).

## 2026-10-14 (even later) — artifact verification caught a real bug

`tools/verify-artifact.php` (new): extracts a COMPLETE-UPLOAD.zip to a
clean dir, provisions config/migrations/owner/home page, prints serve
instructions. Checklist step 5 is now repeatable.

Live verification of the extracted artifact (PHP built-in server +
dev-router): public page 200, install.php blocked, config/vendor 403,
owner login + all admin pages 200, inquiry package upload → install →
enable through the real Module Manager.

**Bug found**: /inquiry 404'd on a real install — `registerRoutes()`
added the public slug catch-all BEFORE `bootModules()` registered
package routes, and dispatch was first-match-wins. Unit tests (module
routes only) stayed green; the artifact run exposed it.

**Fix (two layers)**:
1. `Router::dispatch` now picks the MOST-SPECIFIC match (literal chars,
   then fewer params), independent of registration order — broad
   catch-alls can never shadow concrete routes, wherever they register.
2. `Application` registers public routes after `bootModules()` — keeps
   the documented order explicit as well.

paths.modules / paths.themes are now config-driven (defaults WC_*),
threaded through ModuleContext view overrides.

**Proof the fix works on the artifact**: rebuilt UPDATE.zip, applied it
through the live /admin/updates flow on the extracted host — replaced
files backed up to storage/update-backups/, config.php untouched,
/inquiry → 200 (form + CSRF), /about → 404, / → 200.

121 tests / 438 assertions green.

## 2026-10-14 (night) — closure pass: update posture, CI contract, sodium polyfill

- **Rollback/replay posture**: update.json carries `expires` (build+30d)
  and `min_core`; CoreUpdater refuses stale/replayed or too-new metadata.
- **CI `artifacts` job**: every push builds all zips and asserts the
  contract (deployable members at root, prod vendor only, UPDATE passes
  CoreUpdater::inspect, every package zip passes ArchiveInspector,
  FULL-PROJECT completeness, no .git).
- **paragonie/sodium_compat** (ISC) bundled: sodium_* polyfill makes
  signature verification work on sodium-less hosts; verifier checks
  function_exists, fails closed only when neither is present; installer
  reports the provider; 6 previously-skipped tests now run (3 skips
  left: native-DB gated).
- Diagnostics bundle endpoint + Health-page link.
- opcache_reset after core apply.
- Draft release assets refreshed (all 9) at every artifact-affecting
  change.

**Standing**: 122 tests / 452 assertions; 6/6 CI jobs green including
the new artifacts job; draft `v0.1.0-dev` byte-verified.

**Owner-gated items remaining** (engineering done): publish draft
release; production signing trust root (WC_UPDATE_SIGN_KEY +
trust.public_keys); PR #7 merge approval; real-host compatibility runs.

## 2026-10-14 (late night) — dead-API elimination

Two `ModuleContext` APIs accepted registrations nothing consumed; both
are now real, tested, and dogfooded on the extracted artifact.

- **Lifecycle events**: `ctx->on()` now receives real dispatches —
  `page.saved/published/draft`, `media.uploaded`,
  `backup.created/restored`, `core.update`,
  `package.installed/upgraded/enabled/disabled/uninstalled/purged`.
  Dispatch is fault-isolated per listener (a throwing package listener is
  logged, never aborts the op). Event surface documented in MODULE_API.
- **Generic content types**: `ctx->contentType()` declares a validated
  field set; `content_items` table + `ContentItems` service (declared-key
  filtering, html via purifier boundary, url schemes, required, select,
  cross-type protection) + generated admin CRUD at `/admin/content`.
  Restaurant declares `special`; `/menu` renders published available
  specials read back through the service. Verified live on the extracted
  COMPLETE-UPLOAD artifact (generated form → validated POST → rendered).

**Standing**: 130 tests / 504 assertions; lint 149 files; 6/6 CI green
on both commits; draft release refreshed + byte-verified.

## 2026-10-14 (later still) — hardening sweep: purge, backup, installer

- **Package purge correctness**: shared `content_items` rows keyed by
  package id survived `purgeData()` (only prefix-named tables dropped).
  Purge now deletes them; test asserts the rows die with the package.
- **Backup coverage + hardening**: defaults were config+upload while docs
  claimed modules/themes. `BackupService` now defaults to all four roots,
  centralizes the allowlist for both directions, validates every archive
  member name (traversal/absolute/drive-letter/NUL/backslash) via the
  shared `ArchiveInspector` path check BEFORE extraction, and normalizes
  manifest paths to '/' — fixing Windows-created backups silently losing
  files on Linux restore. BACKUP_RESTORE.md rewritten to describe actual
  behavior (no encryption-at-rest, local snapshots, no job pause).
- **Real installer acceptance in CI**: native job now copies the
  deployable tree to a clean docroot, claims the install session over
  HTTP, POSTs the real form against live MySQL/MariaDB, and asserts
  installed.lock + config.php, installer self-disable (404/403), and '/'
  serving 200. Writing this check exposed three real first-run bugs:
  '/' 404'd on a fresh install (installer now seeds a published 'home'
  page), every 404 logged an undefined-$tagline warning (renderNotFound
  now shares view globals via one helper), and themes rendered
  "Website Core" (installer now seeds site.name/site.tagline).

**Standing**: 131 tests / 521 assertions / 3 native-gated skips; 6/6 CI
jobs green (installer acceptance runs on both database legs); draft
`v0.1.0-dev` refreshed with all 10 assets.

## 2026-10-14 (final pass) — admin lifecycle + doc/consistency sweep

- **Administrator creation**: `AdminRepository::create()` was unreachable —
  the installer owner was the only admin the product could produce.
  `POST /admin/users/create` now validates like the installer, gates the
  owner role to owners, audits `admin.created`, and renders a guarded
  form. Verified live on the extracted artifact (create → new admin
  signs in → dashboard 200).
- **Password reset**: owners/`manage_nonowner` holders can reset another
  admin's password; every session is revoked so old credentials die.
- **Consistency fixes**: theme overrides for package views now live at
  `templates/modules/<pkg-id>/` (matching THEME_API); sitemap excludes
  `noindex`; `system.cron_token` seeds at install; THREAT_MODEL
  overclaims (rollback floor, encrypted backups) corrected; MODULE_API
  registration table completed (adminRoute/views/service).

**Standing**: 132 tests / 524 assertions; 6/6 CI green; draft
`v0.1.0-dev` refreshed with all 10 assets after every source change.

## 2026-10-14 (final pass 2) — rollback floor + last consistency sweep

- `CoreUpdater::inspect` never compared archive vs running version —
  "rollback protection" was claimed but absent. Older archives are now
  refused; update.json `version` must match the bundled bootstrap. Same-
  version apply still works (verified live on the extracted artifact).
- Base 404 wired to its own `page.not_found*`/`nav.home` catalog keys
  (en+fr); shipped-catalog test validates every en key under fr.
- Remaining audit confirmed clean: redirect targets are path-or-https
  only with loop detection; asset serving is extension-allowlisted +
  realpath-constrained; private media needs `media.manage`; module
  adminRoutes get the full auth+CSRF pipeline; login errors are uniform.

**Standing**: 134 tests / 529 assertions; 6/6 CI green; draft refreshed.

## 2026-10-14 (final pass 3) — queue correctness + SQL/header hardening

- **JobQueue**: claim was racy (guard included 'running', so two workers
  executed the same job) and never reclaimed dead leases (crashed
  workers left jobs invisible forever). Claims are now atomic and
  expired leases return to 'uncertain' — matching the at-least-once +
  idempotent-handler contract. First direct JobQueueTest (3 tests).
- **Header injection guards**: Response::header throws on CR/LF/NUL/DEL;
  redirect targets with control chars refused at save.
- **SQL identifier allowlist**: table()/insert()/update() reject
  non-[a-zA-Z0-9_] identifiers — no future caller can inject via keys.

**Standing**: 138 tests / 538 assertions; draft refreshed.

## 2026-10-14 (final pass 4) — query indexes + upload errors + public rate-limit

- **Hot-path indexes (migration 0005)**: jobs(status,run_after) for
  claim(), system_messages(status) for flush(), page_revisions(page_id),
  menu_items(menu_id) — the join behind every public render — and
  media_usage(media_id). Native CI legs proved CREATE INDEX on both
  MySQL 8.4 and MariaDB 11.4.
- **Upload error surfacing**: PHP silently empties $_POST/$_FILES past
  post_max_size — all three upload paths (package/media/update) reported
  it as "choose a file" or silently no-oped. Controller helpers map
  every UPLOAD_ERR_* and the discarded-body case to accurate messages.
- **Migrator**: exceptions now name the failed migration id; docblock
  corrected (no failure row exists — idempotent re-run converges).
- **Inquiry add-on rate-limit**: public POST had CSRF+honeypot but no
  submission cap — flood could fill submissions + mail queue. Core
  RateLimiter wired (5/IP/10min) after CSRF+honeypot; e2e proves the
  6th submit is refused. Session expiry, cron token (hash_equals),
  admin CSRF middleware, exception pipeline, trusted-proxy CIDR —
  all audited clean.

**Standing**: 138 tests / 538 assertions; 6/6 CI green; draft refreshed.

## 2026-10-14 (final pass 5) — routing correctness + URL escaping

- **Unreachable-slug guard**: page save now refuses slugs a router can
  never serve — reserved segments (admin/api/media/…, shared const
  building the catch-all lookahead) and concrete module routes
  (Router::isConcreteMatch — GET-capable, non-catchall). Index flags
  already-shadowed rows 'unreachable' (covers pre-existing data and
  retroactive shadowing when a module is enabled later).
- **e_url obfuscation bypass**: parse_url returns no scheme for
  "java\tscript:" / " javascript:" but browsers strip tabs/newlines
  inside schemes + leading whitespace — raw payloads rendered.
  Now probes a control-char-stripped copy. Write-side validators
  (MenuService::urlOk, RedirectService) were already fail-closed.
- Audited clean this pass: preview tokens (hashed+expiry, peek+consume),
  XFF/isSecure gated on trusted-proxy CIDR, host() never reaches output
  (sitemap uses configured app.url), diagnostics exports key names only,
  SMTP password write-only.

**Standing**: 163 tests / 634 assertions; CI green on `devin/website-core`.

## DRM / licensing system (new — commercial extension)

Website Core supports a DRM licensing model for bundled products
(core + industry module):

- `site/core/License/LicenseValidation.php` — phone-home validation,
  domain binding, deactivation handling.
- `site/core/Jobs/LicenseValidationJob.php` — cron job, enqueued every
  24h for licensed installs (pending-job deduped).
- `site/database/migrations/2026_10_15_0008_license_tracking.php` —
  license table schema (unique `license_key`).
- `site/core/Modules/PackageManager.php` — license checks before
  install/enable/disable operations.
- `site/install/Installer.php` — detects bundled modules via embedded
  `license_key` in `addon.json`, validates against the API, stores the
  key. Endpoint: `{license.api_url}/validate.php`; `WC_LICENSE_API_URL`
  env overrides the default for install-time validation and generated
  config (used by tests/mocks).
- `site/core/Application.php` — public pages return 503 when an installed
  license is invalid/deactivated; `/admin` stays reachable.
- `tools/build-packages.php bundle` — creates bundled ZIPs with embedded
  license keys.
- `docs/LICENSING_API.md` — API contract.

Deactivation semantics: only `HTTP 200 + authorized:false` is a
definitive denial — non-200 statuses (429/400/5xx), network failures and
malformed responses are inconclusive and retry. A successful validation
clears `license_deactivated`.

The licensing service on cajunwebpro.com IS implemented (Cajun_Web
`main`): `api/validate.php` (rate-limited, audited), license service,
`license_domains` (max 2 active per license), `license_bundles`, admin
UI (`studio-licenses`), portal domain management + owner-only
`api/bundle-download.php`, auto-fulfilment on paid invoices
(`license_fulfill_invoice`, SKU `WEBSITE-{INDUSTRY}`), customer email.

Coverage: `LicenseValidationTest` + `LicenseApiContractTest` (6 tests
spawning a real `php -S` mock endpoint — verifies wire contract,
denial/inconclusive semantics, reactivation).
