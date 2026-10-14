# Roadmap

Milestones toward a tested commercial 1.0 of the UNIVERSAL CORE. Stages
adapt to real dependencies; scope is preserved, not deferred silently.

| ID | Milestone | Exit criteria |
|----|-----------|---------------|
| M0 | Baseline | boundaries/architecture recorded, runnable bootstrap, CI green |
| M1 | Installer + identities | one-page install, owner claim, admin auth/MFA/sessions, usable core-only site |
| M2 | Content + presentation | pages/sections/menus/revisions, media library, theme tokens/slots, localization, metadata/sitemap/robots |
| M3 | Package system | versioned Module API, Module Manager upload→uninstall lifecycle, SDK scaffold + validator + compat kit |
| M4 | Operations | verified updates (signed metadata), backup/restore/recovery mode, jobs, system mail (SMTP + capture), health/diagnostics |
| M5 | Cross-industry proof | restaurant + salon reference modules, shared inquiry-form add-on (byte-identical ZIP on both sites), two distinct themes |
| M6 | Release | security/a11y/perf/hosting matrix evidence, commercial docs, independently reviewed RC, verified packages |

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
