# Commercial Hardening Program

Website Core is in a **feature freeze**. Work after 2.0.1 closes gaps in the
existing product, completes wiring, expands useful options, improves the GUI,
and proves that every advertised workflow is suitable for a paid commercial
release. New broad product areas are out of scope until this program closes.

## How work proceeds

One surface is completed at a time:

1. Inventory every route, button, form, background job, setting, and state.
2. Reproduce the workflow with a realistic user and data set.
3. Wire missing actions and remove dead, duplicate, or misleading controls.
4. Complete validation, errors, empty states, destructive confirmations,
   permissions, audit records, and recovery behavior.
5. Polish desktop, tablet, and mobile presentation with accessible keyboard
   and reduced-motion behavior.
6. Add unit/package/native acceptance coverage for the repaired contract.
7. Run the complete PHP 8.4/8.5 and MySQL/MariaDB CI matrix.
8. Update this document, the changelog, test evidence, and handoff before
   moving to the next surface.

A screen is not complete because it renders. It is complete only when its
primary task and failure/recovery paths work from the packaged build.

## Definition of commercially complete

Every listed surface must satisfy all applicable gates:

| Gate | Required evidence |
| --- | --- |
| Functional | Every visible control reaches a real server action; create, read, edit, delete/archive, search/filter, pagination, and export paths work where applicable. |
| Data | Validation is explicit; existing data survives upgrades; destructive actions are confirmed and recoverable where practical. |
| Security | Authentication, capability checks, CSRF, output escaping, upload constraints, rate limits, audit trails, and secret handling are verified. |
| UX | Clear page purpose, useful defaults, loading/empty/error/success states, consistent actions, no raw implementation data presented to ordinary administrators. |
| Visual | Shared design system, responsive layouts, readable hierarchy, polished tables/forms/cards, no overlapping or clipped controls. |
| Accessibility | Keyboard use, focus visibility, labels, landmarks, announcements, contrast, touch targets, and reduced motion meet the WCAG 2.2 AA target. |
| Operations | Health diagnostics identify failure; retry, rollback, backup, restore, and manual recovery instructions are actionable. |
| Release | Fresh install, supported upgrade, subfolder/no-rewrite hosting, packaged artifacts, checksums, and documentation pass CI and browser acceptance. |

## Ordered surface audit

Status values: **queued**, **in progress**, **code complete**, **verified**.

| Order | Surface | Commercial completion focus | Status |
| --- | --- | --- | --- |
| 1 | Shared admin shell and dashboard | Unified visual system, mobile navigation, persistent categories, search/account actions, alerts, versions, useful dashboard links | code complete — 2.0.2; packaged-browser check pending |
| 2 | Content list and page editor | Page lifecycle, visual blocks, preview, publish/review/schedule, revisions, duplication, trash/restore, validation, unsaved-state protection | queued |
| 3 | Media library | Upload failures, folders/tags, bulk operations, focal points/crops, usage, replacement, empty states, file limits | queued |
| 4 | Navigation Menu | Existing-menu import, tree editing, targets, page/external links, nesting, keyboard alternatives, validation, public rendering | queued |
| 5 | Forms and submissions | Field builder, validation, notifications, spam controls, consent evidence, inbox, filters, read state, archive/export | queued |
| 6 | SEO, redirects, translations, patterns | Actionable audits, previews, redirect import/export, localized content, reusable layouts, errors and conflicts | queued |
| 7 | Appearance and public theme | Branding, theme preview/activation, header/footer layouts, responsive output, attribution, status/error pages | queued |
| 8 | Administrators, roles, profile, security | Account lifecycle, role safety, capability clarity, MFA, sessions, recovery, lockouts, audit evidence | queued |
| 9 | Privacy and integrations | Consent behavior, privacy requests, API keys, webhooks, scopes, delivery failures, exports and secret rotation | queued |
| 10 | Modules and marketplace | Install/upgrade/enable/disable/uninstall/purge, dependency errors, protected Core, recovery mode, catalog trust and rollback | queued |
| 11 | Backups and operations | Create/delete/restore, remote replication, queue states, retries, schedules, interrupted operations, disaster recovery | queued |
| 12 | Health, updates, audit, settings | Actionable diagnostics, core upgrade staging/application, rollback, log clarity, mail testing, license state and cron setup | queued |
| 13 | Agency management | Remote credential lifecycle, connection tests, health/version visibility, failure handling and least privilege | queued |
| 14 | Installer and reinstall/upgrade | Single-page polish, database test, HTTPS, safe writable-path provisioning, existing DB handling, cleanup and manual recheck | queued |
| 15 | Industry-module contract | Module admin category, default navigation takeover, shared services, uninstall/reinstall, compatibility and isolated failure | queued |
| 16 | Final cross-browser release gate | Chrome/Edge/Firefox/Safari where available, mobile breakpoints, accessibility review, performance/load, clean-host install and oldest-supported upgrade | queued |

## 2.0.2 — shared shell acceptance

- [x] Admin CSS and JavaScript moved into versioned Core assets served by the
      constrained, rewrite-independent asset route.
- [x] Desktop sidebar retains expanded/collapsed groups and scroll position.
- [x] Mobile navigation has an accessible open/close flow, overlay, focus
      transfer, and Escape handling.
- [x] Header provides clear section hierarchy, admin search, notifications,
      public-site launch, and a compact account menu.
- [x] Flash messages use live status/alert roles and can be dismissed.
- [x] Forms, cards, tables, badges, buttons, builders, empty states, and focus
      styles share a polished responsive design system.
- [x] Dashboard metrics link to their real management areas and provide useful
      quick actions instead of passive statistics only.
- [x] PHP 8.4/8.5, package, MySQL, MariaDB, artifact, and Core-only CI pass
      (GitHub Actions run 400, run ID `40045870820`).
- [ ] Browser verification completed on a packaged subfolder installation.

The next implementation target after 2.0.2 verification is **Content list and
page editor**. No later surface should be marked complete early merely because
some of its underlying code already exists.

