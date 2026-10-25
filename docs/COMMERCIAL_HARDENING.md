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
| 1 | Shared admin shell and dashboard | Unified visual system, mobile navigation, persistent categories, search/account actions, alerts, versions, useful dashboard links | code complete through 2.0.15; packaged-browser check pending |
| 2 | Content list and page editor | Page lifecycle, visual blocks, preview, publish/review/schedule, revisions, duplication, trash/restore, validation, unsaved-state protection | code complete — 2.0.3; packaged-browser check pending |
| 3 | Media library | Upload failures, folders/tags, bulk operations, focal points/crops, usage, replacement, empty states, file limits | code complete — 2.0.15; packaged-browser check pending |
| 4 | Navigation Menu | Existing-menu import, tree editing, targets, page/external links, nesting, keyboard alternatives, validation, public rendering | code complete — 2.0.16; packaged-browser check pending |
| 5 | Forms and submissions | Field builder, validation, notifications, spam controls, consent evidence, inbox, filters, read state, archive/export | code complete — 2.0.17; packaged-browser check pending |
| 6 | SEO, redirects, translations, patterns | Actionable audits, previews, redirect import/export, localized content, reusable layouts, errors and conflicts | code complete — 2.0.6; packaged-browser check pending |
| 7 | Appearance and public theme | Branding, theme preview/activation, header/footer layouts, responsive output, attribution, status/error pages | code complete — 2.0.6; packaged-browser check pending |
| 8 | Administrators, roles, profile, security | Account lifecycle, role safety, capability clarity, MFA, sessions, recovery, lockouts, audit evidence | code complete — 2.0.6; packaged-browser check pending |
| 9 | Privacy and integrations | Consent behavior, privacy requests, API keys, webhooks, scopes, delivery failures, exports and secret rotation | code complete — 2.0.6; packaged-browser check pending |
| 10 | Modules and marketplace | Install/upgrade/enable/disable/uninstall/purge, dependency errors, protected Core, recovery mode, catalog trust and rollback | code complete through 2.0.14; failed-migration file rollback and landscaping lifecycle CI covered; packaged-browser check pending |
| 11 | Backups and operations | Create/delete/restore, remote replication, queue states, retries, schedules, interrupted operations, disaster recovery | code complete — 2.0.6; packaged-browser check pending |
| 12 | Health, updates, audit, settings | Actionable diagnostics, core upgrade staging/application, rollback, log clarity, mail testing, license state and cron setup | code complete through 2.0.15; packaged-browser check pending |
| 13 | Agency management | Remote credential lifecycle, connection tests, health/version visibility, failure handling and least privilege | code complete — 2.0.6; packaged-browser check pending |
| 14 | Installer and reinstall/upgrade | Single-page polish, database test, HTTPS, safe writable-path provisioning, existing DB handling, cleanup and manual recheck | code complete — 2.0.6; packaged-host check pending |
| 15 | Industry-module contract | Module admin category, default navigation takeover, shared services, uninstall/reinstall, compatibility and isolated failure | code complete — 2.0.6; packaged-module check pending |
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

## 2.0.3 — Content and Page Editor acceptance

- [x] Page search, publishing/review filters, summaries, pagination, and empty
      states work through the no-rewrite subfolder route.
- [x] Invalid editor submissions preserve the administrator's entered values
      and explain canonical URL, JSON schema, media, parent, and date errors.
- [x] Optimistic locking blocks stale-tab overwrites.
- [x] Revision snapshots preserve and restore the complete editable page,
      while retaining the pre-restore version as another revision.
- [x] Draft preview opens the rendered preview instead of hiding its URL in a
      flash message.
- [x] Visual blocks support pointer drag and labeled keyboard move controls;
      removal is confirmed and changes are announced.
- [x] Unsaved-change protection, live SEO preview, character counts, public
      page links, and responsive editor/status panels are wired.
- [x] Trash restore and permanent deletion have confirmations, audit evidence,
      dependent-record cleanup, and missing-record 404 behavior.
- [x] PHP 8.4/8.5, package, MySQL, MariaDB, artifact, and Core-only CI pass
      (push run 404, run ID `40046902895`; PR run 405 also passed).
