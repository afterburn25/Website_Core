# Session Handoff

## 2.0.16 Navigation workspace completion

- Migration `2026_10_25_0024` adds `menu_items.is_enabled` with existing links
  remaining visible by default on upgraded sites.
- The administrator tree supports before/inside/after drag placement, retains
  arrow-button alternatives, blocks excessive depth, and requires structure
  changes to be saved or discarded before another menu mutation.
- Header/footer tabs expose total and hidden counts. New links may be staged,
  placed under a parent immediately, and opened in the same tab, a new tab, or
  a named new window.
- Core public navigation retains the selected target, marks the active route,
  filters hidden subtrees, and does not replace an intentionally hidden custom
  menu with the published-page fallback.
- Redirects can be edited in place with bounded fields and recoverable
  duplicate/missing-row validation.
- Release as Core 2.0.16 after the full CI matrix and package contracts pass;
  packaged browser verification remains the external acceptance gate.

## 2.0.15 landscaping lifecycle carried into latest Core

- Package Manager now retains the previous package directory until replacement
  migrations finish. A thrown migration restores the prior files, manifest,
  version, and enabled/disabled state and records a successful rollback.
- Landscaping acceptance now covers customer-created data through same-version
  replacement and data-preserving uninstall, queued estimate mail, protected
  photo routes, and all four theme selections.
- The actual landscaping ZIP lifecycle—install, enable, replace, deliberate
  failed-migration rollback, disable, and uninstall—passed on MySQL 8.4 and
  MariaDB 11.4 at Core commit
  `18bf6b9eb389702c62cc911a8b4cdfdc11fc18d5` in CI run
  `40133429653`.
- Remaining landscaping acceptance is external: responsive visual review in a
  real browser and a hosted file-manager upgrade/rollback exercise.
- Core 2.0.15 rebuilds the directly usable landscaping TEST-BUNDLE and
  `Website-Core-Landscaping-2.0.15-REPAIR-UPLOAD` from the same verified 1.0.0
  module snapshot.

## Permanent packaging rule

- All user-facing build downloads are a single directly usable ZIP. Never put
  a ZIP inside another ZIP. GitHub Actions must upload an extracted staging
  directory so its generated download archive contains the deployable files
  at the archive root.
- Complete uploads, testing/licensed bundles, and repair uploads must include
  the browser installer (`install.php`, `install/index.php`, and the Core
  installer implementation/views). Installed sites rely on the existing
  configuration and installation lock to keep setup disabled.
- CI stages and inspects the downloadable payload, rejects nested ZIP files,
  requires installer files, and preserves hidden `.htaccess` guard files.

## 2.0.15 Media workspace safety and polish

- Page blocks and page social images register `core.page` Media usage records
  on create, edit, duplicate, and revision restore; purge removes those records.
- Migration `2026_10_24_0023` backfills public-image references for existing
  pages and is safe to rerun after an interrupted shared-host upgrade.
- Media details name and link to referencing pages before deletion, expose a
  stable URL copy action, and visualize focal points over the crop preview.
- Private image variants now require `media.manage` and return private,
  no-store responses instead of failing for authorized administrators.
- Upload selection and bulk-selection state are visible; bulk submission stays
  disabled until at least one item is selected.
- Release as Core 2.0.15 only after the full CI matrix and package contracts
  pass; packaged browser verification remains an explicit external gate.

## 2.0.14 Landscaping 1.0 Core synchronization

- Synchronized `cajunwebpro/landscaping` 1.0.0 runtime source from
  `Core_Mods/module/landscaping` commit
  `be009de567286b16ea60e4188b6e7eb34f482b83`.
- Verified module release digest:
  `05639d1c98002b33a0aeabfbb728802095c14cc3028280214c7e1572670d8627`.
- Final Core_Mods CI passed in run `40125724520`.
- Added Bold Outdoors as the default and Estate Green, Neighborhood Lawn, and
  Earth & Stone as separately packaged 1.0.0 themes.
- Added package install/render/estimate coverage, native MySQL/MariaDB
  migration coverage, release-gate assertions, and dedicated TEST-BUNDLE and
  REPAIR-UPLOAD CI artifacts.
- Permanent delivery rule: a stable module release is shipped only after its
  verified runtime source is synchronized into the current Core line and both
  repositories' CI pass.

## 2.0.14 subfolder assets and SEO recovery

- Live hosted acceptance completed after deploying commit `aa5a3ddde5f8`:
  maintenance remains a public 503, Core admin CSS and JavaScript return 200
  from the `/catering/index.php?p=/_a/...` routes, protected admin routes
  redirect logged-out requests to login, and installed `install.php` and
  `install/` URLs remain unavailable with 404 responses.
