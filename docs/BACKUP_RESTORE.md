# Backup & Restore (design — implementation is M4)

## Scope

Full local backup/restore of: database (all core + registered-module
tables), required files (config, keys, themes, modules, uploads),
version/manifest inventory, and job state — encrypted at rest, with
recovery credentials delivered separately from the archive itself.

## Constraints (shared hosting)

- No `mysqldump`, shell, or unbounded request timeouts: bounded,
  resumable browser operations with progress; consistent snapshot via a
  single-transaction dump where possible, else an authenticated
  maintenance window that holds writes.
- Private staging under `paths.storage`/backups; retention + quotas
  enforced; incomplete or unsupported snapshots are rejected loudly —
  never a green check on partial data.
- Backups are protected runtime artifacts — never release assets and
  never web-reachable.

## Restore semantics

- Verified on an isolated installation including module data and the
  original keys. A downloaded ZIP or passing CRC is not acceptance.
- Restores pause outbound jobs/system messages until reconciliation so
  an old payment/email attempt is never replayed as a fresh action.
- **Clone vs disaster-recovery are distinct**: cloning generates a new
  installation identity, salts, keys, and sessions; DR restores keep the
  original identity.
- Failure drills (interruption, disk-full, recovery-mode access) are part
  of the M6 acceptance matrix.
