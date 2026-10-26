# Permanent Industry Module Rules

Read this file and `docs/MODULE_RULES.md` before creating, reviewing, or
releasing any industry module. These rules apply to Catering and every future
industry; a module may add industry behavior but may not replace Core.

## Required ownership model

1. Core owns login, administrators, optional public customer identity,
   permissions, pages, Media, forms,
   navigation storage/editor, live brand selections, footer identity, social
   profiles, themes, dashboard, backups, updates, health, audit, privacy, and
   integrations.
2. The industry owns only its schema, public routes, industry services,
   blocks, seeded content, and one named admin category.
3. The industry declares starting primary/footer navigation and starting
   brand images through `ModuleContext`. Core imports them once and owns every
   later administrator edit. Updates never repopulate cleared choices.
4. The Core Dashboard is the only dashboard. Industry summaries use
   `dashboardWidget()` and link to real module workspaces.
5. Module admin routes use the authenticated Core admin pipeline and shared
   layout. Every mutation also checks its declared capability, validates
   input, uses CSRF, records an audit event, and confirms destructive effects.
6. A module activates only the optional Core surfaces it can support through
   `commerce()`, `customerAccounts()`, `businessProfile()`, and/or
   `leadManagement()`. Undeclared features remain absent. Core owns generic
   records; the module owns industry meaning, validation, formulas, and rules.
7. Prefer `adminView()` and `adminResource()` for module administration.
   Custom screens are allowed only when the workflow cannot fit the shared
   contracts and must still use the authenticated Core shell.

## Required manifest and lifecycle

- Use one stable `publisher/package` ID, semantic version, explicit supported
  Core/API/PHP range, package-relative paths, declared assets, capabilities,
  dependencies/conflicts, and module-owned repeatable migrations.
- Exactly one industry package may be enabled for a site. Add-ons remain
  reusable and themes remain independent presentation packages.
- Disable stops code but retains files/data. Uninstall removes files and
  retains data. Purge is a separate irreversible typed-confirmation action.
- Missing/incompatible/cyclic dependencies, duplicate IDs, wrong directory
  type, escaping paths, missing providers, and boot failures must fail closed
  for that package while Core login/admin/recovery remains available.
- Every module gets its own `Core_Mods` branch and repository folder with a
  README, changelog, status/roadmap, versioned release artifact, checksum, and
  test evidence. Never develop one module by overwriting another module's
  branch or folder.

## Release proof required

Prove fresh install, supported upgrade, disable/enable, uninstall/reinstall,
purge, administrator-owned menu/brand preservation, dashboard/category
integration, failure quarantine, Core-only recovery, subfolder/no-rewrite
routing, responsive public/admin output, and installation from the exact
packaged ZIP. The executable details live in `docs/MODULE_API.md`,
`docs/PACKAGE_SPEC.md`, `docs/MODULE_REPOSITORY.md`, and
`docs/MODULE_RULES.md`.