- Authenticated `/admin/health`, `/admin/audit`, and `/admin/navigation` render
  successfully with the versioned Core assets. Health reports zero integrity
  issues, zero package warnings, and zero mail failures.
- Module Manager finalized catering from recorded `0.1.23` to `0.1.24` and ran
  `catering_0003_home_search_metadata`. SEO now reports no common issues for
  the migrated home page; infrastructure asset paths are absent from 404
  insights.
- The shared administrator shell now anchors Core CSS and JavaScript to the
  installation subfolder, so `/catering` no longer requests `/_a/...` from the
  domain root.
- Live maintenance-mode probing found that the corrected `/_a` URL was still
  intercepted by the public 503 gate. Core now exempts its allowlisted asset
  route from maintenance and license blocking; the regression test dispatches
  the stylesheet and requires a real CSS response.
- Build and deploy the newest CI-produced 2.0.14 REPAIR-UPLOAD; the artifact
  recorded below predates this final asset-gate correction.
- SEO 404 insights now exclude Core asset, admin, API, installer, and cron
  infrastructure paths, including historical rows already stored.
- Fresh installs seed descriptive home-page titles and search summaries.
  Catering `0.1.24` adds a migration that repairs only untouched starter
  metadata and preserves customized content.
- CI's catering REPAIR-UPLOAD contract now compares the packaged module version
  with the authoritative source snapshot instead of a stale hard-coded version.
- Commit `1f7f1b36c81b3edda9d0ed3252cb50d73f60713e` passed all six CI jobs in
  run `40122945702`. Its
  `Website-Core-Catering-2.0.14-REPAIR-UPLOAD` artifact has digest
  `sha256:e74eaa312cd66bec98a2399011e159d6e57b0b4274e25c361bb0b40ee7f38c09`.
- Next external step: deploy that CI artifact and verify authenticated Health,
  Audit, Navigation, and SEO Insights on the live subfolder installation.

## 2.0.14 Health and fatal-page recovery

- Hosted `/admin/health` and `/admin/audit` returned bare HTTP 500 responses,
  and the host did not honor
  the dynamically provisioned `.htaccess` custom error mapping.
- Health now uses a fresh `HealthController`; Audit uses a fresh
  `AuditController`. Their data sources are isolated so
  the page renders partial results and warnings instead of throwing.
- `index.php` registers a shutdown fallback before bootstrap. Fatal PHP errors
  directly render `errors/500.html` with subfolder-correct assets and links,
  independent of Apache/LiteSpeed `ErrorDocument` support.
- Build and distribute the 2.0.14 catering REPAIR-UPLOAD, then verify the
  authenticated Health and Audit routes on the live host.

## 2.0.14 commercial admin polish

- Second cross-surface commercial presentation pass; no new product domain.
- Shared shell now has persistent desktop sidebar collapse, compact density,
  accessible branded confirmations, auto unsaved-work guards, character
  counters, password visibility controls, and current-table quick filters.
- A reusable switch pattern now controls maintenance, MFA policy, form
  availability, consent, webhooks, administrators, and module enabled state.
- Appearance previews local logo/favicon selections. Settings disables
  irrelevant SMTP/privacy inputs and preserves stored SMTP values whenever a
  different mail transport is selected.
- Release as Core 2.0.14; require the full CI matrix and package/browser smoke
  tests before marking the 2.0.14 acceptance section verified.

## 2.0.13 isolated Updates and server 500 page

- Hosted `/admin/updates` still produced a bare HTTP 500 after 2.0.12 while
  the public maintenance page and administrator login continued to render.
- Core Updates now has a fresh dedicated controller. GET does not open a ZIP;
  explicit stage writes bounded review metadata and apply re-verifies fully.
- A standalone branded `errors/500.html` plus a managed, subfolder-aware
  `ErrorDocument 500` mapping covers failures before PHP's handler can run.
- Publish, pass all six CI jobs, then distribute only the CI-produced 2.0.13
  catering REPAIR-UPLOAD archive.

## 2.0.12 shared-host class-cache repair

- Live hosted testing still returned HTTP 500 from `/admin/updates` after the
  2.0.11 repair upload. The public site was in maintenance mode and the
  administrator login route remained reachable; the cloud browser could not
  inspect authenticated pages without the owner's credentials.
- 2.0.12 removes correctness dependence on `opcache_reset()` and
  `opcache_invalidate()`. The new `runtime-code-loader.php` creates private,
  version-specific PHP paths; bootstrap loads the autoloader/helpers through
  those paths and the autoloader mirrors every Core/admin class likewise.
- Admin-only 500 pages now expose expandable exception details exclusively to
  an authenticated administrator. Logging falls back from the configured
  storage log directory to `WC_CONFIG/logs/application.log`.
