# Installation

## Flow

1. For shared hosting, place the generated CajunWebPro web-installer PHP file
   by itself in the empty destination directory and open it through HTTPS.
   The launcher verifies and extracts the embedded COMPLETE-UPLOAD payload as
   the PHP worker, then removes itself. Direct control-panel ZIP extraction is
   still supported when the panel and PHP worker use the same filesystem owner.

Build the ownership-safe launcher from a completed testing bundle with:

```bash
php tools/build-web-installer.php \
  dist/Website-Core-catering-VERSION-TEST-BUNDLE.zip \
  dist/CajunWebPro-Catering-VERSION-Web-Installer.php
```

2. Browse to `/install.php` (or `/`) → the protected-core installer opens.
3. The branded one-page setup checks PHP/extensions, writable paths, and
   required SSL/HTTPS. Enter MySQL/MariaDB credentials and use **Test
   connection** before installing. The connection test reports whether an
   existing Website Core schema was found; reusing it requires a separate,
   explicit confirmation. If the extracted files have foreign
   ownership, the installer automatically provisions a protected private
   runtime directory without asking the customer for hosting credentials.
4. The installer verifies checks, connects to MySQL, runs migrations,
   creates the owner, writes `config/config.php` with generated secrets,
   writes `installed.lock`, and removes the public `install.php` file and
   `install/` directory. When shared-host ownership blocks direct deletion,
   the installer atomically retires those exact public paths into protected
   storage and keeps retrying physical erasure. If even safe retirement is
   blocked, the completion screen gives exact File Manager instructions and a
   **Recheck cleanup and open the website** action while the validated config
   and permanent marker keep the installer disabled. The completion screen
   also provides separate **Visit website** and **Open admin** actions.
5. Sign in through `/index.php?p=/admin`, enable MFA in **Profile**.

The friendly `/admin/` directory entry redirects to that rewrite-independent
route while preserving a subfolder such as `/catering`. All admin links and
forms continue using `index.php?p=` so nginx hosts do not require rewrite rules.

No license keys, unlock files, wizards, or merchant credentials for a
core-only install. **Bundled products** (core + industry module purchased
from Cajunwebpro.com) carry an embedded license key — see below.

## Keyless testing bundles

For development and customer testing, build an explicitly labeled bundle:

```bash
php tools/build-packages.php test-bundle <industry> [outdir]
```

The resulting `*-TEST-BUNDLE.zip` contains the core and selected industry
module plus production runtime dependencies, but deliberately omits
`license_key`. The installer still detects,
migrates, registers, and enables the industry module. No license row is
created, no licensing API request is made, and periodic license enforcement
remains inactive. This testing path does not weaken licensed `*-BUNDLE.zip`
files: any bundle containing `license_key` follows the normal validation flow.
The TEST-BUNDLE archive has deployable files at its root, so extract its
contents directly into the test domain's document root and open the domain.

## Bundled products (license-validated)

Bundles ship the industry module pre-extracted at
`site/modules/industries/<name>/` with a `license_key` in `addon.json`.

- The checks page reports the detected bundle (informational — a core-only
  install remains valid).
- On submit the installer extracts the domain from the Base URL,
  validates `{key, domain}` against the licensing API
  (`{license.api_url}/validate.php`, default
  `https://cajunwebpro.com/api`), and blocks installation when the server
  denies it. The domain must be registered to the license in the
  Cajunwebpro client portal first.
- On success the installer stores the license, registers the package,
  runs the module's migrations, and marks it enabled — no manual
  activation step.
- The `WC_LICENSE_API_URL` environment variable overrides the API base
  for install-time validation and the generated `license.api_url`
  config (used by tests/mocks).
- The installed site re-validates via the cron job runner — daily while
  valid, hourly while deactivated (schedule `cron.php` per Post-install
  below). Explicit server denial takes public pages offline (503) while
  `/admin` stays reachable for correction; transient outages never
  deactivate and never clear the stored verdict. Settings → License →
  "Validate now" re-checks on demand and reactivates a fixed site
  immediately without waiting for cron.
- **Moving a licensed site**: the site reports the domain it actually
  serves (its `app.url`, not the raw request host). To move it, replace
  the domain in the Cajunwebpro client portal (the old site
  auto-deactivates at its next check), then set **Settings → Base URL**
  on the new install and run "Validate now" — no `config.php` edit
  needed. The site refuses to run on an unregistered domain.

## Ownership claim model (no install key)

Two supported modes:

