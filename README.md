# Website Core

A reusable PHP website platform for ordinary shared hosting:

**UNIVERSAL CORE + INDUSTRY MODULES + OPTIONAL ADD-ONS + INDEPENDENT THEMES**

The core installs a complete business-information website — editable pages
and navigation, business identity, media/files, an independent theme, secure
administration, system recovery, and module management — with **no industry
features baked in**. Industry packages (e.g. restaurant, salon), reusable
add-ons (e.g. inquiry forms), and themes are separately versioned packages
installed through the Module Manager.

- **Runtime**: PHP 8.4/8.5, native MySQL/MariaDB via PDO. No Node, Redis,
  Docker, daemons, or SSH required on the customer host. Ed25519
  verification runs on ext-sodium or the bundled sodium_compat polyfill.
  No-rewrite fallback (Apache 2.4 mod_rewrite or LiteSpeed; hosts without
  rewrite use `?p=/path` PATH_INFO). Admin extras: Backups, Health +
  diagnostics, owner-only self-update at `/admin/updates`, self-service
  password reset at `/admin/forgot`.
- **Commercial bundles**: `tools/build-packages.php bundle` ships core +
  an industry module as one ZIP with an embedded `license_key`; the
  installer validates it against the licensing API (`docs/LICENSING_API.md`),
  revalidates on schedule, and a denied license takes public pages offline
  while `/admin` stays reachable — core-only installs need no license.
- **Layout**: `site/` is the deployable web root (extract COMPLETE-UPLOAD
  there). `docs/`, `tests/`, `tools/`, `sdk/`, `examples/` are development
  material and never ship in production uploads.
- **Status**: `1.8.0`. The stable core now includes visual composition,
  universal forms, production media workflows, custom roles and an editorial
  approval flow. See `PROJECT_STATUS.md`, `docs/ROADMAP.md`, and
  `docs/1.8_RELEASE_NOTES.md`.

## Quick start (development)

```bash
composer install
php tools/release-gate.php
php tools/lint.php
vendor/bin/phpunit --testsuite unit     # unit tests (no DB needed)
vendor/bin/phpunit --testsuite package  # package/manifest tests

# Native MySQL acceptance (requires a server):
WC_TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=wc_test" \
WC_TEST_MYSQL_USER=root WC_TEST_MYSQL_PASS=replace-with-test-db-password \
vendor/bin/phpunit --testsuite native

# Dev server (uses tools/dev-router.php, an .htaccess mimic):
cd site && php -S 127.0.0.1:8000 ../tools/dev-router.php
# then open http://127.0.0.1:8000/ → redirects to the installer

# Release artifacts + verification:
php tools/build-packages.php          # dist/: FULL-PROJECT / COMPLETE-UPLOAD /
                                      # UPDATE / package zips / SHA256SUMS
php tools/verify-artifact.php dist/Website-Core-*-COMPLETE-UPLOAD.zip
# extracts the artifact to a clean dir, provisions it, prints serve steps
php tools/build-web-installer.php \
  dist/Website-Core-catering-VERSION-TEST-BUNDLE.zip \
  dist/CajunWebPro-Catering-VERSION-Web-Installer.php
```

## Key documents

| File | Purpose |
|---|---|
| `CORE_BOUNDARIES.md` | What may and may not live in core |
| `docs/ARCHITECTURE.md` | Kernel, container, routing, module API design |
| `docs/INSTALLATION.md` | Installer + ownership claim model |
| `docs/1.0_RELEASE_NOTES.md` | Stable release contents and upgrade notes |
| `docs/MODULE_API.md` | Supported extension API surface |
| `docs/PACKAGE_SPEC.md` | addon.json manifest schema v1 |
| `docs/LICENSING_API.md` | Bundle/DRM licensing contract |
| `docs/UPGRADES.md` / `docs/BACKUP_RESTORE.md` | Update & DR design |
| `docs/SECURITY.md` / `docs/THREAT_MODEL.md` | Security policy & model |
| `SESSION_HANDOFF.md` | Session-to-session work log |