- Publish the branch, require the complete six-job CI matrix, and distribute
  only the CI-produced `Website-Core-catering-2.0.12-REPAIR-UPLOAD.zip`.

## 2.0.5 brand and footer ownership

- Core now imports module-supplied logo/favicon/social defaults once and owns
  every later selection. Catering supplies the optimized transparent CATERING
  CO. starter logo and resolves the live Core assets in public/admin layouts.
- Website → Navigation Menu has isolated Header menu and Footer menu tabs using
  the same full tree editor. Every action preserves its selected location and
  Core rejects cross-menu item mutations.
- Website → Appearance has a plain-text business copyright with `{year}` and
  `{site_name}` tokens. Base and compatible module layouts render it escaped;
  the immutable Cajun Web Pro attribution remains a separate Core injection.
- Catering `0.1.21` declares distinct primary/footer defaults, renders both
  Core-owned trees, and contributes its inquiry widget to the single Core
  Dashboard without a duplicate module overview. Core_Mods
  `module/catering` is the authority. Core Appearance also owns optional social
  profiles; blank platforms are omitted and configured profiles render with
  shared accessible SVG icons. Core 2.0.7 expands the directory to fifteen
  platforms and replaces per-profile placement with one global Header,
  Footer, or both control. The 0.1.21 ZIP SHA-256 is
  `4c300c583f658b6ee76c1545c875cfe31404b97894cfaaba27a359b93b2a4d1f`.
- Local PHP is unavailable. Publish the module and Core commits, let their
  GitHub Actions matrices finish, then replace pending CI notes with final run
  IDs and artifact digests.

## 2.0.4 maintenance login repair

- Hosted testing found that a logged-out `/catering/admin` request received
  the maintenance page before auth middleware could redirect to login.
- `Application::inMaintenance()` now exempts the complete `/admin` namespace
  from the public maintenance intercept. Authentication, MFA, CSRF, roles,
  and capabilities still protect the admin routes normally.
- `ApplicationMaintenanceTest` proves `/admin` redirects to the subfolder-safe
  `/catering/index.php?p=/admin/login` route while ordinary public paths remain
  in maintenance mode.
- Hosted verification still needs to cover enable maintenance → logout →
  `/catering/admin` → login → disable maintenance.

## 2.0.3 commercial hardening — Content and Page Editor

- Broad feature development remains frozen. Surface 2 of
  `docs/COMMERCIAL_HARDENING.md` is the active workstream.
- The Content list now has no-rewrite-safe search and filters, summaries,
  pagination, explicit empty states, scheduled state, live/preview links,
  confirmed trash, restore, and publisher-only permanent deletion.
- The Page Editor is a responsive workspace with retained validation input,
  parent and social-image selectors, live SEO preview, character counts,
  accessible visual-block ordering, unsaved-change protection, and a real
  tokenized preview tab.
- Migration `2026_10_23_0022` adds `pages.lock_version` and
  `page_revisions.snapshot_json` for stale-tab protection and complete
  revision restoration. Legacy revisions use the existing column fallback.
- The next surface is Media Library, but it must not begin until Core 2.0.3
  passes the full CI matrix and packaged-browser Content workflow gate.

## 2026-10-23 — Website Core 2.0.2 commercial hardening begins

- Broad feature development is frozen. `docs/COMMERCIAL_HARDENING.md` is now
  the ordered source of truth for completing and polishing existing features
  one screen at a time before commercial release.
- The first surface is the shared admin shell and dashboard. Inline shell CSS
  and JavaScript moved to versioned `site/assets/` files served through
  `/_a/core/*`; the shell now has a polished responsive design system,
  accessible mobile navigation, persistent category/scroll state, clearer
  hierarchy, compact account actions, and announced alerts.
- Dashboard metrics now open the matching Content, Modules, Operations, and
  Health screens. Quick actions lead to existing page, media, navigation, and
  settings workflows.
- Complete the 2.0.2 CI and packaged-browser checks before marking surface 1
  verified. Then move only to surface 2: Content list and page editor.

## 2026-10-24 — Website Core 2.0.1 navigation visibility

- The Core Website category now labels `/admin/navigation` as **Navigation
  Menu**, and the editor explains that its primary tree is the live website
  header navigation.
- Module API v1 now exposes `navigationMenu()` and `navigationTree()`. An
  industry module owns its default links; Core imports them on first boot and
  then owns the editable stored tree without overwriting custom or intentionally
  empty navigation.
- Catering module `0.1.16` uses that API for its six existing links and renders
  Core's current tree instead of a second hard-coded header menu.
- Module development remains authoritative in `Core_Mods` on
  `module/catering`; the Core example is synchronized from that release before
  combined bundle verification.
