# Changelog

## 2.5.0 — 2026-10-25

- Adds `starterForm()` so an industry package can declare one Core-owned form
  schema without overwriting later administrator edits, or explicitly adopt an
  existing form and preserve its submission history.
- Adds package-owned validation callbacks, fault-isolated `form.submitted`
  events, protected submission lookup and idempotent industry-record links.
- Makes package-owned forms unavailable whenever their package code is not
  enabled, preventing submission without required industry validation.
- Adds one-time browser submission tokens and database deduplication so retry
  posts do not create duplicate submissions, events, or notification emails.
- Adds editable plain-text notification templates with safe field tokens and
  keeps queue/delivery evidence attached to the shared submission inbox.
- Adds migration `2026_10_25_0031`, package-partitioned purge handling,
  Module API documentation and regression coverage. Module API v1 remains
  backward compatible; Catering and a second industry still need adoption.

## 2.4.0 — 2026-10-25

- Adds durable `seedContent()` defaults for package-declared content types;
  existing records and administrator-cleared content are never repopulated.
- Adds reusable single and multiple Core Media fields to generated module
  content editors with public-record validation and automatic usage tracking.
- Adds package-scoped `structuredData()` providers. Core supplies `@context`,
  validates bounded allowlisted Schema.org objects, escapes JSON-LD against
  markup termination, combines contributions, and isolates provider failures.
- Fixes generated-content deletion so a submitted package/type cannot delete
  another package's record. No schema migration or API-major change is needed.

## 2.3.0 — 2026-10-25

- Completes the reusable module record workspace with accessible visible-row
  ordering and allowlisted bulk actions while modules retain transactional
  persistence and package-ownership rules.
- Rejects malformed, non-positive, excessive, or undeclared record actions
  before module callbacks run; successful changes receive standard feedback
  and package-attributed audit evidence.
- Adds select-all, keyboard-reachable move controls, responsive action layouts,
  callback-contract documentation, and controller/API regression coverage.
- Keeps Module API v1 backward compatible by adding one optional final
  `adminResource()` options argument. No schema migration or industry-module
  synchronization is required.

## 2.2.2 — 2026-10-25

- Repairs the shared inquiry detail and internal-notes workflow against the
  canonical administrator schema by using `username` for note attribution and
  assignment choices.
- Adds regression coverage for zero-note detail, attributed notes, package
  isolation, administrator selection, and rendered detail output.
- Preserves all inquiry and note records. No migration, Module API change, or
  industry-module synchronization is required.

## 2.2.1 — 2026-10-25

- Adds credential-free SMTP discovery in Settings with separate SSL/465 and
  STARTTLS/587 results and certificate-verified encrypted greetings.
- Suggests a reverse/provider hostname only after forward DNS confirms the
  original server address; diagnostic sockets remain pinned to that address.
- Keeps discovery separate from authentication and saved mail configuration.
  No message or credentials are sent, and no settings are saved by discovery.
- Adds bounded socket operations and reply sizes, safe diagnostic messages,
  DNS/deadline coverage, and real loopback TLS/STARTTLS protocol tests.
- No schema migration, industry snapshot change, or Module API change.

## 2.2.0 — 2026-10-25

- Adds one Core-owned business profile for contact details, address, service
  area, and hours, with seed-once module defaults that never overwrite edits.
- Adds opt-in, package-scoped inquiry and quotation services with assignment,
  priorities, internal notes, itemized totals, expiry, and secure one-time
  customer acceptance or decline links.
- Adds reusable module administration APIs: shared-shell rendering and a
  declarative list/editor resource contract with Core validation, search,
  pagination, permissions, CSRF, confirmations, and auditing.
- Adds additive migration `2026_10_25_0030`, package-preserving disable and
  uninstall behavior, explicit purge cleanup, tests, and Module API docs.
- Replaces generic SMTP connection failures with DNS, TCP-port,
  authentication, and TLS-handshake diagnostics while keeping tests in-page.

## 2.1.0 — 2026-10-25

- Adds module-activated Core commerce: products, inventory-aware carts,
  checkout, immutable order-item snapshots, and an administrator order
  workflow. Store routes and navigation remain absent unless an enabled
  module declares the relevant feature.
- Adds manual Venmo, PayPal, and Cash App checkout methods with administrator
  on/off controls and customer instructions. Manual orders remain payment
  pending until staff records receipt; no gateway confirmation is implied.
- Adds optional public customer accounts with registration, sign-in, profile,
  password recovery, order history, rate limiting, and Core customer
  administration. Modules that do not opt in expose none of these surfaces.
- Adds package ownership to commerce/customer records. Disable and uninstall
  preserve data; explicit package purge removes the package partition from
  Core-owned feature tables.
- Adds additive migration `2026_10_25_0029`, Core/module feature declarations,
  conditional permissions/navigation/search/dashboard integration, audit
  records, transactional order notices, and `commerce.order.placed` webhooks.

## 2.0.32 — 2026-10-25

- Adds Core-owned, first-party analytics across Core pages and every industry
  module without requiring Google Analytics or module-specific tracking code.
- Adds an owner-only Analytics workspace with traffic trends, live visitors,
  visitor journeys, acquisition, device/browser/OS, engagement, geography,
  entry/exit pages, crawler separation, and weekly activity reporting.
