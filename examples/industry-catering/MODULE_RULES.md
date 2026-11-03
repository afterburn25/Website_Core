# Module Creation Rules

**Rules contract: Cajun Web Pro Module Rules 1.1**

Read this file before creating or changing this module. The authoritative
cross-module contract is repository [`MOD_RULES.md`](../../MOD_RULES.md). If
this file and the master contract ever differ, stop and update the module
branch from `main` before continuing.

## Catering development scope — 2026-10-25

The owner has explicitly directed that catering development and releases remain
module-only while Website Core is developed separately. This direction takes
precedence over inherited requirements to synchronize or bundle every module
release with Core.

- Work only on `module/catering` in `modules/industry-catering/`.
- Do not change Website Core implementation, refresh its catering snapshot,
  merge the latest Core, or build a combined Core/module installer unless the
  owner explicitly requests that integration. Core roadmap documentation is
  the authorized exception described below.
- When catering work requires a missing or changed Core capability, add or
  update an actionable dependency in `afterburn25/Website_Core` on the active implementation branch
  (`devin/website-core` as verified 2026-10-25) at
  `docs/ROADMAP.md`. Check existing entries first to avoid duplicates. Describe
  the required shared API/service, the catering use case, and acceptance
  criteria; link it from the module roadmap and handoff and mark dependent
  module work blocked or deferred until Core supports it. Do not implement the
  Core change in this module session or duplicate the shared service here.
- Continue producing the installable module ZIP and matching `LATEST.json`
  when module behavior changes. Do not nest ZIPs inside a delivery ZIP.
- Keep honest Core/API compatibility requirements and run available module
  validation. Record integration checks that were deferred; do not claim
  compatibility with a newer Core without testing it.
- Module-only work does not require synchronized Core bundle CI. Perform and
  record combined-bundle checks when integration is explicitly requested.
- This module still uses Core's documented services; module-only development
  does not make it a standalone website or justify duplicating Core functions.

## Creation preflight

- [x] This module has its own `module/catering` branch.
- [x] This module has one folder under `modules/industry-catering/`.
- [x] `addon.json` has a unique namespaced ID, semantic version, honest Core
      range, Module API major, provider, autoload mapping, and capabilities.
- [x] Industry-specific behavior remains here; universal behavior remains in
      Core and is used only through `ModuleContext`.
- [x] The module declares its starting header and footer menus as separate
      `navigationMenu()` locations and renders each Core-owned
      `navigationTree()`.
- [x] The module declares its generic starting logo with `brandAssets()` and
      renders Core's current logo/favicon through `brandAssetUrl()` without
      overwriting later administrator choices.
- [x] The module renders Core's editable business copyright through
      `copyrightNotice()` without replacing the immutable Cajun Web Pro credit.
- [x] Header/footer icons render Core's location-filtered `socialLinks()` with
      no module-owned profile or placement settings and no empty placeholders.
- [x] Industry admin screens use one module category, protected admin routes,
      and explicit server-side capabilities.
- [x] Catering declares Core's shared inquiry/quote workspace, which supplies
      the overview metrics, search, assignment, notes, and quote navigation;
      the module registers no duplicate lead or Dashboard page.
- [x] Tables, migrations, public routes, assets, and settings are namespaced;
      installs and upgrades preserve existing data and customization.
- [x] Public forms use CSRF, validation, abuse controls, prepared database
      access, and escaped output.
- [x] The README, changelog, status, roadmap, testing, and handoff records have
      been created before the first release.
- [x] Complete module CI verification for version 0.2.0 (Module CI `40216516473`,
      Catering acceptance `40216516597`). The release ZIP and
      `LATEST.json` are complete; combined Core bundle verification remains
      deferred until the owner explicitly requests integration.

## Catering decisions

1. Audience: people and organizations planning catered gatherings.
2. Public navigation: the header begins with Our Story, Menus, Services,
   Packages, Gallery, Contact; the footer begins with planning and contact
   links. Core owns both trees after their independent one-time imports.
3. Module data: menu categories/items, packages, detailed event questionnaires,
   and catering settings; Core owns the linked inquiry and quotation workflow.
4. Core services: routing, views, settings, business profile, navigation,
   permissions, database, CSRF, rate limiting, mail, leads, quotes, admin
   search, dashboard, shared admin rendering, and health checks.
5. Compatibility: Catering 2.1.0 requires Core `>=2.6.2,<3.0.0` and Module
   API 1; acceptance runs in the module branch. Combined Core bundle
   acceptance is deferred until the owner requests integration.

Do not remove this file. It preserves the creation rules and module decisions
for future sessions without relying on conversation history.

