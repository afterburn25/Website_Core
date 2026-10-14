# Upgrades

Owner-controlled updates for core, modules, and themes. No update server
account, no marketplace token — trusted downloadable/uploaded archives
work fully offline. Core serving never depends on update infrastructure.

## Package updates (implemented)

`PackageManager` handles install-over-install: a staged package whose id
matches an installed one follows the same inspect → install → verify path,
with the operation journaled and serialized under the package-operation
lock. See `docs/MODULE_API.md` and `docs/PACKAGE_SPEC.md`.

## Core self-update (implemented)

Admin → **Updates** (`/admin/updates`, owner-only `system.update`
capability):

1. Upload a `Website-Core-*-UPDATE.zip` produced by
   `tools/build-packages.php`.
2. `Core\Update\CoreUpdater::inspect()` runs the shared archive-safety
   checks (`ArchiveInspector::inspectSafety`), requires
   `includes/bootstrap.php`, reads the target version, and validates the
   embedded `update.json` manifest. The manifest carries `product`,
   `version`, a `files` sha256 map of every payload member, and an
   optional Ed25519 `signature` verified by the same
   `SignatureVerifier`/trust-root config (`trust.public_keys`,
   `trust.allow_unsigned`) as packages.
3. `apply()` re-inspects immediately before writing (review-then-apply
   TOCTOU guard), validates **every** member's hash against the manifest
   in a pre-pass — a corrupt or tampered archive aborts with zero files
   written — then backs up each file it will replace into
   `storage/update-backups/update-<timestamp>.zip`, writes the payload,
   and appends to `storage/core-update-journal.log`.
4. The controller runs pending core migrations (`Migrator`) and records
   `core.update` in the audit log.

An update archive may never write `config/config.php`,
`config/installed.lock`, `storage/`, `upload/`, or installed
modules/themes — those stay site-owned.

## Recovery

- A failed update leaves replaced-file backups in
  `storage/update-backups/`; restoring the pre-update state is: extract
  the backup zip over `site/` + restore the DB backup taken beforehand.
- Full portable restore is documented in `docs/BACKUP_RESTORE.md`.
- A broken optional extension can never lock administrators out: the
  recovery-mode flag boots trusted core only (Modules screen).

## Signing update manifests

Builds ship unsigned unless `WC_UPDATE_SIGN_KEY` (base64 Ed25519 secret)
is set when running `tools/build-packages.php`. Sites enforce signatures
by configuring `trust.public_keys` and `trust.allow_unsigned=false`.

## Not yet implemented (roadmap)

- Automatic opcache reset / maintenance-window enforcement around apply.
- Signed metadata `expires` / anti-rollback monotonic versions (a
  TUF-style flow is the reference target; we do not invent cryptography).