- Adds Search Console CSV import, safe CSV export, configurable retention, and
  automatic bounded cleanup through the existing shared-host cron runner.
- Excludes administrator and preview traffic, DNT/GPC visitors, prefetches,
  sensitive endpoints, and unsuccessful pages; raw IPs and full user-agent
  strings are never stored.
- Adds additive migration `2026_10_25_0028` and shared-host/no-rewrite-aware
  analytics routes, assets, filters, and heartbeat collection.
- Moves both transactional-email tests beneath the SMTP settings and runs
  them in-page with bounded timeouts, live success/error feedback, and no
  navigation to a host-generated Bad Gateway screen.
- Defaults a previously unconfigured site to SMTP over implicit SSL on port
  465, deriving `mail.<domain>` and `admin@<domain>` from the installed public
  domain while preserving every explicitly saved mail value.
- Displays the effective installed Base URL as a locked, muted Website Identity
  field and rejects attempts to change installation identity through Settings.

## 2.0.31 — 2026-10-25

- Rebuilds 404 insights as responsive resolution cards so paths, traffic,
  timestamps, redirect choices, and dismissal actions remain aligned.
- Aligns the managed-site connection form and gives the current administrator
  a direct, clearly worded MFA setup action when protection is missing.
- Adds encryption-aware SMTP port defaults and a connection/authentication
  test that neither sends mail nor saves unverified values.
- Reworks Forms into one coherent three-step workspace with full-width
  guidance, a wider field editor, starter field sets, live preview, and a
  clearer save-and-place handoff.

## 2.0.30 — 2026-10-25

- Backs up and restores configured private configuration, upload, and media
  roots on split-ownership shared hosts.
- Preflights changed file destinations before importing SQL and skips
  byte-identical read-only deployment files that require no replacement.
- Adds an owner-only verified recovery-point download with streamed delivery,
  private/no-store headers, and audit evidence.
- Adds external-runtime, read-only-file, and file-response regression coverage
  after live restore exposed a panel-owned `config/.htaccess` failure.

## 2.0.29 — 2026-10-25

- Repairs MariaDB/MySQL backup table discovery by replacing the unsupported
  native-prepared `SHOW TABLES LIKE ?` form with a safely bound
  `information_schema.tables` query.
- Treats table-prefix wildcard characters literally and retains deterministic
  table ordering in backup manifests.
- Adds native MySQL and MariaDB regression coverage that creates and inspects
  an actual Core backup during CI.
- Makes repair-artifact staging and upload names resolve the current Core
  version instead of retaining a previous release number.
- Records the first packaged shared-host acceptance findings and keeps final
  commercial tagging gated on the repaired live backup/restore pass.

## 2.0.28 — 2026-10-24

- Adds the permanent root module-rules checklist and aligns executable API,
  package-schema, lifecycle, repository, and release guidance.
- Enforces directory type, duplicate-ID, safe-path, theme-hook, provider,
  PSR-4, contribution, and exactly-one-industry boundaries.
- Detects dependency cycles and quarantines dependents whose required package
  did not boot, while keeping Core and other packages available.
- Supports validated migration-directory lists in uploaded, finalized, and
  bundled installs and rejects missing declared directories truthfully.
- Expands module contract tests and updates native installer/artifact CI for
  the setup CSRF contract and final 2.0.28 packages.

## 2.0.27 — 2026-10-24

- Adds installer CSRF/session hardening and a non-blocking operation lock for
  concurrent or duplicate submissions.
- Detects existing Website Core schemas during connection tests and requires
  explicit protected-reinstall confirmation before reuse.
- Preserves recoverable app keys, validates canonical HTTPS/field boundaries,
  and converts database driver failures into safe operator guidance.
- Verifies and atomically commits configuration and the permanent install
  marker instead of writing final files in place.
- Adds a completion-screen cleanup recheck action and focused installer
  lifecycle/URL contract coverage without a schema migration.

## 2.0.26 — 2026-10-24

- Completes agency credential editing, encrypted key rotation, pause/re-enable,
  duplicate prevention, and confirmed credential removal.
- Hardens outbound health checks with current public-HTTPS validation, strict
  TLS/protocol controls, no redirects, bounded responses, and safe errors.
- Preserves last known healthy remote inventory across failures while adding
  latency, consecutive-failure, active-state, and key-update evidence.
- Rebuilds the Agency dashboard around actionable health and relative Core
  version signals and expands focused lifecycle coverage.
- Adds additive migration `2026_10_25_0027` for agency lifecycle telemetry.

## 2.0.25 — 2026-10-24

- Repairs Audit’s canonical schema use and adds context/IP search, event-family
  filtering, responsive timeline presentation, and safe bounded CSV export.
- Cryptographically binds staged-update review to the archive, adds discard,
  and rolls replaced Core files back when post-write migrations fail.
- Validates all website/mail settings as one change, retains non-secret failed
  input, enforces HTTPS base URLs, and adds scheduled-task token rotation.
- Adds HTTPS, required-extension, and cron-heartbeat evidence to isolated
  System Health collectors.
- Adds focused system-control regression coverage without a schema migration.

## 2.0.24 — 2026-10-24

