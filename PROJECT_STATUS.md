# Project Status

**Current**: `2.0.8` commercial hardening. The planned 0.1.15 through 2.0 core milestones
are implemented in focused, documented commits. The 0.9 gate added cache-safe
defaults, cumulative update metadata, offline reproducible build support, and
a dependency-free release contract check; 1.0 freezes that contract and
produces checksummed installation and update artifacts.

Version 2.0.2 begins the feature-frozen commercial-hardening program recorded
in `docs/COMMERCIAL_HARDENING.md`. Existing surfaces are now completed one at a
time against functional, data, security, UX, visual, accessibility,
operational, and packaged-release gates. The first pass replaces the embedded
developer-style admin shell with a versioned responsive design system and
wires dashboard summaries to their real management workflows. Version 2.0.3
hardens Content and the Page Editor with working no-rewrite filters,
conflict-safe saves, complete revision snapshots, recoverable trash, strict
validation, real draft previews, unsaved-work protection, and a responsive
editor workspace.

Version 2.0.4 closes a maintenance-mode lockout discovered during hosted
testing: the complete admin namespace now bypasses the public maintenance
intercept, so a logged-out `/admin` request can reach the existing auth
middleware and administrator login page. Protected admin screens still
require authentication normally.

Version 2.0.5 completes the brand-asset handoff between industry modules and
Core. A module may supply initial logo/favicon/social files, Core imports them
into its protected Media storage exactly once, and administrators thereafter
own the selections in Website → Appearance. Direct logo/favicon uploads,
previews, the shared admin logo/favicon, and the catering public layout all use
the same Core-owned settings without allowing module updates to overwrite or
repopulate an administrator's choices. The release also completes separate
visual header/footer menu editing and a token-aware custom copyright field;
the required Cajun Web Pro attribution remains separately enforced.

Version 2.0.6 completes the first full cross-surface workflow and presentation
pass: shared admin guidance and icons, Media/Navigation/Forms completion,
searchable audit history, mail testing, webhook recovery, broader mutation
auditing, and polished recovery/update/operations screens. Packaged browser
acceptance on supported shared hosts remains the external release gate.

Version 2.0.7 refines shared brand presentation and the Core-owned social
profile workflow. The administration identity and catering footer now honor
transparent logo artwork without boxed backgrounds. Appearance supplies
fifteen common business networks and one global Header, Footer, or both
placement control, while blank profiles remain absent from public markup.

Version 2.0.8 closes the shared-host file-manager update gap. Module Manager
detects when active package files report a different version than the database,
offers a deliberate Finalize update action, runs pending module migrations,
and preserves the package state. Normal ZIP upgrades now also keep enabled
packages enabled and disabled packages disabled.

The 1.0.1 maintenance release adds an installed-site schema boot gate. A
shared-hosting file-manager upload now applies pending core migrations before
admin services query new tables, preventing the login-succeeds/dashboard-500
failure mode. Error references are also written alongside the matching log
entry in `storage/logs/application.log` when that private path is writable.

The 1.1 release adds a schema-backed visual page composer with eleven core
blocks, drag ordering, hide/show controls, full-width theme rendering,
revision/duplication support, server-side sanitization, and a Module API hook
for industry-owned blocks.

The 1.2 release adds reusable form definitions, a visual field editor, a Form
page block, CSRF and honeypot protection, IP-scoped rate limiting, validation,
consent evidence, a submission inbox, CSV export and queued notifications.

The 1.3 release adds searchable/tagged media organization, bulk moves, focal
point crops and usage inspection. It also replaces raw maintenance and error
output with illustrated, offline-safe status pages and a discrete owner login.

The 1.4 release adds revision-safe reusable layout patterns, header/footer
layout variants and explicit parent-theme fallback for safe child themes.

The 1.5 release adds consent-gated analytics plus a protected privacy-request
intake, admin inbox, completion record and export workflow.

The 1.6 release adds search previews, safe per-page schema, redirect map
portability and automated SEO/accessibility content checks.

The 1.7 release adds translated page variants, localized public URLs,
language-aware navigation and alternate-language metadata.

The 1.8 release adds custom roles, a strict editorial publish capability,
review requests and notes, and side-by-side revision comparison.

The 1.9 release adds scoped API keys, authenticated health/content endpoints,
signed asynchronous webhooks, portable content transfer and optional remote
WebDAV backup replication.

The 2.0 release adds an encrypted agency site registry, remote version/health
checks, signed marketplace feeds, policy-driven managed updates, and a visual
drag-and-drop navigation tree with page-aware links and explicit targets.

