# 0001 — Architecture and stack decisions

Date: 2026-10-14 · Status: accepted

## Context

Reusable commercial website platform for ordinary shared hosting; empty
repository, greenfield build.

## Decisions

- PHP 8.4/8.5 + PDO/MySQL(MariaDB); no framework, small kernel
  (container, router, view, DI). Rejected: Laravel/Symfony (hosting
  assumptions, weight), microservices.
- Single deployable root `site/` with protected private dirs + preferred
  external storage path; `.htaccess` + per-dir deny + asset resolver.
- `addon.json` manifest schema v1 for all package types; lifecycle
  journal in DB; deterministic dependency-ordered boot; boot failures
  quarantined.
- Installer is one page, key-free; ownership via seeded `setup.token` or
  bound-session first-claim (documented limits).
- sqlite driver for dev/test only — native MySQL/MariaDB is the
  acceptance target (CI services).
- Version namespaces: core, extension API major, theme API, package,
  schema — independent.