- Adds automatic integrity inspection and detailed verification evidence to
  every visible recovery point.
- Stops backup creation or file restore on required read/write failure instead
  of silently shipping or accepting incomplete recovery data.
- Preserves/audits local backup success separately from optional remote-copy
  failure and makes delete/maintenance filesystem outcomes truthful.
- Prevents duplicate queued backups, records cron heartbeat, and exposes
  deliberate recovery of interrupted leases before retry.
- Rebuilds backup presentation as responsive verified recovery cards and adds
  focused operational regression coverage.

## 2.0.23 — 2026-10-24

- Completes Modules with lifecycle summaries, package filtering, clearer
  protected-Core/data-preservation presentation, and safer purge identifiers.
- Makes recovery-mode filesystem changes truthful and actionable when shared-
  host ownership prevents creating or removing the flag.
- Adds duplicate-safe catalog registration plus disable, re-enable, and
  disabled-only removal workflows for signed marketplace sources.
- Retains dependency gates, verified staging, rollback journaling, separate
  uninstall/purge semantics, and recovery boot isolation.
- Adds focused marketplace and module commercial-contract coverage.

## 2.0.22 — 2026-10-24

- Adds persistent visitor privacy-choice management and enforces explicit
  consent whenever optional analytics is configured.
- Expands privacy requests into a verified, resolution-evidenced lifecycle
  with reopening, audit history, safe CSV export, and a polished public center.
- Adds atomic API-key rotation and webhook editing, test delivery, HMAC-secret
  rotation, activation, and failed-delivery retry controls.
- Bounds portable site imports at 5 MB and strengthens workflow feedback while
  preserving the private-data exclusion contract.
- Adds additive MySQL/MariaDB/SQLite-compatible privacy evidence columns and
  focused integration/consent regression coverage.

## 2.0.21 — 2026-10-24

- Rebuilds Administrators, Roles, My Profile, and Security as responsive,
  evidence-oriented workspaces with clearer account, assignment, MFA, session,
  and recovery state.
- Adds unique administrator-email validation, failed-create value retention,
  self-service username/email updates, and grouped human capability guidance.
- Enforces the non-owner management capability on enable/disable actions and
  makes last-owner protection transactional with the requested mutation.
- Adds cancellable MFA enrollment, password-confirmed recovery-code rotation,
  one-time copy support, remaining-code visibility, and session revocation when
  recovery credentials or MFA state changes.
- Adds focused role/account security regression coverage without a schema
  migration.

## 2.0.20 — 2026-10-24

- Rebuilds Appearance as a responsive visual brand workspace with live token
  preview, direct social-image upload, clearer asset guidance, and visual theme
  cards while retaining entered settings after validation errors.
- Rejects incompatible theme activation and completes child-theme inheritance
  by accepting the documented `parent` key as well as legacy `extends`.
- Loads declared child-theme CSS/JS through Core's constrained subfolder-safe
  asset route and adds complete baseline social metadata.
- Adds an accessible mobile public-navigation control with Escape and link
  close behavior while preserving a usable no-JavaScript menu.
- Adds focused appearance/theme contract regression coverage without a schema
  migration or industry-specific Core behavior.

## 2.0.19 — 2026-10-25

- Consolidates redirect ownership in SEO with complete create, edit, remove,
  hit-count, 404-recovery, CSV import, CSV export, loop, and conflict handling;
  Navigation now links to that single authoritative workspace.
- Expands published-page audits to catch missing titles, malformed canonical
  URLs, intentional noindex state, invalid structured data, corrupt visual
  blocks, missing alternative text, weak link labels, and search-length gaps.
- Rebuilds Translations as a side-by-side localized-content workspace with
  language progress, public/hidden switches, live search previews, safe
  removal, failed-save retention, and validated block/schema JSON.
- Ensures hiding a language retains editor work while removing its public URL,
  navigation records, and alternate-language metadata until re-enabled.
- Rebuilds Layout Patterns as a searchable visual library with block-order
  previews, safe draft application, revision guidance, renaming, duplication,
  conflict validation, and confirmed deletion.
- Adds focused service and workspace regression coverage and rebuilds the
  directly usable catering and landscaping release artifacts as Core 2.0.19.

## 2.0.18 — 2026-10-25

- Changes failed document-root create, rename, and delete checks from a red
  required failure to a yellow installer warning that permits setup to continue.
- Keeps completed sites and administration available when hosting ownership
  prevents physical installer cleanup, while retaining permanent setup lockout,
  later cleanup retries, and clear File Manager instructions.
- Rebuilds the combined Landscaping Website 1.0.0 installer and shared-host
  repair upload on the latest Core release.

## 2.0.17 — 2026-10-24

- Rebuilds Forms as a guided responsive workspace with live public preview,
  pointer drag ordering, keyboard move controls, and reusable field help,
  placeholder, autocomplete, required, and dropdown settings.
- Preserves the complete entered form after validation errors and reports
  duplicate names, invalid dropdowns, excessive lengths, and missing records
  instead of silently discarding or creating data.
- Lets administrators pause and reactivate forms without losing configuration
  or submissions and provides clearer active/new/total response summaries.
