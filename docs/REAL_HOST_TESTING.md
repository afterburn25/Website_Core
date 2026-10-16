# Real-Host Compatibility Testing Checklist

This document provides a step-by-step checklist for verifying Website Core
on actual shared-hosting environments (Apache 2.4 mod_rewrite, LiteSpeed, cPanel
variants). These are owner-gated because they require real hosting credentials.

## Goal

Confirm that:
1. The installer completes successfully on the target host.
2. The no-rewrite fallback (`?p=` / PATH_INFO) works when mod_rewrite is disabled.
3. Cron (web and CLI) functions correctly.
4. Package upload/enable/disable/uninstall cycles work.
5. Backups/restore roundtrip.
6. Updates (via the admin UI) apply cleanly.

## Prerequisites

- Clean hosting account with:
  - PHP 8.4 or 8.5
  - MySQL 8.4+ or MariaDB 11.4+
  - pdo_mysql, mbstring, openssl, json, fileinfo, session, ctype extensions
  - Writable `config/` directory
  - Writable private storage path (preferably outside document root)
- Optional: SMTP credentials for mail testing
- Domain pointing to the public directory where `COMPLETE-UPLOAD` is extracted

## Test Procedure

### 1. Installer verification

1. Extract `Website-Core-VERSION-COMPLETE-UPLOAD.zip` to the public directory.
2. Create `config/setup.token` with a random value (recommended mode):
   ```bash
   echo "random-$(openssl rand -hex 16)" > config/setup.token
   ```
3. Browse to `http://yourdomain.com/` → should redirect to `/install.php`.
4. Fill out all fields:
   - Submit the token (if using seeded-token mode).
   - Database credentials (MySQL/MariaDB host, database name, user, password).
   - Owner username/email/password (12+ chars).
5. Submit → installer should:
   - Run migrations.
   - Create the owner.
   - Write `config/config.php`.
   - Write `config/installed.lock`.
   - Self-disable (delete or rename `install.php`).
6. Browse to `/admin/login` → should 200 (login page renders).
7. Sign in with the owner credentials → should redirect to `/admin/dashboard`.

**Expected result**: clean install, no errors, admin dashboard accessible.

### 2. No-rewrite fallback verification

1. In `.htaccess`, temporarily disable mod_rewrite:
   ```apache
   #RewriteEngine On
   #RewriteRule ^ index.php [L]
   ```
   (Or use a host configuration that does not support mod_rewrite.)
2. Test GET:
   - `http://yourdomain.com/` → should 200 (home page or redirect to `/admin/login` if no content).
   - `http://yourdomain.com/index.php?p=/admin/login` → should 200 (login page).
   - `http://yourdomain.com/index.php/admin/login` → should 200 (PATH_INFO fallback).
3. Test POST:
   - POST to `http://yourdomain.com/index.php?p=/admin/login` with username/password → should 302 redirect to `/admin`.
   - POST to `http://yourdomain.com/index.php/admin/login` (PATH_INFO) → should 302 redirect to `/admin`.
4. Re-enable mod_rewrite in `.htaccess`.
5. Verify normal routing still works.

**Expected result**: all `?p=` and PATH_INFO requests work, mod_rewrite routing still works after re-enable.

### 3. Cron verification

1. In Settings > System, note the cron token (or set one if empty).
2. Web cron:
   - Fetch `http://yourdomain.com/cron.php?token=TOKEN` → should return `processed=X mailed=Y`.
   - Fetch without token → should return 403.
   - Fetch with wrong token → should return 403.
3. CLI cron:
   - Run `php /path/to/site/cron.php` → should output `processed=X mailed=Y`.
4. Create a scheduled job in the host panel (e.g., every 5 minutes) pointing to the CLI command.

**Expected result**: both web and CLI cron drain the job queue and mail queue; token gate enforces 403.

### 4. Package operations

1. Navigate to `/admin/modules`.
2. Upload `Website-Core-Package-inquiry-1.0.0.zip` (from the draft release).
3. Click "Review" → verify manifest details are shown.
4. Click "Install" → should move to "installed".
5. Navigate to `/admin/content` → verify "inquiry" content type appears.
6. Navigate to `/admin/modules` → disable the add-on → should move to "disabled".
7. Re-enable → should move back to "installed".
8. Upload a fresh copy → verify "staged" → "install" → "installed" cycle.
9. Purge (if offered) → verify removal.

**Expected result**: package lifecycle works through stage/install/enable/disable/uninstall/purge.

### 5. Self-service password reset

1. While logged out, browse to `/admin/forgot`.
2. Enter the owner email (or username).
3. Click "Send reset link" → should show "If that account exists, a reset link is on its way."
4. Retrieve the email (from mail queue or SMTP inbox).
5. Click the reset link → should show the new password form.
6. Enter a new password (12+ chars, repeat).
7. Submit → should show "Password updated. Sign in with your new password."
8. Sign in with the new password → should succeed.

**Expected result**: self-service reset works, old password rejected, sessions revoked.

### 6. Backup/restore roundtrip

1. Navigate to `/admin/backups`.
2. Click "Create backup now" → should list a new backup.
3. Download the backup ZIP.
4. Make a visible change (create a page, modify settings).
5. Navigate to `/admin/backups` → select the backup → click "Restore".
6. Confirm → should restore the database and files (except config/installed.lock/storage/upload/installed packages).
7. Verify the change is reverted (page/settings state pre-backup).

**Expected result**: backup captures state, restore reverts it (with the documented exceptions).

### 7. Update application (test on staging, not production)

⚠️ **Do not run this on production until the signing trust root is established.**

1. In Settings > System, copy the cron token (if you'll use web cron).
2. In Settings > System, under Updates, download the current `Website-Core-VERSION-UPDATE.zip` from the draft release.
3. Upload it → review the manifest (version, files, signed status).
4. Click "Apply" → should:
   - Validate integrity.
   - Create a backup in `storage/update-backups/`.
   - Replace core files.
   - Run migrations.
   - Clean opcache.
5. Verify the site loads and admin works.

**Expected result**: clean core-only update, settings/content/packages preserved.

## Host-Specific Checks

### Apache 2.4 mod_rewrite

- Verify `.htaccess` rewrite rules are honored (Apache configuration must allow `AllowOverride All` in the site directory).
- Verify `Options -Indexes` blocks directory listing.
- Verify per-directory `.htaccess` deny files (admin, config, storage, upload, vendor) are respected.

### LiteSpeed

- Verify rewrite rules work (LiteSpeed mod_rewrite emulation is generally compatible).
- Verify PHP runs under the selected PHP version (LSAPI or mod_php).
- Verify `display_errors` is off (check `.htaccess` `mod_php` section or server config).
- **Pending**: actual LiteSpeed host run to confirm no quirks.

### cPanel variants

- Verify file permissions (owner/group writeable for config, storage, upload).
- Verify PHP extensions are enabled (check MultiPHP selector or EasyApache).
- Verify database socket/network access (localhost vs socket).

## Failure Reporting

Document any failures with:
- Host provider and panel type (cPanel, Plesk, DirectAdmin, custom).
- PHP version and extensions (`php -v`, `php -m`).
- Database version and connection mode (socket vs TCP).
- Exact error message or HTTP status code.
- `.htaccess` allow-override setting (if accessible).

This checklist provides evidence for the compatibility claims in `docs/COMPATIBILITY.md`.
