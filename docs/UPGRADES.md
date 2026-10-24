# Upgrades

Owner-controlled updates for core, modules, and themes. No update server
account, no marketplace token — trusted downloadable/uploaded archives
work fully offline. Core serving never depends on update infrastructure.

## Package updates (implemented)

`PackageManager` handles install-over-install: a staged package whose id
matches an installed one follows the same inspect → install → verify path,
with the operation journaled and serialized under the package-operation
lock. See `docs/MODULE_API.md` and `docs/PACKAGE_SPEC.md`.

When an administrator has already replaced a package directory through a
shared-host file manager, Module Manager compares the active disk manifest
with the registered database manifest. A version mismatch produces a
**Finalize update** action. Finalizing validates the disk package, checks its
license when applicable, runs pending migrations, synchronizes the stored
manifest/version, resets OPcache, and preserves the existing enabled,
disabled, or installed state. This is a recovery workflow; normal updates
should still use Upload & stage so Core can validate before files become live.

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
   `trust.allow_unsigned`) as packages. `inspect()` also refuses a
   manifest whose declared `version` disagrees with the bundled
   bootstrap, and any archive older than the running core — the rollback
   floor; intentional recovery goes through backup restore, not an
   update artifact.
3. `apply()` re-inspects immediately before writing (review-then-apply
   TOCTOU guard), validates **every** member's hash against the manifest
   in a pre-pass — a corrupt or tampered archive aborts with zero files
   written — then backs up each file it will replace into
   `storage/update-backups/update-<timestamp>.zip`, writes the payload,
   and appends to `storage/core-update-journal.log`.
4. The controller runs pending core migrations (`Migrator`) and records
   `core.update` in the audit log.

The 1.0 update is cumulative from `0.1.14-dev` and includes migrations 0009
(admin notices), 0010 (publishing/trash/menu workflow), and 0011 (SEO 404
insights). The build manifest declares that real source floor rather than the
target version, so an older supported installation can pass inspection.
Release operators can intentionally raise the floor with
`WC_UPDATE_MIN_CORE=<version>`.

Maintenance windows are owner-driven: creating
`storage/maintenance.flag` returns 503 + `Retry-After` for every
non-admin request while it exists (authenticated operators keep
access), so an apply never swaps code underneath public traffic.
Setting `updates.require_window` additionally makes the apply step
refuse until the flag exists — enforcing the workflow, not just
honoring it.

An update archive may never write `config/config.php`,
`config/installed.lock`, `storage/`, `upload/`, or installed
modules/themes — those stay site-owned.

If the installed Core itself was only partially copied and the Updates route
is unavailable, use the matching per-industry `REPAIR-UPLOAD` artifact. It is
extracted at the installed web root, includes the complete Core runtime and
industry files at their live path, and excludes installer entry files and
runtime site data. Module Manager must then finalize the detected industry
version mismatch.

## Recovery

- A failed update leaves replaced-file backups in
  `storage/update-backups/`; restoring the pre-update state is: extract
  the backup zip over `site/` + restore the DB backup taken beforehand.
- Full portable restore is documented in `docs/BACKUP_RESTORE.md`.
- A broken optional extension can never lock administrators out: the
  recovery-mode flag boots trusted core only (Modules screen).

## Signing update manifests

Builds ship unsigned unless `WC_UPDATE_SIGN_KEY` (base64 Ed25519 secret)
is set when running `tools/build-packages.php`. Installed sites default
to `trust.allow_unsigned=false`: an unsigned build refuses until the
owner configures `trust.public_keys` for signed builds, or explicitly
opts back into unsigned artifacts (development posture — the shipped
`config.example.php` keeps `allow_unsigned=true` for local use).

For an offline/reproducible build, `WC_BUILD_VENDOR_DIR` may point to a
previously verified production-only Composer vendor tree. The builder requires
both `autoload.php` and Composer installed metadata before it will use it.

## Managed signed feeds (2.0)

Owner-only **Marketplace** accepts public HTTPS catalog URLs. Catalog JSON is
accepted only when its Ed25519 signature matches `trust.public_keys`;
unsigned catalogs are never accepted, even on development installations.
Every listed update also carries a SHA-256 digest, and the downloaded archive
must independently pass `CoreUpdater` signature, manifest and member checks.

Automation policies are stored in `updates.automation`: `off`,
`stage-security`, `stage-all`, `apply-security`, or `apply-all`. Staging leaves
the verified archive for owner review. Apply policies first create a complete
site backup, enable maintenance, apply atomically, run migrations, clear
opcache and journal the result. Cron checks are deduplicated and no more often
than every twelve hours.

## Future hardening

- A stronger TUF-style signed-metadata chain is a reference target; the
  shipped floor already refuses older archives, expired manifests,
  incompatible `min_core`, and manifest/bootstrap version mismatches —
  we do not invent cryptography beyond that.
