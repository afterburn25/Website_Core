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
- **Status**: `2.0.27` commercial hardening. Broad feature development is
  frozen while every existing workflow is completed, polished, and verified
  screen by screen. See `PROJECT_STATUS.md`, `docs/COMMERCIAL_HARDENING.md`,
  `docs/2.0.26_RELEASE_NOTES.md`, and `docs/2.0.27_RELEASE_NOTES.md`.
- **Bundled industries**: catering and landscaping are synchronized release
  snapshots. Landscaping 1.0 includes four selectable themes, with Bold
  Outdoors as its default presentation.

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
| `docs/2.0.1_RELEASE_NOTES.md` | Module-owned navigation defaults and editable Core takeover |
| `docs/2.0.2_RELEASE_NOTES.md` | Commercial hardening program and polished administration shell |
| `docs/2.0.3_RELEASE_NOTES.md` | Conflict-safe page workflow and polished content workspace |
| `docs/2.0.4_RELEASE_NOTES.md` | Maintenance-mode administrator login recovery |
| `docs/2.0.5_RELEASE_NOTES.md` | Core-owned branding, header/footer menus, and copyright controls |
| `docs/2.0.6_RELEASE_NOTES.md` | Commercial workflow completion and administration UX hardening |
| `docs/2.0.7_RELEASE_NOTES.md` | Transparent brand presentation, revised admin identity, and unified social placement |
| `docs/2.0.8_RELEASE_NOTES.md` | Shared-host module update recovery and state-preserving upgrades |
| `docs/2.0.9_RELEASE_NOTES.md` | Fail-safe Core Updates workspace recovery |
| `docs/2.0.10_RELEASE_NOTES.md` | Shared-host stale-controller recovery |
| `docs/2.0.11_RELEASE_NOTES.md` | Version-keyed shared-host OPcache refresh |
| `docs/2.0.12_RELEASE_NOTES.md` | Version-specific runtime PHP loader and owner-only error details |
| `docs/2.0.13_RELEASE_NOTES.md` | Isolated update workspace and server-level branded 500 recovery |
| `docs/2.0.14_RELEASE_NOTES.md` | Professional admin polish, switches, form safety, and workspace preferences |
| `docs/2.0.15_RELEASE_NOTES.md` | Media reference safety, private previews, and focused workspace polish |
| `docs/2.0.16_RELEASE_NOTES.md` | Visual navigation ordering, visibility controls, and redirect editing |
| `docs/2.0.17_RELEASE_NOTES.md` | Forms builder, submission inbox, consent evidence, and bulk workflow polish |
| `docs/2.0.18_RELEASE_NOTES.md` | Advisory installer cleanup warning for split-ownership shared hosts |
| `docs/2.0.19_RELEASE_NOTES.md` | SEO, redirects, translations, and reusable-layout commercial completion |
| `docs/2.0.20_RELEASE_NOTES.md` | Appearance, child-theme assets, and responsive public-theme completion |
| `docs/2.0.21_RELEASE_NOTES.md` | Administrator, role, profile, session, and MFA commercial completion |
| `docs/2.0.22_RELEASE_NOTES.md` | Privacy lifecycle, consent, API credential, and webhook completion |
| `docs/2.0.23_RELEASE_NOTES.md` | Module lifecycle, recovery, and signed marketplace completion |
| `docs/2.0.24_RELEASE_NOTES.md` | Verified backups, restore safety, queue, and operations completion |
| `docs/2.0.25_RELEASE_NOTES.md` | Health, reviewed updates, audit evidence, and settings completion |
| `docs/2.0.26_RELEASE_NOTES.md` | Agency credentials, remote checks, and version visibility completion |
| `docs/2.0.27_RELEASE_NOTES.md` | Installer safety, explicit reinstall, and atomic configuration completion |
| `docs/COMMERCIAL_HARDENING.md` | Ordered screen-by-screen audit and commercial completion gates |
| `docs/MODULE_API.md` | Supported extension API surface |
| `docs/MODULE_RULES.md` | Permanent cross-industry module ownership, UX, lifecycle, and release rules |
| `docs/MODULE_REPOSITORY.md` | Authoritative module branches, folders, versioning and release sync |
| `docs/LANDSCAPING_INDUSTRY.md` | Landscaping 1.0 scope, themes, provenance, and Core delivery evidence |
| `docs/PACKAGE_SPEC.md` | addon.json manifest schema v1 |
| `docs/LICENSING_API.md` | Bundle/DRM licensing contract |
| `docs/INTEGRATIONS_API.md` | API keys, endpoints and signed webhooks |
| `docs/AGENCY_MARKETPLACE.md` | Agency registry, catalogs and managed update policies |
| `docs/UPGRADES.md` / `docs/BACKUP_RESTORE.md` | Update & DR design |
| `docs/SECURITY.md` / `docs/THREAT_MODEL.md` | Security policy & model |
| `SESSION_HANDOFF.md` | Session-to-session work log |