The 2.0.1 maintenance release makes that editor unmistakable as **Navigation
Menu** inside the Core-owned Website category. The reusable Module API now lets
each industry module declare its approved starting links. Core imports that
declaration exactly once, takes over the editable stored copy, and never
repopulates a menu that an administrator later clears intentionally.

The 2.0.5 maintenance release applies the same default-then-Core-ownership
model to brand assets. The catering `0.1.21` snapshot supplies its generic
transparent CATERING CO. logo and starting footer navigation, while Core owns
all later branding, footer-link, and copyright changes.

The first industry implementation remains `examples/industry-catering`: a
complete editorial public site, menu/package management, secure multi-step
quote workflow, and catering lead administration. The universal core remains
industry-neutral. Final GitHub CI is authoritative for PHP 8.4/8.5 and native
MySQL/MariaDB evidence; the local release run performs the dependency-free
gate, complete PHP syntax lint, package inspection, and checksums.

## Baseline

- Repository `afterburn25/Website_Core` was **empty, public** at
  2026-10-14; initialized fresh on `main`, integration branch
  `devin/website-core`.
- No prior code imported; all source written for this project.
- Local toolchain: PHP 8.4.25, Composer 2.10.3. No local MySQL — native
  acceptance runs in CI (`mysql:8.4`, `mariadb:11.4` service jobs).

## Milestone evidence

| ID | Item | State | Evidence |
|----|------|-------|----------|
| M0 | Repo baseline, boundaries, architecture docs | done | this repo, `CORE_BOUNDARIES.md`, `docs/` |
| M0 | Runnable bootstrap + CI | done | `site/index.php`, `site/core/Application.php`, `.github/workflows/ci.yml` |
| M1 | Branded one-page installer + claim model | implemented | CajunWebPro UI, required SSL/HTTPS, credential-free private-runtime ownership repair, live green readiness steps, database connection test, ownership-independent protected-core installer with no public cleanup artifacts, valid config+marker completion detection, existing-database reinstall support, and best-effort retirement of legacy installer files in `site/core/Install/Installer.php`, `site/includes/installer-cleanup.php`, `site/includes/runtime-paths.php`, `site/core/Install/views/installer.php`, `site/includes/install-cleanup-gate.php` |
| M1 | Migrations + schema | implemented | `site/database/migrations/2026_10_14_0001_*`, native suite |
| M1 | Admin auth: sessions, CSRF, TOTP MFA, recovery codes, rate limits, last-owner protection | implemented | `site/core/Auth/`, `site/core/Security/`, `tests/Unit/AuthServiceTest.php` |
| M1 | Admin shell: dashboard, settings, users, modules list, basic pages | implemented | `site/admin/` |
| M1 | Core-only public website + base theme | implemented | `site/themes/base/`, public page routes |
| M2 | Content/media/navigation/theme/localization/search metadata | implemented | menus, media library, previews, revisions, sections, redirects, noindex/canonical/OG, HTMLPurifier sanitization, layered i18n (en+fr catalogs, fallback/normalization/placeholders), sitemap/robots |
| M3 | Package API + Module Manager | implemented | archive inspector, lifecycle state machine, journal/lock, migrations on install, dep-ordered enable, per-package contexts, adminRoute/views/service surface, upload/review/action UI, SDK roundtrip test |
| M4 | Updates/backup/recovery/jobs/mail/diagnostics | implemented | BackupService roundtrip + admin UI; health + audit pages; diagnostics bundle download; PHPMailer queue + cron drain + recent-messages health table; Ed25519 signature verifier (sodium_compat polyfill); recovery-mode flag; CoreUpdater + /admin/updates (manifest-hash-verified apply, expires/min_core, maintenance-window gate, pre-update backup, journal, migrations, opcache reset) |
| M5 | Cross-industry proof | implemented + tested | `examples/industry-restaurant` + `examples/industry-salon` (reference modules), `examples/addon-inquiry` (shared add-on), `examples/theme-noir` + `examples/theme-terra` (distinct themes) — verified via `ReferencePackagesTest` and `ReferenceSitesParityTest` (byte-identical add-on ZIP on both sites, parity preserved, theme switch preserves data) |
| M6 | Release | implemented + tested | `docs/SECURITY.md` (passwords, MFA, sessions, CSRF, rate limits, input, headers, accessibility controls), `docs/COMPATIBILITY.md` (PHP 8.4/8.5, MySQL 8.4/MariaDB 11.4, Apache 2.4 mod_rewrite, LiteSpeed pending, no-rewrite fallback), `docs/COMMERCIAL_READINESS.md` (runtime deps, signing trust root, public repo policy, accessibility, performance posture), `docs/RELEASE_CHECKLIST.md` (release steps, verification, signing), AccessibilityTest (7 tests, 31 assertions) — aria-label, for labels, alt text, focus-visible, reduced-motion; release artifacts (FULL-PROJECT/COMPLETE-UPLOAD/UPDATE/packages + SHA256SUMS) built + CI-gated; `tools/verify-artifact.php` fresh-install dogfood; draft GitHub prerelease with 9 assets (unpublished — owner approval) |
| Industry 1 | Catering website | implemented + tested | `examples/industry-catering`, `docs/CATERING_INDUSTRY.md`, `ReferencePackagesTest::testCateringIndustryInstallsRendersAndCapturesQuote`; PHP 8.4/8.5 + MySQL 8.4 + MariaDB 11.4 CI green |

