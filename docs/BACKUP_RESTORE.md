# Backup & Restore

## Scope (implemented)

A backup is a single ZIP in `storage/backups/` containing:

- `db.sql` — a driver-aware dump of **all** prefixed tables (core and
  package tables alike; statement-bounded, hex-encoded values so user
  data cannot forge delimiters).
- `files/…` — the trees under the restorable roots: `config/` (settings,
  keys), `upload/`, protected `storage/media`, `modules/`, and `themes/`.
  `storage/media` follows configured `paths.storage`, including a protected
  directory outside the public deployment root. Other storage paths are deliberately excluded — backups never contain
  backups, locks, caches, or staged updates.
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

## What “Verified” actually proves (commercial acceptance hardening)

Starting with the backup-integrity hardening on the 2.6.2 release branch,
`BackupService::inspect()` checks the stored `db.sql` checksum **and
streams every protected file directly out of the finalized ZIP**, verifying
each against its `meta.json` SHA-256. Missing members, unlisted payload
files, malformed or unsafe manifest paths, and checksum mismatches are
rejected instead of appearing as a green recovery point. This inspection
occurs when the Backups page lists archives, before owner-only downloads,
before restores, and when a newly created backup is finalized. If a fresh
snapshot cannot pass read-back verification, it is not reported as created
and verified.

**Verification is not a restore drill.** A fully checksummed local ZIP is
not proof that PHP-FPM can rewrite an existing deployment, recover a
database on a new server, restart the app, reconcile email/jobs, or that
the archive exists independently offsite. Restore to a disposable isolated
clone with matching driver/prefix and disabled mail/cron/live payments,
prove representative content/media, then record evidence. The archive
includes configuration and secrets, so store any offsite copy privately;
never upload it to a public repository or untrusted file host.

## Explicit module purge data protection

Uninstall removes module files but **preserves** module data. A separate
operator-confirmed purge is irreversible. Its table discovery matches the
**literal** configured database prefix and module-owned table prefix:
underscores and percent signs must not expand as SQL `LIKE` wildcards and
must never match another module's or another namespace's tables. This
contract is enforced by SQLite and native MySQL/MariaDB regression tests,
but customer-host backups and authorized restore evidence remain required
before a real production purge.

## Symbolic-link safety

A backup must remain inside its declared, operator-configured roots. Core now
**rejects** a backup when a selected root or an included file/directory is a
symbolic link; it does not dereference such links into unrelated filesystem
trees or silently omit them. The backup manifest hashes the copied staging
bytes rather than an original that might change during the copy.

A restore verifies every path component from its configured restore root
down to the destination file before importing SQL, then checks again before
copying. A symlinked destination or parent directory **fails closed**, even
if the linked file happens to match the expected checksum. On shared hosts
using symlinks for controlled configuration, replace them with supported
regular directories or obtain a separate explicitly reviewed backup plan;
never bypass these checks to complete an upgrade.

## Constraints (shared hosting)

- No `mysqldump`, shell, or unbounded request timeouts: the dump is a
  bounded per-table SELECT loop. Manual backups/restores run in an admin
  request; automatic backups run as bounded cron jobs.
- Restore automatically creates a pre-restore safety snapshot, enables
  maintenance mode for the restore window, resets OPcache, and then returns
  the site to its prior maintenance state.
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
