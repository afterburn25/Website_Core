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
  Docker, daemons, or SSH required on the customer host.
- **Layout**: `site/` is the deployable web root (extract COMPLETE-UPLOAD
  there). `docs/`, `tests/`, `tools/`, `sdk/`, `examples/` are development
  material and never ship in production uploads.
- **Status**: pre-1.0 development. See `PROJECT_STATUS.md` for the honest
  milestone state and `docs/ROADMAP.md` for the acceptance sequence.

## Quick start (development)

```bash
composer install
php tools/lint.php
vendor/bin/phpunit --testsuite unit     # unit tests (no DB needed)
vendor/bin/phpunit --testsuite package  # package/manifest tests

# Native MySQL acceptance (requires a server):
WC_TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=wc_test" \
WC_TEST_MYSQL_USER=root WC_TEST_MYSQL_PASS=secret \
vendor/bin/phpunit --testsuite native

# Dev server (uses tools/dev-router.php, an .htaccess mimic):
cd site && php -S 127.0.0.1:8000 ../tools/dev-router.php
# then open http://127.0.0.1:8000/ → redirects to the installer
```

## Key documents

| File | Purpose |
|---|---|
| `CORE_BOUNDARIES.md` | What may and may not live in core |
| `docs/ARCHITECTURE.md` | Kernel, container, routing, module API design |
| `docs/INSTALLATION.md` | Installer + ownership claim model |
| `docs/MODULE_API.md` | Supported extension API surface |
| `docs/PACKAGE_SPEC.md` | addon.json manifest schema v1 |
| `docs/UPGRADES.md` / `docs/BACKUP_RESTORE.md` | Update & DR design |
| `docs/SECURITY.md` / `docs/THREAT_MODEL.md` | Security policy & model |
| `SESSION_HANDOFF.md` | Session-to-session work log |
