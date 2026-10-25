# Industry Module Consistency Rules

Read this file before creating or updating any industry module. These rules are
part of the commercial Core/module contract and do not change from one
industry to another.

The root `MOD_RULES.md` is the short creation/release checklist. This document
is the detailed authority; module work must read both before implementation.

## Ownership boundaries

- Core owns authentication, administrator accounts, permissions, settings,
  pages, Media, forms, navigation storage, branding selections, themes,
  backups, updates, health, audit history, privacy, and integrations.
- A module owns only its industry-specific schema, services, blocks, public
  routes, seeded industry content, and its named administration category.
- A module may declare starting header/footer navigation and brand assets.
  Core imports those defaults once; the administrator owns all later edits.
- A module must not create a second dashboard. It contributes overview widgets
  to the Core Dashboard and industry pages to its own admin category.

## Administrator experience

- Every module page must state its purpose, use Core cards/forms/tables/buttons,
  include useful empty/error/success states, and remain responsive.
- Visible controls must call real actions. Mutations require capability checks,
  CSRF protection, validation, an audit record, and an explicit confirmation
  when data or availability can be lost.
- Module admin routes use Core's shared layout and navigation registration;
  direct unstyled administration pages are not allowed.
- Labels use business language. Internal IDs, raw JSON, stack traces, and
  secrets are not ordinary administrator UI.

## Lifecycle and compatibility

- Each module has its own repository branch and repository folder, semantic
  version, changelog, status/roadmap, tests, package checksum, and documented
  supported Core range.
- Install and upgrade migrations are repeatable. Disable stops module code
  without deleting data. Uninstall removes files while preserving data. Purge
  is separate, typed-confirmation protected, and irreversible.
- A failed module must be isolatable through Core recovery mode. Module code
  cannot be required for Core admin, login, installer cleanup, or backups.
- Exactly one industry package may be enabled on a site. Shared capabilities
  belong in add-ons, while visual alternatives belong in themes.
- Reinstall must adopt compatible existing module tables and must not overwrite
  administrator-owned navigation, branding, content, or settings.

## Release evidence

Every module release must prove fresh install, supported upgrade, disable,
enable, uninstall, reinstall, purge, Core-only recovery, subfolder/no-rewrite
routing, responsive public/admin rendering, and packaging from its authoritative
branch. See `MODULE_API.md`, `PACKAGE_SPEC.md`, and `MODULE_REPOSITORY.md` for
the executable interfaces and repository workflow.

## Runtime-enforced manifest boundaries

- Package type must match its destination (`industries`, `addons`, or
  `themes`); duplicate IDs remain invalid regardless of database state.
- Entry, PSR-4, migration, asset, integrity, and starting-brand paths are
  confined to the package. Theme packages cannot declare PHP providers,
  entry files, PSR-4 roots, or migrations.
- Dependency cycles are rejected. An enabled dependent is quarantined if its
  required dependency does not successfully boot.
- Declared entry files, PSR-4 roots, and provider classes must exist. A
  provider failure quarantines only that package and records journal evidence.
- Contribution keys, labels, admin routes, capabilities, navigation URLs, and
  default brand paths are validated as they register; duplicate per-package
  contribution keys fail that package's boot.
- Capability and scheduled-job tokens use the package slug as their prefix
  (for example `catering.manage` and `catering.send_followup`) so one module
  cannot claim Core or another module's global token.