- Core_Mods now carries versioned shared `MOD_RULES.md` plus a required local
  `MODULE_RULES.md` creation preflight copied into every new module. Repository
  CI rejects modules that omit or mismatch the rules contract.
- Verified Core commit `49c124d0292efeb6b7e6965cd93259cc1234e580`
  in CI run 397 (`40044526443`): all six PHP 8.4/8.5, package, artifact,
  Core-only, MySQL 8.4, and MariaDB 11.4 jobs passed. Catering release commit
  `13190474e6d786854742b06e23e0691c131dda5f` passed Core_Mods run 8 with ZIP
  SHA-256 `b182dbd11edbdff03cc52a2bc9ffd83fe5f7b1c125588e6149cf6f5d69eb11cc`.

## 2026-10-24 — dedicated Core_Mods repository

- Industry-module development moved to `afterburn25/Core_Mods`. Repository
  `main` owns shared standards, templates, registry, and validation; each
  module owns a long-lived `module/<slug>` branch and a separate
  `modules/<module-id>/` folder.
- Catering is authoritative on `module/catering` under
  `modules/industry-catering/`, with source, roadmap, status, changelog,
  testing record, handoff, and the current versioned module ZIP. This Core
  repository retains `examples/industry-catering/` only as the tested snapshot
  used by combined release builders.
- When module behavior changes, increment its own manifest version and release
  it in Core_Mods first. Synchronize the snapshot into Core in a separate,
  traceable commit, run Core's complete CI matrix, and record the source module
  branch, commit, version, and release digest.

## 2026-10-23 — Website Core 1.8 through 2.0

- Version 1.8 completed custom roles and permissions, editorial review notes,
  approval boundaries, and revision comparison. Version 1.9 added scoped API
  keys, signed webhooks, JSON site transfer, and optional HTTPS WebDAV backup
  destinations. Migrations 0019 and 0020 carry those upgrades forward.
- Version 2.0 added the agency dashboard, encrypted remote credentials,
  authenticated client health checks, signed marketplace catalogs, and managed
  core-update policies with backup, maintenance-mode, migration, checksum, and
  audit-history safeguards. Migration 0021 installs the agency, marketplace,
  managed-update, and extended navigation data model.
- The navigation editor is now a visual nested tree. Administrators can drag
  items to reorder or reparent them, use accessible move/indent controls,
  select an internal page by name or enter an external/custom URL, and choose
  same tab, new tab, or a dedicated new window. Public themes preserve those
  target choices with safe opener isolation.
- Website Core is `2.0.0`; the extension/module API remains major version 1.
  Release history, upgrade behavior, API use, agency operations, marketplace
  trust rules, and roadmap status are documented under `docs/`. GitHub CI is
  the authoritative PHP 8.4/8.5 plus MySQL/MariaDB release gate; do not publish
  a branch head unless all six jobs pass.

## 2026-10-23 — Website Core 1.0.1 admin recovery

- Reproduced the deployment boundary: the installed site's standalone admin
  login renders, while the authenticated dashboard can fail when current PHP
  files are placed over a database whose core migration journal stops before
  the new admin tables. Core boot now compares `WC_SCHEMA_VERSION` with that
  journal and applies pending core migrations before any admin service can
  query the newer schema.
- Production error references are now useful: the same reference displayed to
  the operator is included in PHP's error log and, when writable, the private
  `storage/logs/application.log`. The generic error response also uses the
  branded dark presentation instead of an unstyled document.
- Added an upgrade regression test that creates a pre-admin-platform SQLite
  installation, boots current core, and verifies migrations 0009–0011 and the
  new tables/columns are applied automatically.

## 2026-10-23 — stable Website Core 1.0

- Promoted the validated 0.9 release candidate to `1.0.0` without changing
  extension API major version 1. Added `docs/1.0_RELEASE_NOTES.md` and aligned
  bootstrap, example config, test bootstrap, artifact verifier, base theme,
  roadmap, project status, README and changelog.
- Stable artifacts are built from the committed tree with a production-only
  Composer vendor set. `Website-Core-1.0.0-UPDATE.zip` declares cumulative
  compatibility from `0.1.14-dev`; later release operators can raise that
  floor explicitly with `WC_UPDATE_MIN_CORE`.
- The release is intended to be pushed to `devin/website-core`. GitHub CI is
  the authoritative PHP 8.4/8.5 + MySQL/MariaDB acceptance gate before merge
  or publication; do not rewrite the documented milestone commits.

## 2026-10-23 — 0.1.15 through 0.9 release-candidate build

- Delivered focused, individually named commits for integrity, the admin
  platform, publishing and nested editable menus, shared media/branding,
  package integration plus immutable Cajun Web Pro attribution, admin
  security, operations/recovery, and SEO/performance. `docs/ROADMAP.md` maps
  every version to its stable commit subject so the history remains readable
  even when a credentialed GitHub connector reauthors commit object IDs.
