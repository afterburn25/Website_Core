# Session Handoff

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
