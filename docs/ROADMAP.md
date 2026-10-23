# Roadmap

Milestones toward a tested commercial 1.0 of the UNIVERSAL CORE. Stages
adapt to real dependencies; scope is preserved, not deferred silently.

## 0.1.15 → 1.0 delivery line

| Version | Scope | State | Git evidence |
|---|---|---|---|
| 0.1.15 | version and installation integrity | complete | commit “Stabilize version and integrity reporting” |
| 0.2 | core admin search, notices, dashboard | complete | commit “Build the core admin platform” |
| 0.3 | publishing workflow, trash, editable menus/submenus | complete | commit “Add editorial workflow and nested menus” |
| 0.4 | media derivatives/replacement and shared branding | complete | commit “Add shared media and branding controls” |
| 0.5 | package hooks, upgrade UX, immutable Cajun Web Pro footer | complete | commit “Expand the package integration API” |
| 0.6 | session controls, MFA policy, security headers | complete | commit “Add administrator session security controls” |
| 0.7 | operations, queue controls, automatic backups and safer restore | complete | commit “Add operations and recovery controls” |
| 0.8 | SEO insights, structured data, redirects and cache support | complete | commit “Add SEO insights and public caching” |
| 0.9 | release gate, compatibility hardening, cumulative-update metadata | complete | commit “Harden the 0.9 release candidate” |
| 1.0 | stable version, packages, checksums, release notes and branch handoff | complete | commit “Release Website Core 1.0.0” |
| 1.0.1 | existing-install schema boot repair and traceable production errors | complete | commit “Repair admin schema drift on boot” |
| 1.1 | visual block composer, full-width layouts, revision-safe block data and module block registration | complete | commit “Build the 1.1 visual page composer” |
| 1.2 | universal form builder, protected submissions, inbox, notifications and CSV export | complete | commit “Add the 1.2 universal forms platform” |
| 1.3 | media folders/tags, bulk organization, focal crops, usage inspection and branded status pages | complete | commit “Build the 1.3 media and status experience” |

The immutable footer is core code, not an editable setting or theme field.
Every public HTML response receives both required links to
`https://cajunwebpro.com`; modules cannot remove it through the supported API.

| ID | Milestone | State | Evidence |
|----|-----------|-------|----------|
| M0 | Baseline | done | boundaries/architecture recorded, runnable bootstrap, CI green |
| M1 | Installer + identities | done | one-page install, owner claim, admin auth/MFA/sessions, usable core-only site |
| M2 | Content + presentation | done | pages/sections/menus/revisions, media library, theme tokens/slots, localization, metadata/sitemap/robots |
| M3 | Package system | done | versioned Module API, Module Manager upload→uninstall lifecycle, SDK scaffold + validator + compat kit |
| M4 | Operations | done | verified updates (signed metadata), backup/restore/recovery mode, jobs, system mail (SMTP + capture), health/diagnostics |
| M5 | Cross-industry proof | done | restaurant + salon reference modules (`examples/industry-restaurant`, `examples/industry-salon`), shared inquiry-form add-on (`examples/addon-inquiry`), two distinct themes (`examples/theme-noir`, `examples/theme-terra`) — verified via `ReferencePackagesTest` and `ReferenceSitesParityTest` |
| M6 | Release | done | `docs/SECURITY.md` (passwords, MFA, sessions, CSRF, rate limits, input, headers, accessibility controls), `docs/COMPATIBILITY.md` (PHP 8.4/8.5, MySQL 8.4/MariaDB 11.4, Apache 2.4 mod_rewrite, LiteSpeed pending, no-rewrite fallback), `docs/COMMERCIAL_READINESS.md` (runtime deps, signing trust root, public repo policy, accessibility, performance posture), `docs/RELEASE_CHECKLIST.md` (release steps, verification, signing), AccessibilityTest (7 tests, 31 assertions) — aria-label, for labels, alt text, focus-visible, reduced-motion; release artifacts (FULL-PROJECT/COMPLETE-UPLOAD/UPDATE/packages + SHA256SUMS) built + CI-gated; `tools/verify-artifact.php` fresh-install dogfood; draft GitHub prerelease with 9 assets (unpublished — owner approval) |
| M7 | DRM licensing | done | `LicenseValidation` service, `license` table, periodic validation job, PackageManager license checks, installer license validation, bundled module extraction, `license_key` manifest field, `docs/LICENSING_API.md` API contract, LicenseValidationTest (4 tests) |

## Requirement IDs (stable)

- **CORE-BOOT-1** core-only install/boot with zero packages
- **CORE-INST-1** one-page key-free installer, resumable, self-disabling
- **CORE-INST-2** ownership claim: seeded `setup.token` or bound-session claim
- **CORE-AUTH-1** roles owner/admin/editor + capability registry
- **CORE-AUTH-2** TOTP MFA + single-use recovery codes + replay protection
- **CORE-AUTH-3** session rotation, idle/absolute expiry, revocation, last-owner guard
- **CORE-SEC-1** CSRF on all mutations, prepared SQL only, escaped output
- **CORE-PKG-1** addon.json schema v1 validation; duplicate/cycle/compat rejection
- **CORE-PKG-2** explicit lifecycle states + persistent journal
- **CORE-PKG-3** data-preserving uninstall; separate authorized purge
- **CORE-JOB-1** leased, bounded, idempotent job/outbox service + cron runner
- **CORE-UPD-1** signed update metadata + rollback protection (M4)
- **CORE-BAK-1** full backup/restore incl. module data + keys (M4)
- **CORE-MOD-1** recovery mode: minimal boot, quarantine boot-failures
- **SDK-PROOF-1** byte-identical add-on ZIP on two reference sites (M5)
- **ACC-MATRIX-1** security/a11y/hosting acceptance evidence (M6)

## External gates (tracked, not blockers to engineering)

- Production update-signing trust root — owner/legal input needed before
  a release ships a non-test trust configuration.
- Target-host provider list (LiteSpeed/shared panels) — needs evidence
  runs on real hosts before compatibility claims.
- Commercial licensing terms/legal entity — open owner decision.
