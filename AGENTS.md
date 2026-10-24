# AGENTS

## What this is

Website Core: a reusable PHP platform (universal core + industry modules +
add-ons + themes) for shared hosting. `site/` is the deployable web root.

## Working rules

- Core stays universal — check `CORE_BOUNDARIES.md` before adding anything
  business-shaped to core.
- Production target is MySQL/MariaDB via PDO prepared statements; the
  sqlite driver is dev/test-only.
- Never commit `site/config/config.php`, claim/lock files, or secrets.
- Root PHP entries are thin and allowlisted; logic lives in `site/core/`.
- Packages use ONLY the `ModuleContext` API (docs/MODULE_API.md).

## Non-negotiable delivery rules

- Every downloadable build is one directly usable ZIP. Never place a ZIP file
  inside another ZIP. For GitHub Actions artifacts, upload the extracted
  payload directory so GitHub's generated download ZIP is the package itself.
- Every complete, bundle, and repair upload includes the browser installer:
  `install.php`, `install/index.php`, `core/Install/Installer.php`, and
  `core/Install/views/installer.php`. Installed sites remain protected by the
  existing configuration plus installation-lock checks.
- CI must inspect these rules before publishing any artifact. Do not weaken or
  bypass the packaging contract in a later session.
## Verify

```bash
composer install
php tools/lint.php
vendor/bin/phpunit --testsuite unit,package
# native suite needs WC_TEST_MYSQL_DSN (runs in CI)
```

Dev server: `cd site && php -S 127.0.0.1:8000 ../tools/dev-router.php`

## Style

- `declare(strict_types=1)`; PSR-12; small classes; prepared SQL; escape
  via `e()`/`e_attr()`/`e_url()`; no dead controls or placeholder features.
- Migrations: ordered ids, journaled, driver-aware (mysql + sqlite-dev),
  additive-first.
