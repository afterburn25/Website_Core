# Upgrades (design — implementation is M4)

## Model

Owner-controlled updates for core, modules, and themes via trusted,
signed metadata. Target design:

- Update manifests are signed; sites verify signature + per-file hashes
  before staging. A maintained TUF-style flow is the reference; we do not
  invent cryptography — signing uses libsodium (bundled paragonie/sodium_compat
  fallback on hosts without ext-sodium).
- Metadata carries `expires` and monotonic version — expired or
  rollback-version metadata is rejected.
- Trust roots live in `config.updates.trust_roots` (key rotation supported).
  If no approved production trust root is configured, verification is
  test-only and that gate stays explicit — no test keys ship in releases.
- No GitHub tokens or marketplace accounts on customer sites; trusted
  downloadable/uploaded packages work offline of any directory service.
- Core serving never depends on update-server availability.

## Staging

Update ZIPs extract to `upload/` staging (protected), verified, then
applied over `site/` with an operation journal identical to the package
lifecycle (staged→validated→applied/failed). Files and schema do not share
one transaction — recovery for a half-applied update is the documented
restore path, and the journal records exactly where it stopped.

## Migrations

Ordered, journaled, repeat-safe; additive-first expand/contract where
practical. MySQL DDL commits independently — arbitrary rollback is not
promised; incompatible file/schema combinations require restore, which
the updater detects and demands.

## Preconditions checked

Free space, writable targets, active-request/maintenance window,
opcache behavior (`opcache_reset` where available), DB reachability.