- Replaces the raw submission table with a filterable response inbox,
  expandable details, consent evidence, mail-queue state, selection feedback,
  bulk status actions, and confirmed permanent deletion.
- Exports response values into useful CSV columns, includes consent and
  notification evidence, and protects spreadsheet users from formula
  injection.
- Carries portable form definitions and remapped page Form blocks through site
  transfer while excluding notification recipients and all submissions.
- Validates submitted dates and dropdown choices server-side, records consent
  timestamps, connects notification messages to their submission, and uses
  the shared branded response page.

## 2.0.16 — 2026-10-24

- Rebuilds Navigation as a focused responsive workspace with link-type and
  visibility status, direct parent selection, guided targeting, and clearer
  header/footer menu summaries.
- Makes drag-and-drop place links before, after, or inside another item while
  retaining keyboard ordering controls, depth protection, unsaved-state
  guidance, and explicit save/discard actions.
- Adds per-link visibility so administrators may stage or temporarily hide a
  link and its submenu without deleting it; site export/import preserves that
  visibility choice.
- Preserves Same tab, New tab, and New window behavior in Core's public menu,
  adds active-page indication, and respects intentionally hidden custom menus.
- Accepts safe page anchors and telephone destinations in addition to internal,
  web, and email links; enforces field lengths before database writes.
- Lets existing redirects be edited safely and prevents duplicate-source or
  missing-record updates from becoming administrator errors.
- Promotes children one level in place when a parent link is removed instead
  of unexpectedly sending every child to the top of the menu.

## 2.0.15 — 2026-10-24

- Rebuilds the verified Landscaping Website 1.0.0 TEST-BUNDLE and directly
  usable 2.0.15 REPAIR-UPLOAD with native database lifecycle coverage.
- Makes private-image thumbnails available to authorized Media administrators
  while retaining permission checks and private, no-store browser caching.
- Tracks page-block and social-sharing image references so Media shows the
  pages using a file and warns accurately before a referenced file is deleted.
- Backfills those references for existing pages during the Core upgrade, so
  installed sites receive accurate deletion warnings without resaving pages.
- Polishes Media with selected-file feedback, guarded bulk actions, stable URL
  copying, visual focal-point controls, and direct links to referencing pages.

## 2.0.14 — 2026-10-24

- Restores the previous package files, manifest, version, and enabled state
  when an uploaded module upgrade reaches a failing migration.
- Expands landscaping acceptance coverage across customer-data-preserving
  upgrades and uninstall, queued estimate mail, protected photo routes, and
  all four theme switches.
- Runs the landscaping package install, enable, replacement, failed-migration
  rollback, disable, and uninstall lifecycle against native MySQL and MariaDB.
- Keeps the allowlisted Core asset route available during maintenance and
  license-denial states so login and authenticated administration retain their
  styles and JavaScript on root and subfolder installations.
- Synchronizes the verified Landscaping Website 1.0 runtime from Core_Mods,
  including estimate intake, service and project content, maintenance plans,
  service areas, seasonal services, administration, and shared Core branding
  and navigation integration.
- Adds four installable landscaping themes: Bold Outdoors (default), Estate
  Green, Neighborhood Lawn, and Earth & Stone.
- Builds and validates landscaping TEST-BUNDLE and shared-host REPAIR-UPLOAD
  artifacts and runs its migration against native MySQL and MariaDB in CI.
- Adds a persistent collapsible desktop sidebar and optional compact workspace
  density while retaining each administrator's expanded navigation groups.
- Replaces browser-native destructive prompts with an accessible branded
  confirmation dialog and safely restores auto-submit switches when canceled.
- Adds reusable, keyboard-accessible on/off switches to maintenance, MFA,
  form availability, consent, webhooks, administrator accounts, and modules.
- Protects substantial admin forms from accidental navigation, adds live
  character counts, previews selected brand images, and disables irrelevant
  dependent fields without erasing saved SMTP credentials.
- Reorganizes Operations into clear availability, housekeeping, scheduling,
  and job-monitoring areas with responsive commercial-product styling.
- Repairs the standalone server-error-page test fixture so its temporary
  installation root exists before `.htaccess` provisioning is exercised.
- Moves System Health and Audit to fresh dedicated controllers and isolates
  their data access so one broken query cannot take down either workspace.
- Makes Health collector and Audit query failures render useful partial or
  empty states with actionable administrator warnings.
- Registers a front-controller fatal shutdown renderer so the bundled branded
  500 page works even when a shared host ignores `.htaccess` ErrorDocument.
- Anchors administrator CSS and JavaScript to the installation subfolder so
  `/catering` deployments never request Core assets from the domain root.
- Keeps internal asset, admin, API, installer, and cron misses out of public
  SEO 404 insights while hiding historical infrastructure rows already logged.
- Seeds new home pages with a descriptive title and search summary instead of
  an incomplete placeholder that immediately fails the SEO audit.
- Polishes SEO insights with responsive tables, actionable issue guidance,
  single-path dismissal, and automatic cleanup after creating a redirect.
## 2.0.13 — 2026-10-24

- Moves Core Updates into a dedicated `UpdatesController` with a new class
  path, independent of the broader System tools controller.
- Makes the Updates GET route metadata-only; corrupt, oversized, unreadable,
  or abandoned staged ZIP files are never opened simply by viewing the page.