- The required footer is code-owned and injected into every public HTML
  response: “Website Design & Development by Cajun Web Pro LLC” and “Want a
  website like this for your business? Let’s build yours.” Both named links
  point to `https://cajunwebpro.com`; no admin setting can edit it.
- Hardened public caching for 0.9: only controller-opted anonymous pages get
  shared caching/ETags. Module forms, previews, and any unmarked HTML are
  private/no-store so session CSRF values cannot enter a shared cache.
- Added `tools/release-gate.php`, Composer-lock-aware CI caches, a verified
  offline production-vendor build input, and cumulative update metadata with
  a `0.1.14-dev` default floor. Stable 1.0 packaging and CI verification are
  the next release steps.

## 2026-10-23 — ownership-independent installer (`0.1.6-dev`)

- Live verification at `demo.cajunwebpro.com/catering` showed both canonical
  artifacts remained after direct delete, protected cross-parent retirement,
  and same-parent rename. That proves the PHP worker cannot change entries in
  the document root; another delete primitive cannot repair that ownership.
- Moved the installer into `site/core/Install` and routed uninstalled root
  requests through it. New packages ship no public `install.php` or `install/`
  artifact, so successful launch no longer requires document-root deletion.
- Installed sites continue best-effort cleanup of old artifacts without
  failing closed. Root access rules explicitly deny legacy `install.php` and
  `/install`; the old entry also retains its completed-install guard if a
  non-Apache server ignores `.htaccess`.
- Bumped Website Core to `0.1.6-dev` and catering to `0.1.6`.

## 2026-10-23 — same-parent installer retirement (`0.1.5-dev`)

- Ported the proven shared-host cleanup path from the earlier Velvet Vixxen
  installer into the shared Website Core. Cleanup still tries direct deletion
  and protected private-runtime retirement first, then renames blocked
  `install/` and `install.php` entries inside the site-root parent when a
  cross-parent move is rejected by hosting ownership rules.
- Same-parent retirement removes the canonical public URLs atomically. Hidden
  names are randomized, `install.php` becomes a non-PHP `.disabled` file,
  Apache/LiteSpeed deny rules preserve existing `.htaccess` content, and every
  later site load retries physical deletion of any protected residue.
- Safe chmod retries are limited to 0775 directories and 0664 files; no FTP,
  chown, shell requirement, or 0777 mode was introduced.
- Bumped Website Core to `0.1.5-dev` and catering to `0.1.5`.

## 2026-10-23 — installer cleanup retry and launch UI (`0.1.4-dev`)

- Centralized automatic cleanup in `site/includes/installer-cleanup.php`,
  outside the directory it removes. Cleanup targets only root `install.php`
  and `install/`, tries physical deletion first, and uses an atomic move into
  the protected private runtime when split ownership blocks child deletion.
- The shared public/admin cleanup gate repeats that exact cleanup on every
  later site load, but only when a valid generated config and `installed.lock`
  jointly prove installation completed. A database or marker alone is not
  sufficient authorization to delete installer files.
- Root `install.php` now detects the installed marker before loading the
  removable installer class. Partial cleanup therefore renders the full
  branded recovery screen instead of an unstyled missing-class error.
- The fail-closed screen has inline styling, subfolder-safe branding, and
  **Recheck cleanup**, **Visit website**, and **Open admin** actions. Recheck
  naturally enters the website once both canonical artifacts are gone.
- The completion screen now has separate public-site and admin actions.
- Reinstalling against an existing Website Core database now migrates in
  place, creates or refreshes the requested owner, preserves an existing home
  page and cron token, and upserts bundled license/package state.
- Bumped Website Core to `0.1.4-dev` and catering to `0.1.4`.

## 2026-10-23 — credential-free installer repair (`0.1.3-dev`)

- Removed the local FTP provisioner, FTP fields, and hosting-access test.
- Foreign-owned packaged directories now trigger automatic creation of a
  persistent mode-0700 runtime outside the document root. Bootstrap and the
  cleanup gate deterministically rediscover its configuration and locks.
- Runtime selection rejects public/temp paths, symlinks, weak permissions,
  and destinations that fail a complete create/read/rename/delete probe.
- Installer overview steps 01–03 now turn green with a checkmark and “Ready”
  as server checks, site/database details, and owner details are completed.
  The next incomplete step receives the active highlight.
- Bumped Website Core to `0.1.3-dev` and catering to `0.1.3`.

## 2026-10-23 — shared-host ownership fallback (`0.1.2-dev`)

