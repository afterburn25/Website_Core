# Module Repository and Release Synchronization

## Authority

Website Core and industry modules have separate development authorities:

- Core: `afterburn25/Website_Core`
- Modules: `afterburn25/Core_Mods`

In Core_Mods, `main` contains the shared repository standard, module registry,
documentation templates, and validation workflow. Every module is isolated in
both a long-lived branch and a repository folder:

```text
branch: module/<slug>
folder: modules/<module-id>/
```

The Catering Website module therefore lives on `module/catering` in
`modules/industry-catering/`. No second module is developed on that branch.

## Version ownership

Every module owns its semantic version in its `addon.json`. A Core version
change does not automatically change a module version. Any module behavior,
schema, view, style, script, or packaged-asset change requires a module version
increment and changelog entry.

Each module folder keeps:

- Source and assets
- `README.md` and `CHANGELOG.md`
- Project status, roadmap, testing, and session-handoff documents
- `releases/<module-id>-<version>.zip`
- `releases/LATEST.json` with version, filename, Core/API compatibility, and
  SHA-256 digest

## Core snapshot contract

Core's `examples/<module-id>/` directory is a release input, not the live module
development source. Synchronize it only from a committed, validated module
branch release.

For each synchronization:

1. Confirm the module branch CI passes.
2. Confirm `addon.json`, `LATEST.json`, and the release ZIP agree on version.
3. Verify the release ZIP digest and that `addon.json` is at its archive root.
4. Replace the corresponding Core example snapshot with that exact source.
5. Record the Core_Mods branch, commit SHA, module version, and release digest
   in the Core commit or release notes.
6. Run Core lint, unit, package, native MySQL/MariaDB, installer, and artifact
   contract tests before building or publishing combined installers.

Never make an untracked module hotfix only in Core. Fix and release it in its
Core_Mods branch, then synchronize the verified snapshot.