- Writes compact review metadata only after an explicit upload passes the full
  archive and signature inspection, and requires that review before apply.
- Adds a polished static `errors/500.html` and provisions a subfolder-aware
  Apache/LiteSpeed `ErrorDocument 500` mapping for failures before PHP can
  render Core's normal branded error response.

## 2.0.12 — 2026-10-24

- Loads Core and administration PHP through private, version-specific runtime
  paths so hosts that disable OPcache reset/invalidation cannot reuse old
  bytecode after a hosting-panel upload.
- Anchors mirrored autoload resolution to `WC_ROOT`, preserving the installed
  site's real Core and administration directories from any private cache path.
- Limits pathname mirroring to Core-owned `core/` and `admin/` classes so
  module providers retain their real `__DIR__` for bundled views and assets.
- Keeps the existing OPcache refresh as a best-effort optimization while no
  longer depending on host support for correctness.
- Shows the exception type, message, file, and line only to an authenticated
  administrator on admin error pages; public visitors retain the opaque error
  reference.
- Falls back to the private configuration log directory when the configured
  storage log directory cannot be created or written.

## 2.0.11 — 2026-10-24

- Adds a version-keyed runtime code-cache refresh before Core autoloading.
- Resets OPcache and force-invalidates Core/admin PHP files on the first
  request after a hosting-panel deployment changes the Core version.
- Records the refreshed version in the private configuration directory; an
  unwritable marker safely retries on later requests instead of blocking boot.
- Prevents mixed deployments where the sidebar reports a new version while
  PHP still executes an older cached Application routing table.

## 2.0.10 — 2026-10-24

- Moves Health, Diagnostics, Audit, and Core Updates routes to the fresh
  `SystemToolsController` class/file path.
- Forces shared-host OPcache/autoloaders to load the repaired System tools
  implementation after hosting-panel file replacement instead of retaining a
  stale cached `SystemController`.
- Keeps the 2.0.9 fail-safe update-workspace handling intact on the new path.
- Adds an administration routing regression contract for the replacement
  controller.

## 2.0.9 — 2026-10-24

- Prevents `/admin/updates` from returning HTTP 500 when shared-host storage
  contains an unreadable, invalid, stale, or undeletable staged update file.
- Converts update-workspace failures into an actionable administrator warning
  while leaving all live Core files untouched.
- Makes update-journal reading tolerate missing and unreadable host files.
- Adds regression coverage for missing staged archives, unusable journal paths,
  and the recoverable Updates-screen error state.

## 2.0.8 — 2026-10-24

- Detects module files replaced directly through a shared-hosting file manager
  when their disk manifest differs from the registered database version.
- Adds a safe **Finalize update** action that validates the active package,
  runs pending migrations, synchronizes the manifest/version record, resets
  OPcache, and preserves the module's current enabled or disabled state.
- Preserves enabled and disabled states across ordinary staged ZIP upgrades;
  an enabled industry site no longer needs a second manual enable step.
- Records structured stage metadata while retaining compatibility with
  packages staged by earlier Core releases.
- Produces installer-free per-industry REPAIR-UPLOAD archives for recovering
  partially copied shared-host installations in one root-level extraction.
- Resolves upgrades against the package's existing discovered directory, so a
  bundled `industry-catering` install is replaced in place instead of creating
  a second `catering` directory with the same package id.

## 2.0.7 — 2026-10-24

- Enlarged and vertically stacked the administration logo, removed the
  duplicate site-name label, and retained a single-line Website Administration
  caption.
- Removed opaque logo frames from the administration sidebar and catering
  footer so the existing transparent PNG blends with its surface.
- Expanded the Core social directory to fifteen common business networks with
  icon previews and replaced per-network placement selectors with one global
  Header, Footer, or Header and footer dropdown.
- Preserved upgraded sites by deriving the new global location from legacy
  profile placement values until an administrator saves Appearance.
- Updated the bundled catering module snapshot to `0.1.21`.

## 2.0.6 — 2026-10-24

- Reworked the shared administration shell with destination icons, page-purpose
  guidance, responsive tables, persistent navigation state, consistent loading
  feedback, safer confirmations, polished cards, filters, and empty states.
- Completed Media, Navigation Menu, and Forms workflows with pagination,
  search/filter state, status transitions, selection feedback, organization,
  field guidance, visual previews, and administrative audit evidence.
- Polished SEO, translation, appearance, account/security, privacy,
  integrations, modules, recovery, backup, operations, health, update, audit,
  settings, marketplace, and agency screens around their real tasks.
- Added direct mail-transport testing, webhook enable/disable and failed
  delivery retry, searchable paginated audit history, and consistent auditing
  of security-sensitive administrator mutations.
- Fixed public-site launch links for subfolder and no-rewrite installations by
  separating the real site base path from the administration route base.
- Added acceptance coverage for the shared admin presentation contract,
  immediate mail testing, filtered form submissions, Media pagination, and
  webhook retry behavior.

## 2.0.5 — 2026-10-24

- Added Module API v1 `brandAssets()` and `brandAssetUrl()` methods. Industry
  modules may declare starting logo/favicon/social-image files; Core safely
  copies them into Media once and permanently owns the editable selections.
