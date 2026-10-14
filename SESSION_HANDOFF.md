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

**Next**
- M2: media library, navigation builder, revisions UX, preview tokens,
  sitemap/robots, sanitization (HTMLPurifier), localization strings.
- M3: Module Manager upload/lifecycle UI + ZIP safety + SDK scaffold.
- M4: backup/restore, signed updates, SMTP, health/diagnostics.