- [ ] Packaged subfolder browser verification covers create, validation,
      conflict, preview, publish, schedule, duplicate, trash, restore, purge,
      and revision restore.

Version 2.0.6 carries the same acceptance discipline through the remaining
existing surfaces. Browser/host evidence stays explicitly pending until it is
run against packaged builds; code-complete does not mean externally verified.

## 2.0.14 — shared commercial polish acceptance

- [x] Desktop sidebar collapse and compact density are optional, persistent,
      keyboard reachable, and do not alter saved category state.
- [x] Destructive operations share an accessible branded confirmation dialog;
      canceling an auto-submit switch restores its prior visual value.
- [x] Binary settings use a common switch design with explicit descriptions
      across Operations, Security, Forms, Privacy, Integrations, Users, and
      Modules.
- [x] Substantial forms receive unsaved-work protection and length-limited
      fields expose live counts without changing server validation.
- [x] Core CSS and JavaScript remain available through the subfolder-aware
      allowlisted asset route during maintenance and license-denial states.
- [x] Appearance previews newly selected logo/favicon files before save.
- [x] SMTP and analytics controls expose only relevant inputs; inactive SMTP
      values remain stored rather than being erased by a transport change.
- [ ] Packaged browser verification covers keyboard use, confirmation cancel,
      mobile navigation, sidebar/density persistence, and switch mutations.

## 2.0.15 — Media workspace acceptance

- [x] Media upload and bulk controls expose clear selection state, and image
      focal points use a visual crop preview instead of unexplained numbers.
- [x] Page blocks and social images register their Media references; details
      identify and link to referencing pages before destructive deletion.
- [x] Authorized administrators can preview private-image variants while
      anonymous visitors remain forbidden and private responses are not cached.
- [ ] Packaged browser verification covers upload, private preview, page usage,
      focal-point editing, URL copying, bulk organization, and guarded delete.

## 2.0.16 — Navigation workspace acceptance

- [x] Dragging supports precise before, after, and submenu placement; arrow
      controls provide equivalent ordering without pointer input.
- [x] Links may be hidden without deletion, and hidden parent subtrees remain
      absent from public navigation without triggering page-list fallback.
- [x] Page, internal, external, anchor, email, and telephone destinations are
      identified, validated, and paired with persistent target behavior.
- [x] Removing a parent promotes children one level in place and normalizes
      their sibling order.
- [x] Redirects expose safe create, edit, and delete workflows with actionable
      duplicate, missing-record, length, loop, and destination validation.
- [ ] Packaged browser verification covers pointer ordering, keyboard ordering,
      visibility, targets, mobile editing, redirects, and public active state.

## 2.0.17 — Forms and submissions workspace acceptance

- [x] Form fields support precise pointer ordering and labeled keyboard move
      controls, with a representative live public preview and responsive
      desktop/mobile workspace.
- [x] Field definitions retain help, placeholder, autocomplete, required, and
      dropdown choices while server validation rejects duplicate identifiers,
      invalid selections, invalid dates, missing forms, and excessive lengths.
- [x] Invalid form saves keep every entered setting and field visible for
      correction instead of redirecting to a blank editor.
- [x] Forms can be paused and reactivated without losing settings or response
      history; library summaries distinguish active forms and new responses.
- [x] The inbox supports search, form/status filtering, pagination, expandable
      response details, consent timestamps, notification queue evidence,
      individual status updates, selection, bulk processing, and confirmed
      permanent deletion.
- [x] Filtered CSV exports use response columns instead of raw JSON, include
      consent and notification evidence, and neutralize spreadsheet formula
      prefixes.
- [x] Site transfer remaps portable form definitions and page Form blocks
      without exporting recipients or visitor submissions.
- [x] Public intake retains CSRF, honeypot, IP-scoped throttling, bounded
      values, consent evidence, queued mail, and branded no-store outcomes.
- [ ] Packaged browser verification covers builder dragging and keyboard
      ordering, failed-save retention, pause/reactivate, public validation,
      inbox filters/details, bulk actions, deletion, notification state, CSV,
      and mobile layouts.

## 2.0.14 — diagnostic and fatal-page recovery acceptance