- Replaced the ineffective direct-only `config/` and `storage/` ownership
  checks with a dual path: real PHP write probes first, then the proven
  local-only FTP provisioning fallback used in earlier CajunWebPro builds.
- `site/install/LocalFtpProvisioner.php` restricts transport to loopback,
  fingerprints this exact site before writes, uses only 0750/0775 directory
  modes, writes and verifies installer files, and retries full installer
  cleanup. No FTP password is persisted or repopulated.
- The polished installer shows an Automatic ownership repair section only
  when direct writes fail and includes a live **Test hosting access** action.
  Readiness is green when either direct writes or the supported fallback can
  perform provisioning; no 0777 workaround is used.
- Bumped Website Core to `0.1.2-dev` and catering to `0.1.2`. The locally
  assembled `Website-Core-catering-0.1.2-dev-TEST-BUNDLE.zip` passes ZIP,
  version, required-file, secret-exclusion, and fallback contract checks.
  Full PHP lint/PHPUnit was unavailable in the current container.

## 2026-10-23 — catering industry package

- The approved concept is an elegant editorial catering site: ivory and deep
  forest green, restrained gold, large event/food photography, serif display
  typography, and varied full-page layouts.
- Added `examples/industry-catering` (`cajunwebpro/catering` 0.1.1). It owns
  menu categories/items, packages, and catering inquiries and accesses core
  only through `ModuleContext` services.
- Public routes cover the homepage, services plus wedding/corporate/private/
  drop-off detail pages, menus and category detail, packages and package
  detail, gallery, story, service area, FAQ, contact, and a four-step quote
  planner. The module includes responsive navigation, accessibility controls,
  reduced-motion handling, and four optimized project-owned WebP photographs.
- Admin routes provide a dashboard, menu/package tools, lead pipeline status
  updates, and catering settings. Contact and quote submissions are CSRF-
  checked, honeypot-protected, rate-limited, stored in module-owned tables,
  and optionally queued to core mail.
- Added package regression coverage. CI confirms 187 PHP files lint clean,
  179 unit/package tests with 714 assertions, both MySQL 8.4 and MariaDB 11.4
  native/install acceptance, core-only boot, and release-artifact contracts.

## 2026-10-23 — keyless test-bundle installation

- Added `test-bundle <industry> [outdir]` packaging mode. Output is visibly
  named `TEST-BUNDLE`, omits `license_key` from the industry manifest, and
  includes a clean production-only Composer runtime in a direct web-root
  archive layout.
- Installer discovery no longer requires a license key: a keyless bundled
  industry module is registered, migrated, and enabled automatically without
  creating a license row or contacting the licensing API.
- Licensed bundles are unchanged: when `license_key` exists, install-time
  validation, storage, domain binding, and periodic enforcement still apply.
- Added package coverage for the keyless bundled-module lifecycle and updated
  installation/licensing documentation. CI now performs a real HTTP install
  on both MySQL and MariaDB, verifies no license row/API warning is created,
  and inspects the generated TEST-BUNDLE archive contract.
- CI verification caught and fixed an incomplete ModuleRegistry test harness
  call; the production MySQL/MariaDB keyless installation paths had already
  passed before that test-only correction.

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
- Branded one-page installer: capability checks, required SSL/HTTPS,
  seeded-token or bound-session claim, database connection test, safe
  writable-directory repair, migrations, owner creation, generated
  config+secrets, complete installer deletion, and fail-closed cleanup gate.
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
  installed.lock + config.php, complete installer deletion (404), and '/'
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

**Standing**: 165 tests / 647 assertions; CI green on `devin/website-core`.

## DRM / licensing system (new — commercial extension)

Website Core supports a DRM licensing model for bundled products
(core + industry module):

- `site/core/License/LicenseValidation.php` — phone-home validation,
  domain binding, deactivation handling.
- `site/core/Jobs/LicenseValidationJob.php` — cron job, enqueued hourly
  while deactivated and every 24h otherwise (pending-job deduped).
- `site/database/migrations/2026_10_15_0008_license_tracking.php` —
  license table schema (unique `license_key`).
- `site/core/Modules/PackageManager.php` — license checks before
  install/enable; `disable()` intentionally skips the check so an
  invalid license can never trap a site (covered by
  `testDisableAllowedWithInvalidLicenseForRecovery`).
- `site/install/Installer.php` — detects bundled modules via embedded
  `license_key` in `addon.json`, validates against the API, stores the
  key, registers the package, runs module migrations and marks it
  `enabled`. Endpoint: `{license.api_url}/validate.php`;
  `WC_LICENSE_API_URL` env overrides the default for install-time
  validation and generated config (used by tests/mocks).
- `site/core/Application.php` — public pages return 503 when an installed
  license is invalid/deactivated; `/admin` stays reachable.
