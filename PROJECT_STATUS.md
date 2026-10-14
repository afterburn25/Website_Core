# Project Status

**Current**: M0–M5 implemented (installer, identities/security, content,
packages, ops/updates, reference packages + parity proof) with release
artifacts and CI gating. Pre-1.0 — version `0.1.0-dev`; remaining items
are owner-gated, not engineering.

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
| M5 | Reference packages + portability proof | implemented | inquiry add-on + restaurant + salon + terra/noir themes via real Module Manager; ReferenceSitesParityTest proves identical core schema, byte-identical shared add-on, isolated data, theme-switch preservation, uninstall-preserves/purge-removes |
| M6 | Acceptance matrix + commercial docs + RC | evidence built | release artifacts (FULL-PROJECT/COMPLETE-UPLOAD/UPDATE/packages + SHA256SUMS) built + CI-gated; `tools/verify-artifact.php` fresh-install dogfood; draft GitHub prerelease with 9 assets (unpublished — owner approval) |

## Remaining gaps (owner-gated)

- Production Ed25519 trust root (`trust.public_keys`) — owner decision;
  no key material in repo.
- Draft prerelease publish — owner approval.
- Real-host compatibility runs outside local/CI environments.
- PR #7 merge approval.

## Test status

- `unit`+`package`: 130 tests / 504 assertions green locally (PHP 8.4.25);
  3 skips are native-DB gated. Sodium tests run via `sodium_compat`.
- `native`: green in CI on mysql:8.4 and mariadb:11.4 service jobs.
- CI: 6 jobs — lint+unit PHP 8.4/8.5, native MySQL, native MariaDB,
  core-only boot, release-artifact contract.