- Preserved administrator choices: existing brand media is never overwritten,
  and an intentionally cleared module default is never restored on a later
  request or package update.
- Added direct logo and favicon upload controls, current-asset previews, Media
  selectors, and ICO support to Website → Appearance.
- Wired the selected Core logo and favicon into the shared administration
  shell, base public theme, and catering public/admin layouts with accessible
  fallbacks.
- Updated the catering module snapshot to `0.1.20` with the approved
  transparent generic **CATERING CO.** logo and Core `>=2.0.5,<3.0.0` range.
- Added path-confinement, one-time import, source-preservation, Appearance UI,
  and reference-package regression coverage for the branding handoff.
- Expanded Website → Navigation Menu with isolated **Header menu** and
  **Footer menu** editors using the same visual tree, link sources, nesting,
  ordering, and target controls.
- Added a plain-text custom copyright notice in Website → Appearance with
  `{year}` and `{site_name}` tokens; the mandatory Cajun Web Pro attribution
  remains independently enforced by Core.
- Catering now seeds an editable footer menu once and renders the current
  Core-managed footer links and copyright notice.
- Consolidated module summaries into the Core Dashboard overview. Catering
  contributes its live inquiry widget there and no longer ships a duplicate
  dashboard route, view, or navigation entry.
- Added optional Facebook, Instagram, LinkedIn, YouTube, TikTok, and X profile
  fields to Appearance. Each valid nonblank HTTPS profile can render in the
  header, footer, or both; blank fields produce no markup.

## 2.0.4 — 2026-10-23

- Kept the complete `/admin` namespace reachable while public maintenance
  mode is enabled, allowing authentication middleware to redirect a logged-out
  `/admin` request to the administrator login page.
- Preserved normal authentication and capability checks for every protected
  admin screen; this change does not expose administration to the public.
- Added a subfolder/no-rewrite regression test proving `/catering/admin`
  reaches `/catering/index.php?p=/admin/login` while public pages still receive
  the maintenance response.

## 2.0.3 — 2026-10-23

- Rebuilt the page list with working no-rewrite search/filter controls,
  status summaries, pagination, clearer empty states, scheduled-state detail,
  direct public links, and confirmed destructive actions.
- Rebuilt the page editor as a responsive two-column workspace with retained
  validation input, parent-page and social-image selectors, live SEO preview,
  character counts, keyboard-capable block ordering, unsaved-change
  protection, and a draft preview that opens the actual preview page.
- Added optimistic edit locking so stale browser tabs cannot silently
  overwrite newer changes, plus complete revision snapshots which restore URL,
  body, blocks, SEO, sharing, schema, indexing, and parent settings.
- Added strict errors for invalid canonical URLs, schema JSON, media, parent
  pages, and publishing dates; missing or deleted records now return 404
  rather than displaying false success messages.
- Added audited duplication, restore, revision restore, trash, and permanent
  deletion workflows with dependent preview, translation, menu, and revision
  cleanup.

## 2.0.2 — 2026-10-23

- Froze broad feature development and established a permanent, ordered
  commercial-hardening audit for every existing Core surface.
- Rebuilt the shared administration shell around versioned Core CSS and
  JavaScript served through the constrained, rewrite-independent asset route.
- Added a polished responsive sidebar/topbar, accessible mobile navigation,
  persistent category/scroll state, clearer page hierarchy, compact account
  actions, announced/dismissible alerts, and comprehensive shared UI states.
- Turned passive dashboard metrics into real management links and added useful
  quick actions for existing content, media, navigation, and settings flows.
- Expanded automated contracts for Core admin asset delivery, keyboard/mobile
  controls, external assets, focus visibility, and reduced motion.

## 2.0.1 — 2026-10-24

- Renamed the Website-category admin item and page to **Navigation Menu** so
  the visual editor is immediately recognizable.
- Updated the catering module snapshot to `0.1.16`: its current header links
  are initialized into Core's primary menu once and shown in the editor, while
  preserving existing customized menus and intentionally empty menus.
- Added Module API v1 `navigationMenu()` and `navigationTree()` methods so
  every industry module can own its starting menu while Core takes over the
  editable stored copy after installation.
- Wired the catering header to Core's nested menu tree with the selected
  same-tab, new-tab, and dedicated new-window targets.

## 2.0.0 — 2026-10-23

- Added an owner-only agency dashboard with encrypted remote credentials,
  authenticated health checks, and core/industry version visibility.
- Added signed marketplace catalogs and policy-driven, verified managed core
  update staging or application with safety backups and maintenance windows.
- Rebuilt the navigation editor as a polished visual tree with drag-and-drop
  ordering/nesting, keyboard-friendly structure controls, page lookup,
  external links, and same-tab/new-tab/new-window behavior.
- Preserved Module API v1 compatibility for existing industry modules,
  add-ons and themes.

## 1.9.0 — 2026-10-23

- Added scoped, revocable API keys and authenticated health/content endpoints.
- Added durable HTTPS webhooks with encrypted signing secrets, HMAC payloads
  and delivery history.
- Added portable site-content export/import and optional HTTPS WebDAV backup
  replication configured outside the database.

## 1.8.0 — 2026-10-23

