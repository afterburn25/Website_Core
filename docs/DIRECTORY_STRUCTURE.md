# Directory Structure

```
Website_Core/
  .github/workflows/   CI: lint+unit matrix, native MySQL/MariaDB, core-only boot
  docs/                Architecture, specs, policies (never in production zips)
  tests/               Unit / Native (MySQL) / Package suites
  tools/               lint.php, dev-router.php, build utilities
  sdk/                 addon.json schema, package scaffolding, compat kit
  examples/            Reference industry/add-on/theme packages (M5)
  site/                === deployable web root (COMPLETE-UPLOAD contents) ===
    index.php          Thin front controller (only required HTTP entry)
    install.php        Thin installer entry; self-disables after success
    cron.php           Token-guarded/CLI job runner for host cron
    .htaccess          Front-controller rewrite + private-path protection
    core/              Universal services + supported extension API
    includes/          bootstrap, autoload, template helpers (small, fixed set)
    admin/controllers/ Admin controllers (Admin\Controllers\)
    admin/views/       Admin templates (layouts + pages)
    database/migrations/ Ordered, journaled migration files
    config/            config.example.php; generated config.php is gitignored
    install/           Installer class + views (setup-only)
    modules/industries/ Install destination — empty on core-only setup
    modules/addons/     Install destination — empty on core-only setup
    themes/base/        Usable, replaceable industry-neutral theme
    assets/{css,js}/    Core public assets
    images/{branding,icons,placeholders}/
    storage/            Protected scaffolding (backups/tmp/ratelimit)
    upload/             Protected staging area for package uploads
```

## Source vs deployment

- `site/` is the COMPLETE-UPLOAD payload — extract directly into the
  website directory; internal layout is preserved, no enclosing wrapper.
- `docs/`, `tests/`, `tools/`, `sdk/`, `examples/`, `composer.*`,
  `phpunit.xml` are development material: present in FULL-PROJECT only.
- `.htaccess` rules + per-directory deny files protect `core/`, `config/`,
  `modules/`, `themes/`, `storage/`, `upload/`, `admin/controllers`,
  `admin/views`, `install/`, `includes/`, `database/`, `vendor/` from
  direct HTTP access. Protection is layered: rules + deny files +
  preferred external `paths.storage` outside the document root.
- Theme/module public assets are served through the namespaced resolver
  `/_a/themes/<theme>/...` and `/_a/modules/<pkg>/...` (allowlisted MIME,
  realpath-constrained, no listing) — assets are not directly web-exposed.
