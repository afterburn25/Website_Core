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
