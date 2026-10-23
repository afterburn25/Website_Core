# Installation

## Flow

1. Extract COMPLETE-UPLOAD into the website directory (contents land at the
   web root; `site/` internal layout is the deployment layout).
2. Browse to `/` → redirected to `/install.php`.
3. The branded one-page setup checks PHP/extensions, writable paths, and
   required SSL/HTTPS. Enter MySQL/MariaDB credentials and use **Test
   connection** before installing. If the extracted files have foreign
   ownership, the installer reveals its automatic local FTP repair fields.
4. The installer verifies checks, connects to MySQL, runs migrations,
   creates the owner, writes `config/config.php` with generated secrets,
   writes `installed.lock`, then removes `install/` and `install.php`.
5. Sign in at `/admin`, enable MFA in **Profile**.

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
  random value to `config/setup.token` (host panel, deploy script, FTP).
  The form then requires that value on submit. Delete the file after
  install (installer removes it too).
- **Bound-session claim** — if no token exists, the first visitor to
  `install.php` claims setup: `config/claim.lock` is created atomically and
  bound to that browser's session cookie. Other sessions see a 409 and the
  documented recovery (remove the claim files via the host file manager).
  The page warns clearly that an unclaimed public upload is claimable by
  the first visitor — the remedy is host protection or a seeded token.

Concurrency: atomic file creation, unique owner constraint, and the lock
prevent duplicate initialization under simultaneous submissions.

## Requirements (verified by the installer)

PHP 8.4+; extensions pdo_mysql, mbstring, openssl, json, fileinfo,
session, ctype (gd/intl/zip/curl recommended); writable `config/` and a
writable private storage path (preferably outside the document root);
MySQL/MariaDB with schema privileges; UTF-8/utf8mb4; and a valid SSL
certificate with the installer accessed over HTTPS.

Real create/write/rename/delete probes are used — not just `is_writable`.
The installer creates missing `config/` and `storage/` directories and tries
safe owner/group permission repairs when the hosting account permits it. When
an uploaded archive belongs to a different filesystem owner, PHP's FTP
extension activates an automatic fallback: credentials are sent only to
`127.0.0.1`, used for the current request, never stored, and can be verified
with **Test hosting access** before installation. The fallback detects this
specific website using packaged fingerprints, provisions with 0750/0775
permissions, writes and verifies the configuration, and retries installer
cleanup through the hosting account. It never uses chmod 0777. Long absolute
paths wrap inside the readiness cards rather than overflowing the setup panel.

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
  success.
- After successful setup, the installer recursively removes only the shipped
  `install/` directory and the root `install.php` entry file. It never deletes
  unrelated files.
- Cleanup is fail-closed. If PHP cannot remove either installer artifact, all
  public, admin, and cron entry points return 503. Open DirectAdmin File
  Manager or FTP, delete `install/` and `install.php` from the website root,
  then reload; the site unlocks automatically. Do not delete
  `config/installed.lock` or `config/cleanup-required.lock`.
- `config.php`, `claim.*`, `cleanup-required.lock`, and `installed.lock` are
  gitignored and excluded from release packages.

## Post-install

- Set `paths.storage` outside the document root where the host permits.
- Configure SMTP under Settings → Mail (or keep `capture` transport during
  evaluation — see UPGRADES/BACKUP docs for limits of capture mode).
- Schedule `php cron.php` (or `cron.php?token=…` with the token shown in
  Settings → System) every few minutes for jobs/mail.
