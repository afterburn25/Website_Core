# Changelog

## 0.1.0-dev (unreleased)

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
