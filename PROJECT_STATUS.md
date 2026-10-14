# Project Status

**Current**: M0 complete + M1 first increment (installer, identities,
security, usable core-only website). Pre-1.0 — version `0.1.0-dev`.

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
| M2 | Content/media/navigation/theme/localization/search metadata | partial | menus, media library, previews, revisions, sitemap/robots done; sections/locales pending |
| M3 | Package API + Module Manager | implemented | archive inspector, lifecycle state machine, journal/lock, migrations on install, dep-ordered enable, per-package contexts, adminRoute/views/service surface, upload/review/action UI, SDK roundtrip test |
| M4 | Updates/backup/recovery/jobs/mail/diagnostics | implemented | BackupService roundtrip + admin UI; health + audit pages; PHPMailer queue + cron drain; Ed25519 signature verifier on stage; recovery-mode flag; CoreUpdater + /admin/updates (manifest-hash-verified apply, pre-update backup, journal, migrations, opcache reset). Diagnostics bundle + opcache/maintenance-window hardening pending |
| M5 | Reference packages + portability proof | implemented | inquiry add-on + restaurant + salon + terra/noir themes via real Module Manager; ReferenceSitesParityTest proves identical core schema, byte-identical shared add-on, isolated data, theme-switch preservation, uninstall-preserves/purge-removes |
| M4 | Updates, backup/restore, jobs, mail, diagnostics | partial | jobs/outbox schema+runner done |
| M5 | Reference industries + shared add-on proof | pending | |
| M6 | Acceptance matrix + commercial docs + RC | pending | |

## Known gaps this increment

- Module Manager upload/install/update/uninstall UI is not yet built;
  registry + state machine groundwork exists (`ModuleRegistry`,
  `packages`, `package_journal` tables).
- Rich-text sanitization for page bodies pending (needs HTMLPurifier dep).
- Media library, navigation builder, sitemap/robots, preview tokens,
  localization strings — M2 scope.
- Backup/restore, signed updates, SMTP transport — M4 scope.
- SQLite driver exists for dev/test ONLY; production + CI acceptance is
  MySQL/MariaDB (`docs/COMPATIBILITY.md`).

## Test status

- `unit`+`package`: 110 tests green locally (PHP 8.4.25); sodium-gated
  tests run where libsodium exists (CI).
- `native`: green in CI on mysql:8.4 and mariadb:11.4 service jobs.