## Remaining gaps (owner-gated)

- Production Ed25519 trust root (`trust.public_keys`) — owner decision;
  no key material in repo.
- Draft prerelease publish — owner approval.
- Real-host compatibility runs outside local/CI environments.
- PR #7 merge approval.
- **Cajunwebpro.com licensing service** — implemented on Cajun_Web `main`
  (validation endpoint, license service, admin/portal UI, auto-fulfilment,
  bundle downloads). CI now proves the full client cycle end-to-end:
  the native leg stages a licensed bundle, runs the real installer over
  HTTP on MySQL/MariaDB against the contract-mock API, then drives a
  cron validation → denial → 503 → reactivation. Remaining: production
  config (`license_core_dir`) and a *live* cajunwebpro.com cycle —
  no real purchase/install/validation has been run yet.

## DRM / licensing (new — commercial extension)

Website Core now supports a DRM licensing model for bundled products:

- **Keyless testing bundles**: `tools/build-packages.php test-bundle
  <industry> [outdir]` produces a clearly labeled TEST-BUNDLE with no
  embedded key and includes production runtime dependencies. The installer
  auto-registers, migrates, and enables it without a license row or API call;
  licensed BUNDLE behavior is unchanged.

- **License keys**: Unique per purchase, embedded in module `addon.json`,
  stored in the `license` table, checked before module operations.
- **Domain binding**: Customer registers domain in the licensing portal;
  validation API checks key + domain; changing a domain deactivates the
  old site. Max 2 active domains per license (public + test). The install
  reports the domain it actually serves (`app.url`) — a site moved to an
  unregistered host self-denies at its next check rather than passing on
  a stale registered domain; `HTTP_HOST` is deliberately untrusted.
- **Validation**: Installer validates license during install against
  `{license.api_url}/validate.php`; periodic revalidation runs via a
  deduped job — hourly while deactivated (fast recovery after a portal
  domain fix), otherwise 24h. **Explicit denial** (deactivated
  license/unregistered domain) blocks public pages with 503 while
  `/admin` stays open; **transient failures** (network/non-200/malformed)
  never deactivate; **successful** validation auto-reactivates.
- **Bundled install**: the installer detects a license-carrying module
  in `site/modules/industries/*/addon.json`, validates its key, records
  the license, registers the package, runs its migrations and marks it
  `enabled` — bundled products work out of the box.
- **Recovery**: `disable()` never requires a valid license — an admin
  can always deactivate a broken/expired package; `install`/`enable`
  remain license-gated.
- **Build tool**: `php tools/build-packages.php bundle <industry>
  <license_key> [outdir]` creates a bundled ZIP with the license key
  embedded in the module manifest.
- **LICENSING_API.md**: documents the Cajunwebpro.com API contract,
  `license.api_url` config, and `WC_LICENSE_API_URL` test override.

## Test status

- `unit`+`package`: 179 tests / 714 assertions green in CI (PHP 8.4.27);
  3 skips are native-DB gated. Sodium tests run via `sodium_compat`.
  AccessibilityTest (7 tests) asserts aria-label, for labels, alt text,
  focus-visible, reduced-motion across admin layout and all 3 themes.
  LicenseValidationTest asserts license storage/status/domain binding;
  LicenseApiContractTest (6 tests) spawns a real `php -S` mock of the
  Cajunwebpro endpoint and verifies the wire contract — well-formed POST,
  explicit denials vs inconclusive failures, reactivation.
- `native`: green in CI on mysql:8.4 and mariadb:11.4 service jobs —
  each leg also exercises the real installer through the SSL proxy contract
  (claim → database test → POST → lock/config/full-cleanup assertions).
- CI: 6 jobs — lint+unit PHP 8.4/8.5, native MySQL, native MariaDB,
  core-only boot, release-artifact contract.
