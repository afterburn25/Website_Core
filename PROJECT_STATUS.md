# Project Status

**Current**: M0–M6 implemented and hardened — installer, identities/security,
content, packages, ops/updates, reference packages + parity proof, accessibility
and performance controls, with release artifacts and CI gating. 149 tests /
582 assertions; all layers audited. Pre-1.0 — version `0.1.0-dev`; remaining
items are owner-gated, not engineering.

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
| M1 | One-page installer + claim model | implemented | `site/install/Installer.php`, `site/install/views/installer.php` |
| M1 | Migrations + schema | implemented | `site/database/migrations/2026_10_14_0001_*`, native suite |
| M1 | Admin auth: sessions, CSRF, TOTP MFA, recovery codes, rate limits, last-owner protection | implemented | `site/core/Auth/`, `site/core/Security/`, `tests/Unit/AuthServiceTest.php` |
| M1 | Admin shell: dashboard, settings, users, modules list, basic pages | implemented | `site/admin/` |
| M1 | Core-only public website + base theme | implemented | `site/themes/base/`, public page routes |
| M2 | Content/media/navigation/theme/localization/search metadata | implemented | menus, media library, previews, revisions, sections, redirects, noindex/canonical/OG, HTMLPurifier sanitization, layered i18n (en+fr catalogs, fallback/normalization/placeholders), sitemap/robots |
| M3 | Package API + Module Manager | implemented | archive inspector, lifecycle state machine, journal/lock, migrations on install, dep-ordered enable, per-package contexts, adminRoute/views/service surface, upload/review/action UI, SDK roundtrip test |
| M4 | Updates/backup/recovery/jobs/mail/diagnostics | implemented | BackupService roundtrip + admin UI; health + audit pages; diagnostics bundle download; PHPMailer queue + cron drain + recent-messages health table; Ed25519 signature verifier (sodium_compat polyfill); recovery-mode flag; CoreUpdater + /admin/updates (manifest-hash-verified apply, expires/min_core, maintenance-window gate, pre-update backup, journal, migrations, opcache reset) |
| M5 | Cross-industry proof | implemented + tested | `examples/industry-restaurant` + `examples/industry-salon` (reference modules), `examples/addon-inquiry` (shared add-on), `examples/theme-noir` + `examples/theme-terra` (distinct themes) — verified via `ReferencePackagesTest` and `ReferenceSitesParityTest` (byte-identical add-on ZIP on both sites, parity preserved, theme switch preserves data) |
| M6 | Release | implemented + tested | `docs/SECURITY.md` (passwords, MFA, sessions, CSRF, rate limits, input, headers, accessibility controls), `docs/COMPATIBILITY.md` (PHP 8.4/8.5, MySQL 8.4/MariaDB 11.4, Apache 2.4 mod_rewrite, LiteSpeed pending, no-rewrite fallback), `docs/COMMERCIAL_READINESS.md` (runtime deps, signing trust root, public repo policy, accessibility, performance posture), `docs/RELEASE_CHECKLIST.md` (release steps, verification, signing), AccessibilityTest (7 tests, 31 assertions) — aria-label, for labels, alt text, focus-visible, reduced-motion; release artifacts (FULL-PROJECT/COMPLETE-UPLOAD/UPDATE/packages + SHA256SUMS) built + CI-gated; `tools/verify-artifact.php` fresh-install dogfood; draft GitHub prerelease with 9 assets (unpublished — owner approval) |

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

- `unit`+`package`: 167 tests / 649 assertions green locally (PHP 8.4.25);
  3 skips are native-DB gated. Sodium tests run via `sodium_compat`.
  AccessibilityTest (7 tests) asserts aria-label, for labels, alt text,
  focus-visible, reduced-motion across admin layout and all 3 themes.
  LicenseValidationTest asserts license storage/status/domain binding;
  LicenseApiContractTest (6 tests) spawns a real `php -S` mock of the
  Cajunwebpro endpoint and verifies the wire contract — well-formed POST,
  explicit denials vs inconclusive failures, reactivation.
- `native`: green in CI on mysql:8.4 and mariadb:11.4 service jobs —
  each leg also exercises the real installer over HTTP (claim → POST →
  lock/config/self-disable assertions).
- CI: 6 jobs — lint+unit PHP 8.4/8.5, native MySQL, native MariaDB,
  core-only boot, release-artifact contract.
