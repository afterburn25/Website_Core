# Backup & Restore

## Scope (implemented)

A backup is a single ZIP in `storage/backups/` containing:

- `db.sql` — a driver-aware dump of **all** prefixed tables (core and
  package tables alike; statement-bounded, hex-encoded values so user
  data cannot forge delimiters).
- `files/…` — the trees under the restorable roots: `config/` (settings,
  keys), `upload/` (media), `modules/` and `themes/` (installed
  packages). `storage/` is deliberately excluded — backups never contain
  backups.
- `meta.json` — format version, DB driver, table prefix, `db_sha256`,
  per-file SHA-256 manifest, table inventory.

Restore (owner-only, `/admin/backups`) replays `db.sql` after a driver +
prefix match, verifies every embedded file's hash before writing, and
writes files only into the restorable roots. Three traversal guards
apply: member names are validated before `extractTo` (shared
`ArchiveInspector::memberNameOk`), manifest `..`/backslash keys fail
integrity or are rejected, and the write loop refuses paths outside the
roots even with a forged manifest. Manifest paths are forward-slash
normalized at backup time so archives are portable across OSes.

## Constraints (shared hosting)

- No `mysqldump`, shell, or unbounded request timeouts: the dump is a
  bounded per-table SELECT loop; backups/restores run inside a normal
  admin request and stay modest by design.
- Private staging under `paths.storage`; unsupported or incomplete
  snapshots are rejected loudly — never a green check on partial data.
- Backups are protected runtime artifacts — never release assets and
  never web-reachable (`storage/` is denied at the web layer).

## Not yet implemented (honest gaps)

- **Encryption at rest** — backup ZIPs are plaintext under `storage/`.
  Filesystem/deny rules are the boundary; treat the backup dir as
  sensitive.
- **Job/message reconciliation on restore** — a restore does not pause
  the outbound queue; a pre-restore `mail.send`/job can run afterward.
  Until reconcile exists, drain or inspect the queue around DR restores.
- **Clone-vs-DR identity split** — restore currently keeps the original
  identity (config, keys, sessions); cloning into a NEW identity
  (fresh salts/keys) is a future operation.
- **Resumable browser progress** — create/restore are single requests;
  sites large enough to hit request limits need the CLI path or a
  chunked design (future).
