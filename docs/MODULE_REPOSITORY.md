# Module Repository and Release Synchronization

## Authority

Website Core and industry modules have separate development authorities:

- Core: `afterburn25/Website_Core`
- Modules: `afterburn25/Core_Mods`

In Core_Mods, `main` contains the versioned `MOD_RULES.md` contract, shared
repository standard, module registry, creation templates, and validation
workflow. Every new module begins with its own required `MODULE_RULES.md`
creation preflight and is isolated in both a long-lived branch and a repository
folder:

```text
branch: module/<slug>
folder: modules/<module-id>/
```

The Catering Website module therefore lives on `module/catering` in
`modules/industry-catering/`. No second module is developed on that branch.

Core's root `MOD_RULES.md` is the runtime/release-side mirror of that permanent
contract. A Core or Core_Mods change that alters the boundary must update both
authorities in the same coordinated release; a module branch cannot weaken it.

## Version ownership

Every module owns its semantic version in its `addon.json`. A Core version
change does not automatically change a module version. Any module behavior,
schema, view, style, script, or packaged-asset change requires a module version
increment and changelog entry.

Each module folder keeps:

- Source and assets
- `README.md`, `MODULE_RULES.md`, and `CHANGELOG.md`
- Project status, roadmap, testing, and session-handoff documents
- `releases/<module-id>-<version>.zip`
- `releases/LATEST.json` with version, filename, Core/API compatibility, and
  SHA-256 digest

## Core snapshot contract

Core's `examples/<module-id>/` directory is a release input, not the live module
development source. Synchronize it only from a committed, validated module
branch release.

Every stable module release must be synchronized into the current active Core
line before it is considered shipped. A module release is complete only when
both its module-branch CI and the corresponding latest-Core CI pass. The Core
snapshot contains the exact runtime source from the verified release archive;
repository documentation and release metadata remain in Core_Mods and are
recorded by digest in Core's release notes.

For each synchronization:

1. Confirm the module branch CI passes.
2. Confirm `addon.json`, `LATEST.json`, and the release ZIP agree on version.
3. Verify the release ZIP digest and that `addon.json` is at its archive root.
4. Replace the corresponding Core example snapshot with the exact runtime
   source from that archive.
5. Record the Core_Mods branch, commit SHA, module version, and release digest
   in the Core commit or release notes.
6. Run Core lint, unit, package, native MySQL/MariaDB, installer, and artifact
   contract tests before building or publishing combined installers.

Never make an untracked module hotfix only in Core. Fix and release it in its
Core_Mods branch, then synchronize the verified snapshot.

## Shared module rules

`Core_Mods/MOD_RULES.md` contract 1.1 applies the same ownership rule to
navigation and branding: a module declares its suitable starting defaults,
Core imports them once, and administrator changes remain authoritative. A
module that ships brand defaults must use package-relative `brandAssets()`
paths and render the current Core selection with `brandAssetUrl()`. Modules
declare header and footer defaults as distinct menu locations, render the
current Core trees, and use Core's copyright-notice API rather than shipping a
hard-coded business copyright.
Optional social profiles and the shared header/footer placement are Core-owned;
modules render the filtered `socialLinks()` results and never ship a second
set of profile settings or individual placement controls.
The Core Dashboard is also the sole overview: modules contribute summaries
through `dashboardWidget()` and must not add a duplicate Dashboard page to
their module category.