- [x] Health runs from a fresh dedicated controller path.
- [x] Audit runs from a separate dedicated controller and preserves a usable
      workspace when its history query cannot complete.
- [x] Queue, backup, package, database, mail, integrity, extension-health, and
      disk collectors fail independently and preserve a usable partial report.
- [x] Collector failures are logged privately and summarized without exposing
      server paths or credentials in ordinary page output.
- [x] The front controller installs a shutdown renderer before loading cleanup
      or bootstrap code and serves the offline branded 500 page for fatal PHP
      startup failures.
- [x] Fatal fallback assets, retry, and administrator-login URLs are rewritten
      for root and subfolder installations.
- [x] Hosted verification confirms authenticated `/admin/health` and
      `/admin/audit` render on the target LiteSpeed/shared-host deployment;
      Health reports zero integrity issues and Audit exposes the preserved
      administrative history.
- [ ] A controlled front-controller fatal remains to verify the pre-bootstrap
      branded fallback on the target LiteSpeed/shared-host configuration.

## 2.0.4 — maintenance administrator access repair

- [x] Public routes continue to receive the branded 503 maintenance page.
- [x] `/admin`, `/admin/login`, MFA, password recovery, and protected admin
      routes bypass the public maintenance intercept.
- [x] Logged-out `/admin` requests are redirected by the normal authentication
      middleware to the rewrite-independent subfolder login URL.
- [x] Protected admin pages still require an authenticated administrator.
- [x] PHP 8.4/8.5, MySQL, MariaDB, Core-only boot, package, and artifact CI
      pass (GitHub Actions run 408, run ID `40048346083`).
- [ ] Hosted browser verification confirms login, logout, and login-again while
      maintenance mode remains enabled.

## 2.0.5 — brand ownership and upload acceptance

- [x] Industry modules may declare package-relative starting logo, favicon,
      and social images through Module API v1.
- [x] Core confines, validates, and copies defaults into its own Media storage;
      package files remain intact.
- [x] Existing brand selections are never overwritten, and a deliberately
      cleared default remains cleared after later boots and upgrades.
- [x] Website → Appearance directly uploads logo/favicon files, shows current
      previews, retains Media selectors, rejects non-image content, and tracks
      brand usage.
- [x] The selected logo and favicon render in the shared Core admin shell,
      base public theme, and catering public/admin layouts with fallbacks.
- [x] Catering 0.1.20 carries the transparent CATERING CO. default and requires
      Core `>=2.0.5,<3.0.0`.
- [x] The Core Dashboard owns the shared overview and renders module widgets;
      catering has no duplicate dashboard route or navigation entry.
- [x] Header and footer menus are separately editable through the polished
      visual tree, and module defaults are imported once per location.
- [x] Appearance stores a plain-text token-aware copyright notice while the
      mandatory Cajun Web Pro attribution remains immutable and separate.
- [x] Optional HTTPS social profiles render as accessible header, footer, or
      dual-placement icons; blank platforms create no placeholder or link.
- [ ] Packaged subfolder browser verification covers upload, replace, clear,
      public header/footer, favicon, Core admin, and module admin rendering.

## 2.0.6 — cross-surface completion acceptance

- [x] Every Core administration destination has a consistent icon, purpose
      statement, responsive data presentation, and persistent navigation.
- [x] Media, menu, and form workspaces expose real filters, pagination,
      status/selection feedback, explicit destructive confirmations, and
      auditable mutations.
- [x] SEO, translations, appearance, privacy, accounts, roles, integrations,
      modules, marketplace, backups, operations, health, updates, audit,
      settings, and agency screens use the shared presentation system.
- [x] Mail settings include an immediate delivery test; failed webhooks can be
      retried and disabled webhooks re-enabled without recreating secrets.
- [x] Audit history supports search and pagination; newly completed mutation
      paths record useful, non-secret evidence.
- [x] Subfolder/no-rewrite public launch links use the site base rather than
      accidentally inheriting the front-controller route base.
- [x] Installer cleanup/recheck, protected Core package rules, module-owned
      navigation/brand defaults, and isolated recovery behavior remain covered
      by the release and package test suites.
- [ ] Packaged browser/host acceptance completes the remaining visual and
      real-host evidence for surfaces 1–15.