- `tools/build-packages.php bundle` — creates bundled ZIPs with embedded
  license keys.
- `docs/LICENSING_API.md` — API contract.

Deactivation semantics: only `HTTP 200 + authorized:false` is a
definitive denial — non-200 statuses (429/400/5xx), network failures and
malformed responses are inconclusive and retry. A successful validation
clears `license_deactivated`.

State preservation (`recordValidation` accepts `?bool`): inconclusive
attempts update `license_validated_at`/`license_error` but preserve the
stored `license_valid` verdict and deactivation state — a transient API
outage can never take a licensed site offline, and no row is created by
an inconclusive first attempt.

Domain-follow binding: `detectDomain()` derives the reported domain from
the canonical `app.url` — a site genuinely moved (URL updated) to an
unregistered host self-denies at its next check instead of passing on
the stale stored domain. `HTTP_HOST` is deliberately untrusted (preview
URLs/IP access would cause false denials); `license_domain` refreshes to
the last domain actually validated. Falls back to stored domain if
config yields nothing. `app.url` is an **admin-editable settings
override** (`/admin/settings`) — the field stays blank to keep the
installed `config.php` value; filling it lets an owner point the site at
its newly-registered domain and recover via "Validate now" without a
config edit. Sitemap/robots read the same override.

"Validate now" admin action at `POST /admin/settings/license-validate`
triggers immediate revalidation + reactivation — owners don't wait for
cron.

Native CI leg (`licensed-bundle` step in the matrix jobs, real
MySQL/MariaDB + `php -S` + mock license API): staged bundle → licensed
install → license/packages/module-table assertions → valid site 200 →
moved-site (config edit) → cron denial → public 503, `/admin` 200 with
"License Warning" present → moved back → cron → public 200. The
core-only boot job also asserts a license-free install never renders
"License Warning".

The licensing service on cajunwebpro.com IS implemented (Cajun_Web
`main`): `api/validate.php` (rate-limited, audited), license service,
`license_domains` (max 2 active per license, www/apex canonicalized to
the same site), `license_bundles`, admin UI (`studio-licenses`), portal
domain management + owner-only `api/bundle-download.php`,
auto-fulfilment on paid invoices (`license_fulfill_invoice`, SKU
`WEBSITE-{INDUSTRY}`), customer email, master test keys
(`is_master` — authorize any domain, revocable, never issued to
customers), owner-gated upgrade adoption (`license_migrate` +
`license_ready()` guards on every entry point), license
deactivate/reactivate, portal full-key recovery for the authenticated
owner, serialized mutations (`license_transaction` + license-row
`FOR UPDATE` — the 2-active cap cannot be bypassed by concurrent
registrations and domain replace is genuinely atomic), per-invoice
fulfilment (`invoice_id` UNIQUE — a second purchase of the same product
mints a second license), and bundle SHA-256 integrity
(`license_bundles.sha256` recorded at generation, verified at download —
corrupted or tampered ZIPs refused with a 404 + audit; schema v3,
additive migrate for adopted stores).

Coverage: `LicenseValidationTest` + `LicenseApiContractTest` (9 tests
spawning a real `php -S` mock endpoint — verifies wire contract,
denial/inconclusive semantics, verdict preservation, reactivation) +
`BundledModuleInstallTest` + `testDisableAllowedWithInvalidLicenseForRecovery`
+ the native licensed-bundle CI leg. Cross-repo: Cajun_Web
`tests/license_bundle_functional.php` (23 checks) builds a real bundle
from this checkout and verifies it through the actual WC install path —
ZIP extraction → WC + module migrations (sqlite) → license/packages
rows → enable → real provider boot via `ModuleRegistry`.