- Added owner-managed custom roles backed by the core capability registry.
- Added draft review requests, publish approval boundaries, review notes and
  side-by-side revision comparison.

## 1.7.0 — 2026-10-23

- Added multilingual page variants, localized URLs and navigation,
  language-switch links and hreflang metadata.

## 1.6.0 — 2026-10-23

- Added search previews, page schema markup, automated content checks and
  validated redirect CSV import/export.

## 1.5.0 — 2026-10-23

- Added consent-gated analytics, public privacy requests, the private request
  inbox, completion tracking and CSV export.

## 1.4.0 — 2026-10-23

- Added reusable layout patterns with revision-safe application.
- Added selectable header/footer layouts and parent-theme template fallback.

## 1.3.0 — 2026-10-23

- Added searchable media folders and tags, bulk organization, focal points,
  crop derivatives and per-item usage inspection.
- Added an offline-safe branded status-page system for maintenance, 403, 404,
  500 and license states with local artwork and a discrete admin-login entry.

## 1.2.0 — 2026-10-23

- Added reusable form definitions and a visual field editor with text, email,
  telephone, numeric, date, textarea, select and consent-checkbox fields.
- Added a Form page block, session CSRF validation, honeypot handling,
  IP-scoped rate limiting, server validation and consent evidence.
- Added a submissions inbox, read state, CSV export and deduplicated queued
  notification emails. Archiving a form preserves its prior submissions.

## 1.1.0 — 2026-10-23

- Added a visual, drag-order page composer with eleven responsive core block
  types, hide/show controls, full-width rendering, and legacy-body fallback.
- Block documents are normalized and sanitized on the server, retained in
  page revisions and duplicates, and rendered through the active theme.
- Added `ModuleContext::pageBlock()` so enabled industry packages can provide
  specialized block schemas and trusted renderers without modifying core.

## 1.0.1 — 2026-10-23

- Automatically apply pending core database migrations during installed-site
  boot when the bundled schema marker is absent. This repairs sites updated by
  replacing files through a shared-hosting file manager and prevents the
  authenticated admin dashboard from querying tables that do not yet exist.
- Put the same ten-character reference in PHP's error log, the administrator's
  private `storage/logs/application.log`, and the styled production error page
  so a reported failure can be traced instead of producing an unrelated ID.

## 1.0.0 — 2026-10-23

### Stable core release
- Promoted the complete 0.1.15 through 0.9 milestone line to the first stable Website Core release.
- Added final release notes, compatibility evidence, upgrade guidance, checksummed packages, and session-to-session Git documentation.
- Preserved the extension API at major version 1; industry modules and themes remain independently versioned.

## 0.9.0-dev (unreleased)

### Added — release candidate and compatibility gate
- Added a dependency-free release gate for version alignment, manifests, migrations, installer cleanup, immutable attribution, cache safety, documentation, and packaging contracts.
- Added an offline, verified production-vendor input for reproducible package builds.
- Added cumulative-update compatibility metadata beginning at `0.1.14-dev`, configurable per release with `WC_UPDATE_MIN_CORE`.
- CI now invalidates Composer caches when either `composer.json` or `composer.lock` changes.

### Fixed
- Public HTML caching is now explicit opt-in; industry forms containing session-bound CSRF tokens remain private and no-store.
- Admin license notices now preserve the original response headers instead of reconstructing the response.

## 0.8.0-dev (unreleased)

### Added — SEO and public performance
- Added aggregated public 404 insights with referrer evidence and one-step validated redirects.
- Added Open Graph URL/type data, default social imagery, and Schema.org organization metadata.
- Added sitemap trash filtering plus explicit sitemap/robots cache policy.
- Added strong ETags, conditional 304 responses, and bounded caching for public HTML.

## 0.7.0-dev (unreleased)

### Added — operations and recovery
- Added an owner operations console for maintenance mode, queue retry/pruning, cache cleanup, mail processing, and queued backups.
- Added daily, weekly, and 30-day automatic backup scheduling through the bounded cron queue.
- Added protected media to backup/restore coverage and automatic pre-restore safety snapshots.
- Restore now pauses public/cron activity, preserves the prior maintenance state, and resets OPcache after success.

## 0.6.0-dev (unreleased)

### Added — administrator security
- Added active-session identification, last-seen updates, individual revocation, and sign-out-all-other-devices.
- Added an administrator security dashboard with MFA coverage and active session counts.
- Added owner-safe mandatory MFA policy enforcement and protected MFA-disable behavior.
- Added HSTS on HTTPS, no-store admin responses, and additional cross-origin security headers.

## 0.5.0-dev (unreleased)

### Added — package integration platform
- Added stable package hooks for dashboard widgets, global admin search, and system-health checks.
- Added capability filtering and fault isolation around package-provided admin data.
- Added explicit staged-upgrade detection and Upgrade labeling in the package lifecycle UI.
- Added a code-owned Cajun Web Pro LLC attribution footer to every public HTML response.

## 0.4.0-dev (unreleased)

### Added — media and design system
- Added in-place media replacement that preserves IDs, metadata, and usage references.
- Added bounded, cached image derivatives with safe original-image fallback when GD is unavailable.
- Added core-owned logo, favicon, social-image, color, and radius controls for compatible themes.
- Added branding usage tracking and public base-theme rendering for those shared design tokens.