- **Seeded token (recommended for public uploads)** — the deployer writes a
  random value to `config/setup.token` (host panel or deployment script).
  The form then requires that value on submit. Delete the file after
  install (installer removes it too).
- **Bound-session claim** — if no token exists, the first visitor to
  the root installer claims setup: `config/claim.lock` is created atomically and
  bound to that browser's session cookie. Other sessions see a 409 and the
  documented recovery (remove the claim files via the host file manager).
  The page warns clearly that an unclaimed public upload is claimable by
  the first visitor — the remedy is host protection or a seeded token.

Concurrency: atomic file creation, a non-blocking installation operation lock,
the unique owner constraint, and a setup-specific CSRF token prevent duplicate
or cross-site initialization under simultaneous submissions.

## Requirements (verified by the installer)

PHP 8.4+; extensions pdo_mysql, mbstring, openssl, json, fileinfo,
session, ctype (gd/intl/zip/curl recommended); writable `config/` and a
writable private storage path (preferably outside the document root);
MySQL/MariaDB with schema privileges; UTF-8/utf8mb4; and a valid SSL
certificate with the installer accessed over HTTPS.

Real create/write/rename/delete probes are used — not just `is_writable`.
The installer creates missing `config/` and `storage/` directories and tries
safe owner/group permission repairs when the hosting account permits it. When
an uploaded archive belongs to a different filesystem owner, the installer
automatically creates a deterministic mode-0700 runtime directory outside the
public document root. Generated configuration, installation locks, and private
storage live there; the bootstrap and cleanup gate locate it automatically on
later requests. Temporary directories, public paths, symlinks, and locations
that fail real create/read/rename/delete probes are rejected. No FTP or hosting
credentials are requested, and chmod 0777 is never used. Long absolute paths
wrap inside readiness cards rather than overflowing the setup panel.

Installer self-removal has a separate advisory probe in the public document
root. It verifies that PHP can create, rename, and delete root entries and
install the managed access-deny rules used by the protected retirement
fallback. A failed probe appears as a yellow warning and does not block setup.
The completed configuration and permanent installation marker disable setup;
if PHP cannot remove `install.php` or `install/`, the completion screen asks
the operator to delete them with the hosting File Manager while the installed
website remains available. Do not work around ownership problems with mode
0777.

An existing Website Core database is supported for reinstall/recovery. The
installer applies only pending migrations, creates or refreshes the submitted
owner login, preserves an existing home page and cron token, and idempotently
updates bundled package/license state. The protected reinstall must be
explicitly confirmed after the database test, and a recoverable existing app
key is retained so encrypted integration credentials remain readable. Existing database tables alone never
mark the files as installed; completion requires both a structurally valid
generated `config.php` and `installed.lock`.

## Deployment variants

- **Root domain** and **subfolder** are both supported — the request layer
  derives the base path from SCRIPT_NAME; all generated URLs include it.
- **Apache rewrite** (`.htaccess` ships) and a **no-rewrite fallback**
  (`index.php?p=/path`) are supported. LiteSpeed honors the same rules.
- The installer does not judge host compatibility from CLI checks alone —
  everything runs in the web SAPI.

## Failure & recovery

- Failed setup is resumable: re-open the page, fix inputs, resubmit. No
  destructive reinstall; `installed.lock` + config are only written on
  success. Configuration is verified in a temporary file and atomically
  committed; a failed write does not truncate the previous config.
- Deployment packages contain a thin public `install.php` entry and protected
  `install/` marker directory; the implementation and view remain under
  `core/Install/`. Successful setup deletes both public artifacts directly
  when possible, otherwise atomically retires the canonical paths.
- Every later request repeats exact cleanup when a valid generated config plus
  `installed.lock` prove installation completed and sweeps any protected
  retirement residue. If the host blocks both deletion and safe retirement,
  the site remains installed, setup remains permanently disabled, and the
  advisory instructions direct the operator to the hosting File Manager.
- A retired entry is moved out of the canonical installer URL, randomized,
  denied by web-server rules, and repeatedly targeted for physical deletion.
- Cleanup never scans or deletes unrelated files, never uses FTP or 0777, and
  never runs based on database tables alone.
- `config.php`, `claim.*`, `cleanup-required.lock`, and `installed.lock` are
  gitignored and excluded from release packages.

## Post-install

- Set `paths.storage` outside the document root where the host permits.
- Configure SMTP under Settings → Mail (or keep `capture` transport during
  evaluation — see UPGRADES/BACKUP docs for limits of capture mode).
- Schedule `php cron.php` (or `cron.php?token=…` with the token shown in
  Settings → System) every few minutes for jobs/mail.