`4d48dcb` fixed the installer's license check to honor the same
denial-vs-transient contract as the runtime validator: it previously
never read the HTTP status line, so a non-200 body carrying
`{"authorized":false}` (the API's own 5xx payload) was treated as a
denial, and every inconclusive outcome hard-blocked installation. Now
only `200 + authorized:false` blocks install; inconclusive outcomes
defer enforcement — the license row is stored valid with
`license_validated_at = NULL` so the scheduler verifies on the next cron
tick, and the success page reports the deferred check.
`InstallerLicenseContractTest` covers all seven outcome classes over
real HTTP against the shared mock fixture.

Standing: 176 tests / 667 assertions; lint clean. Cajun_Web `main` at
`490483c0` — eleven consecutive green 15-step application-acceptance
runs covering every licensing commit, including per-invoice fulfilment,
the bundle builder's config allowlist hardening, and bundle SHA-256
integrity.

## 2026-10-24 — Core 2.0.5 / Catering 0.1.17 branding handoff

- User approved the transparent generic CATERING CO. logo generated at
  `generated_images/exec-59dbdc56-fc70-49a9-abb2-acb8f55e2c4c.png`.
- Core 2.0.5 adds `Core\Branding\BrandAssets`, Module API v1
  `brandAssets()`/`brandAssetUrl()`, safe one-time package-to-Media copying,
  permanent seed markers, direct Appearance logo/favicon uploads, ICO support,
  previews, and shared admin logo/favicon rendering.
- Catering 0.1.17 includes `assets/images/catering-co-logo.png`, declares it as
  the starting logo, and renders current Core assets in public/module-admin
  layouts. Its compatibility range is `>=2.0.5,<3.0.0`.
- `Core_Mods/main` rules contract 1.1 commit:
  `ca6c9114830a240edd52bfad0b5d8da22dc93df6`.
- `Core_Mods/module/catering` release candidate commit:
  `eeead635a04d137c1753451d00e2a23f267647f9`; Module CI run 11 / ID
  `40050314261` was queued when this entry was written.
- Deterministic module ZIP SHA-256:
  `33a9279448505ef6490660d3461e5d5f71b53fb0467616ffc3ab2f10beafec8d`.
- Local PHP is unavailable in this workspace. GitHub CI is authoritative. Do
  not mark pending checkboxes complete until Module CI and Website Core's full
  six-job matrix pass. Continue omitting this local-only handoff file from Git
  Data API pushes because the connector previously rejected it as sensitive.

## 2026-10-24 — Core 2.0.8 shared-host update recovery

- Live verification at `demo.cajunwebpro.com/catering` showed Core `2.0.7`
  and catering `0.1.21` after an attempted file upload. Module Manager's
  journal contained no stage/upgrade record and the public menu still served
  the original dishes. `/admin/updates` also returned the site's not-found
  page, proving the installed Core runtime was only partially copied.
- Core `2.0.8` detects when an active package's on-disk manifest version
  differs from its registered database manifest and presents a deliberate
  **Finalize update** action. Finalizing validates compatibility/license,
  runs pending module migrations, synchronizes the database record, resets
  OPcache, and preserves the current package state.
- Staged ZIP upgrades now remember and restore `enabled` or `disabled` state;
  legacy plain-path stage journal records remain supported.
- The release builder now emits installer-free per-industry
  `REPAIR-UPLOAD` archives containing the complete Core runtime plus the
  industry under its bundled `modules/industries/industry-<slug>/` path.
  PackageManager also resolves later upgrades against the discovered active
  directory, preventing a duplicate id when bundled and managed directory
  names differ. Runtime configuration,
  storage, uploads, and installer entry files remain excluded.
- Added package tests for enabled-state preservation and direct-file migration
  finalization, plus administration contract coverage for the mismatch UI.

## 2026-10-24 — Core 2.0.9 Updates-screen 500 recovery

- After the verified 2.0.8 repair was deployed, live admin correctly reported
  Core 2.0.8 and catering files 0.1.23, but `/admin/updates` returned HTTP 500.
- The Updates controller now contains its staged-archive and journal inspection
  in a recovery boundary. Missing, unreadable, invalid, stale, or undeletable
  workspace files render an actionable admin warning and log the underlying
  exception; they no longer take the screen down.
- `CoreUpdater::inspect()` explicitly rejects missing/unreadable staged files,
  and `journalTail()` safely tolerates inaccessible journal storage.
- 2.0.9 regression coverage verifies those paths and the visible recovery UI.

## 2026-10-24 — Core 2.0.10 stale controller replacement

- Live 2.0.9 verification showed the module finalizer and migration succeeded,
  but Updates, Diagnostics, and Health all still returned HTTP 500. Their
  common dependency was the deployed/cached `SystemController` class, while
  unrelated admin controllers continued working.
- All System routes now use the fresh `SystemToolsController.php` class path.
  The previous file can remain harmlessly on a repaired host; Application no
  longer references it. A new path forces PHP autoload/OPcache to load the
  current implementation after hosting-panel extraction.
- CI and repair-artifact contracts require the new controller file and the
  Application routing reference.

## 2026-10-24 — Core 2.0.11 version-keyed OPcache refresh

- Live Core displayed 2.0.10 but all System routes still executed the failing
  pre-repair controller graph. This proved bootstrap refreshed while the host
  retained a stale cached Application class; renaming only the controller was
  insufficient because stale Application still referenced the old name.
- Bootstrap now runs `wc_refresh_runtime_code_cache()` before loading the Core
  autoloader. On a version change it resets OPcache and force-invalidates all
  Core/admin PHP files plus entry points, then records the version in the
  private config directory. Failed marker writes cause safe retries.
- Added direct unit coverage and artifact membership checks for the runtime
  cache helper. This is a Core-wide shared-host behavior, not a catering rule.