## 0.3.0-dev (unreleased)

### Added — content workflow
- Added scheduled publishing and unpublishing with idempotent request/cron-safe transitions.
- Added page duplication, soft-delete trash, restore, and clearer active/trash views.
- Expanded the core menu editor to edit labels, URLs, sort order, and nested submenu parents.
- Added circular-menu protection and recursively rendered accessible dropdown navigation.

## 0.2.0-dev (unreleased)

### Added — administration platform
- Added capability-aware global search across pages, media, and installed packages.
- Added durable, dismissible administrator notifications with optional actions and expiry.
- Rebuilt the dashboard around published content, package, queue, and integrity status.
- Added responsive admin metrics, notification surfaces, and compact header search.

## 0.1.15-dev (unreleased)

### Added — stabilization and integrity inventory
- Added one authoritative runtime version inventory for the core and enabled
  industry packages, used throughout the administration shell.
- Added non-destructive installation-integrity checks for required files,
  database tables, writable runtime paths, and the protected base package.
- Registry discovery now repairs stale `core/base` database state to enabled.
- Reconciled project documentation and shipped manifests with the running
  `0.1.15-dev` core version.

## 0.1.14-dev (unreleased)

### Fixed — protected core package and admin launch controls
- `core/base` is now always reported as a built-in, enabled system component.
  It can no longer be staged, installed, disabled, uninstalled, or purged by
  either the administration screen or a forged lifecycle request.
- Added an **Open site** admin action that launches the public website in a
  new tab while preserving subfolder installations.
- Added the running Website Core version and every enabled industry module's
  version to the persistent administration sidebar.
- Incremented the core base theme and catering module to `0.1.14`.

## 0.1.13-dev (unreleased)

### Added — grouped persistent administration navigation
- Reworked the core admin sidebar into polished, collapsible Overview,
  Website, Design, Administration, and System categories.
- Categories default expanded and remember their open/closed state and sidebar
  scroll position across navigation and page refreshes. Active-page selection
  uses the most specific matching route and remains on the current URL.
- Extended the module API so installed industries register their admin pages
  inside a dedicated package category. Catering now contributes Dashboard,
  Menus, Packages, Leads & inquiries, and Catering settings.
- Incremented the catering module to `0.1.13`.

## 0.1.12-dev (unreleased)

### Added — PHP-owned browser deployment
- Added a self-extracting, branded web-installer build that deploys the full
  catering bundle through the PHP worker. Fresh installations no longer rely
  on control-panel ZIP extraction ownership, allowing `install.php` and
  `install/` to be physically removed after setup on shared hosting.
- The deployer requires HTTPS, PHP 8.4, an empty destination folder, and the
  ZIP extension; validates the embedded bundle checksum and archive paths;
  rejects symlinks; and removes or disables itself before opening setup.
- Incremented the catering module to `0.1.12`.

## 0.1.11-dev (unreleased)

### Fixed — Settings exception and shared-host installer retirement
- Fixed the Settings controller using a nonexistent dependency property while
  reading the configured base URL and license service. Settings now loads
  through the controller's real container instead of showing a logged-reference
  error page.
- Restored automatic shared-host retirement after direct installer deletion:
  split-owned `install.php` and `install/` entries are atomically moved out of
  their canonical public paths, protected from web access, and retried for
  physical erasure on later requests. No FTP credentials are required.
- Incremented the catering module to `0.1.11`.

## 0.1.10-dev (unreleased)

### Fixed — subfolder admin routing and physical installer deletion
- Admin authentication, navigation, forms, media links, module screens, and
  redirects now use the rewrite-independent `index.php?p=` route. A site
  installed under `/catering` therefore remains under `/catering` even when
  nginx ignores `.htaccess` and the physical `admin/` source directory blocks
  clean `/admin` URLs.
- Added a thin `admin/index.php` directory entry that safely redirects
  `/catering/admin/` to `/catering/index.php?p=/admin` without losing the
  installation subfolder.
- Installer cleanup no longer treats renaming an undeletable installer to a
  hidden filename as successful deletion. The website remains locked until
  `install.php` and `install/` are physically gone.
- Readiness now probes mutation access inside every directory in the shipped
  `install/` tree, catching split ownership before database installation.
- Incremented the catering module to `0.1.10`.

## 0.1.9-dev (unreleased)

### Fixed — images and fonts in routed stylesheets
- The core asset server now rebases relative `url(...)` references inside
  package and theme CSS onto the rewrite-independent `index.php?p=/_a/...`
  route. Background images and fonts therefore resolve correctly when nginx
  or a shared-host proxy ignores `.htaccess` clean-path rewrites.
- The transformation preserves external, absolute, fragment, query-only, and
  data URLs and normalizes relative path traversal inside the asset namespace.
- Versioned CSS, JavaScript, image, and font asset URLs invalidate the prior
  24-hour browser cache immediately after a core or industry upgrade.
- Installer readiness now proves that the PHP worker can create, rename, and
  delete files and directories in the document root before installation, with
  clear PHP-FPM ownership guidance when the host blocks self-removal.
- Incremented the catering module to `0.1.9`.

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
