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
| 6 | SEO, redirects, translations, patterns | Actionable audits, previews, redirect import/export, localized content, reusable layouts, errors and conflicts | code complete — 2.0.19; packaged-browser check pending |
| 7 | Appearance and public theme | Branding, theme preview/activation, header/footer layouts, responsive output, attribution, status/error pages | code complete — 2.0.20; packaged-browser check pending |
| 8 | Administrators, roles, profile, security | Account lifecycle, role safety, capability clarity, MFA, sessions, recovery, lockouts, audit evidence | code complete — 2.0.21; packaged-browser check pending |
| 9 | Privacy and integrations | Consent behavior, privacy requests, API keys, webhooks, scopes, delivery failures, exports and secret rotation | code complete — 2.0.22; packaged-browser check pending |
| 10 | Modules and marketplace | Install/upgrade/enable/disable/uninstall/purge, dependency errors, protected Core, recovery mode, catalog trust and rollback | code complete — 2.0.23; packaged-browser check pending |
| 11 | Backups and operations | Create/delete/restore, remote replication, queue states, retries, schedules, interrupted operations, disaster recovery | code complete — 2.0.24; packaged-browser check pending |
| 12 | Health, updates, audit, settings | Actionable diagnostics, core upgrade staging/application, rollback, log clarity, mail testing, license state and cron setup | code complete — 2.0.25; packaged-browser check pending |
| 13 | Agency management | Remote credential lifecycle, connection tests, health/version visibility, failure handling and least privilege | code complete — 2.0.26; packaged-browser check pending |
| 14 | Installer and reinstall/upgrade | Single-page polish, database test, HTTPS, safe writable-path provisioning, existing DB handling, cleanup and manual recheck | code complete — 2.0.27; packaged-host check pending |
| 15 | Industry-module contract | Module admin category, default navigation takeover, shared services, uninstall/reinstall, compatibility and isolated failure | code complete — 2.0.28; packaged-module check pending |
| 16 | Final cross-browser release gate | Chrome/Edge/Firefox/Safari where available, mobile breakpoints, accessibility review, performance/load, clean-host install and oldest-supported upgrade | in progress — hosted recovery and workflow repairs shipped in 2.0.29–2.0.31 |

## 2.0.29 — Hosted acceptance repair

- [x] Reproduced the packaged MariaDB backup failure on shared hosting and
      traced it to a native-prepared `SHOW TABLES LIKE ?` statement.
- [x] Replaced table discovery with a bound `information_schema.tables` query
      whose prefix comparison treats `_` and `%` literally.
- [x] Added native MySQL/MariaDB backup creation and inspection coverage.
- [x] Hosted 2.0.28 smoke testing covered major Core/Catering admin screens,
      public pages, no-rewrite 404, contact handling, quote review, keyboard
      entry, and persistent admin navigation state.
- [x] Deployed 2.0.29 and verified live MariaDB backup creation/inspection.
- [ ] Deploy 2.0.31 and verify download/restore/update rollback after the live
      restore exposed a panel-owned document-root file.
- [ ] Complete root-domain, Landscaping, non-Chrome browser, mobile assistive-
      technology, installer/reinstall, performance, and destructive lifecycle
      gates on suitable disposable targets before tagging the commercial build.

## 2.0.30 — Split-runtime recovery acceptance

- [x] Backup sources resolve configured private config, upload, and media roots.
- [x] Restore destinations use those same configured roots.
- [x] Every changed destination is preflighted before database import; identical
      read-only deployment files are skipped instead of copied needlessly.
- [x] Owners can download verified recovery points from protected storage.
- [x] External-runtime roots, read-only identical files, and streamed file
      responses have focused regression coverage.
- [ ] Deploy to the shared-host target and repeat create, download, restore,
      integrity, and update-rollback acceptance before module lifecycle tests.

## 2.0.31 — Administration workflow acceptance

- [x] Managed-site fields use a stable responsive grid.
- [x] 404 resolution records use self-contained responsive cards.
- [x] Missing MFA on the signed-in account has a direct setup action.
- [x] SMTP encryption selects standard ports while preserving custom values.
- [x] SMTP handshake/authentication can be tested without saving or sending.
- [x] Forms uses one clear three-step workflow and offers editable starter sets.
- [ ] Verify these surfaces in the uploaded shared-host package before final
      cross-browser and assistive-technology release gates.

## 2.0.27 — Installer and reinstall/upgrade acceptance

- [x] Setup is bound to the claimed browser with a dedicated CSRF token and
      secure, HTTP-only, SameSite session cookie.
- [x] A non-blocking operation lock rejects simultaneous install submissions
      without corrupting files or running duplicate initialization.
- [x] Database testing identifies an existing Core schema and requires an
      explicit preservation/upgrade confirmation before install can continue.
- [x] Repair/reinstall preserves a valid application key, existing content,
      settings, cron token, module data, and the submitted owner lifecycle.
- [x] Canonical HTTPS, length, prefix, port, owner, and email boundaries are
      validated; driver errors are logged privately and safely summarized.
- [x] Config and completion-marker writes are temporary, verified, and atomic;
      installer artifacts are precisely removed/retired with manual recheck.
- [ ] Packaged-host verification covers fresh install, existing-schema cancel
      and confirm, invalid credentials, duplicate submit, cleanup, and launch.

## 2.0.28 — Industry-module contract acceptance

- [x] Root and detailed permanent rule files define Core/module ownership,
      administration, lifecycle, repository, and release evidence.
- [x] Duplicate IDs, wrong destination type, unsafe paths, executable theme
      hooks, malformed providers/PSR-4 roots, and unsafe assets fail validation.
- [x] Dependency cycles fail before recursive enablement; dependents whose
      required package does not boot are quarantined with journal evidence.
- [x] Declared entries, directories, and provider classes must exist within the
      package; one failure does not take down login, Core admin, or other mods.
- [x] Module routes/contributions/default navigation/default brands validate
      their namespace, labels, capability, destination, uniqueness, and paths.
- [x] Migration-directory lists run consistently in normal install, direct
      filesystem finalization, and bundled setup; missing declarations fail.
- [x] Only one industry may be enabled, while add-ons/themes stay independent;
      Core retains editable navigation, branding, dashboard, and shared tools.
- [x] PHP 8.4/8.5, package, MySQL 8.4, MariaDB 11.4, Core-only boot, and
      release-artifact contracts pass in GitHub Actions run 503
      (run ID `40163522209`).
- [ ] Packaged-module verification covers catering/landscaping fresh install,
      upgrade, disable/enable, uninstall/reinstall, purge, and quarantine.

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
- [x] Navigation hands redirect work to the authoritative SEO workspace; safe
      create, edit, delete, duplicate, missing-record, length, loop, and
      destination validation remain available there.
- [ ] Packaged browser verification covers pointer ordering, keyboard ordering,
      visibility, targets, mobile editing, SEO handoff, and public active state.

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

## 2.0.19 — SEO, translations, and patterns acceptance

- [x] SEO owns one complete redirect workspace with manual create/edit/delete,
      traffic counts, missing-path recovery, loop/conflict validation, and CSV
      import/export with bounded rows and actionable first-error reporting.
- [x] Navigation links to the SEO Redirect Manager instead of exposing a
      duplicate redirect editor with separate workflow state.
- [x] Published-page review reports search title/description length, malformed
      canonical URLs, noindex state, invalid schema or blocks, missing image
      alternatives, and weak link labels with direct page-editor access.
- [x] Sitemap and robots endpoints remain visible and exclude draft, deleted,
      noindex, preview, and administration content as applicable.
- [x] Translations show per-language completion and public state, retain
      invalid submissions, validate block/schema JSON, and provide source and
      localized content plus live search preview in one editor.
- [x] Disabled languages preserve translations but stop public localized
      resolution, translated navigation, and alternate-language metadata until
      an administrator re-enables them.
- [x] Individual translations can be removed without changing the source page
      or another locale, with confirmation and audit evidence.
- [x] Layout Patterns provide a searchable visual block-order preview plus
      unique naming, rename, duplicate, guarded delete, and revision-backed
      draft application.
- [x] Focused unit/static contracts cover language lifecycle and JSON errors,
      pattern lifecycle, redirect deletion, and the visible commercial
      workspace controls.
- [x] PHP 8.4/8.5, Core-only boot, MySQL 8.4, MariaDB 11.4, package, and
      directly usable artifact contracts pass in GitHub Actions run 493
      (run ID `40153106399`).
- [ ] Packaged subfolder browser verification covers missing-path resolution,
      redirect conflict/import/export, language lifecycle, failed translation
      save retention, public localized routing, pattern search/duplicate/apply,
      revision recovery, and mobile layouts.

## 2.0.20 — Appearance and public theme acceptance

- [x] Appearance provides one responsive brand workspace with current-state
      summaries, direct logo/favicon/social-image upload, Media selection,
      shared social placement, layout choices, and representative live preview.
- [x] Validation failures retain entered design, copyright, and social values
      and return an actionable message instead of silently discarding work.
- [x] Theme activation validates discovered package compatibility before the
      active-theme setting changes.
- [x] Child themes may use the documented `parent` key or legacy `extends`, and
      declared CSS/JS loads through the constrained subfolder-safe asset route.
- [x] Public output supplies baseline Open Graph metadata, brand social image,
      responsive nested navigation, keyboard Escape handling, and a usable
      no-JavaScript navigation fallback.
- [x] Core keeps required attribution protected separately from theme markup
      and administrator-editable business copyright content.
- [ ] Packaged browser verification covers upload previews, invalid-value
      retention, theme activation, child assets, mobile navigation, social
      metadata, and public layout variants on a real subfolder installation.

## 2.0.21 — Administrators, roles, profile, and security acceptance

- [x] Administrator creation validates unique username/email, retains safe
      submitted fields after errors, and never exposes owner assignment to a
      non-owner manager.
- [x] Role changes and enable/disable actions enforce server-side authority;
      last-enabled-owner validation and mutation occur in one transaction.
- [x] Custom roles expose grouped plain-language capability descriptions,
      assignment counts, protected built-ins, and guarded deletion.
- [x] Administrators can update their own username/email, change password,
      review device sessions, revoke one, or revoke all other sessions.
- [x] MFA enrollment can be cancelled; recovery codes are shown once, counted,
      copied, and password-confirmed for replacement.
- [x] Replacing recovery codes or disabling MFA revokes stale recovery
      credentials and other sessions and records audit evidence.
- [x] Security summarizes MFA coverage, policy, sessions, enabled owners, and
      per-account recovery readiness.
- [ ] Packaged browser verification covers owner/non-owner boundaries, failed
      create retention, role editing, profile changes, MFA enrollment and
      recovery, session revocation, and responsive layouts.

## 2.0.22 — Privacy and integrations acceptance

- [x] Visitors can reopen consent choices and withdraw analytics permission;
      optional analytics cannot be saved without an explicit choice banner.
- [x] Public privacy intake retains invalid values, uses CSRF, honeypot and
      rate limiting, returns a reference, and is private/noindex.
- [x] Requests require recorded identity verification before completion and
      retain timestamps, resolution evidence, reopening, and audited safe CSV.
- [x] API keys remain hashed, scoped, one-time-visible, revocable, and safely
      rotatable with the prior credential revoked after replacement creation.
- [x] Webhooks support public-HTTPS validation, event editing, HMAC-secret
      rotation, signed test delivery, enable/disable, and failed retry.
- [x] Portable imports are capped at 5 MB and exclude credentials, accounts,
      submissions, and other private data.
- [ ] Packaged browser verification covers consent changes, request lifecycle,
      CSV, key/webhook rotation, tests, retries, site transfer, and mobile UI.

## 2.0.23 — Modules and marketplace acceptance

- [x] Core/base is always shown as enabled, built-in, required, and has no
      install/disable/uninstall/purge controls.
- [x] Optional packages expose staged/install/upgrade/enable/disable/uninstall
      and separate typed-confirmation purge states with dependency feedback.
- [x] Package staging validates archive paths, size, manifest, compatibility,
      integrity and trust before code runs; failed upgrades restore old files.
- [x] Recovery mode boots Core only and reports actual flag-write/removal
      failures with shared-host File Manager recovery guidance.
- [x] Marketplace sources require public HTTPS and signed catalogs, reject
      duplicates, and support refresh, disable, re-enable, and safe removal.
- [x] Package state, filesystem-version mismatch, issues, and recent operation
      evidence remain visible and filterable.
- [ ] Packaged browser verification covers lifecycle actions, dependency and
      rollback errors, recovery mode, source lifecycle, and responsive UI.

## 2.0.24 — Backups and operations acceptance

- [x] Every backup is integrity-inspected and displays verification, Core
      version, table/file counts, size, and a useful invalid-archive error.
- [x] Required staging/read/copy failures stop backup or restore instead of
      silently producing or accepting incomplete recovery data.
- [x] Restore is owner-only and requires a safety snapshot, maintenance flag,
      exclusive lock, safe archive members, checksums, prefix and driver match.
- [x] Local creation evidence remains accurate when optional remote
      replication fails, and deletion reports real filesystem outcomes.
- [x] Operations prevents duplicate queued backups, records cron heartbeat,
      recovers expired leases to uncertain, and retries only safe states.
- [x] Maintenance mode preserves admin access and reports shared-host flag
      write/removal failures with actionable manual guidance.
- [ ] Packaged browser verification covers create/inspect/delete/restore,
      remote failure, queue retry/recovery, schedules, cron and responsive UI.

## 2.0.25 — Health, updates, audit, and settings acceptance

- [x] Health collectors fail independently and expose database, storage, disk,
      jobs, backups, packages, integrity, mail, HTTPS, PHP extensions and cron.
- [x] Update GET never parses an archive; explicit stage verifies archive,
      signature, manifest, version, replay floor, hashes and protected paths.
- [x] Review stores the staged SHA-256; apply rechecks it, creates rollback
      files, uses an exclusive lock, runs migrations, and restores replaced
      files if migration fails. Staged files can be deliberately discarded.
- [x] Audit uses the canonical schema, redacts sensitive context, searches
      context/IP/event, filters families, paginates, and exports safe CSV.
- [x] Settings validates the whole website/mail change before writes, preserves
      non-secret invalid input, never renders SMTP password, tests mail, and
      rotates cron credentials with confirmation.
- [ ] Packaged browser verification covers degraded health, update stage/hash/
      discard/apply/rollback, audit search/export, settings errors and mail.

## 2.0.26 — Agency management acceptance

- [x] Connections reject duplicate canonical URLs and support identity edits,
      encrypted key rotation, pause/re-enable, and confirmed removal.
- [x] Secrets are encrypted at rest, never redisplayed, and setup guidance
      requires a least-privilege remote `health.read` credential.
- [x] Every check revalidates public HTTPS resolution and uses verified TLS,
      no redirects, strict timeouts, HTTPS protocol limits, and bounded bodies.
- [x] Authentication, endpoint, transport, and remote-server failures produce
      safe actionable messages without exposing credentials or cURL output.
- [x] Failed checks preserve last known healthy identity/version inventory and
      record check time, latency, and consecutive failures.
- [x] The responsive dashboard summarizes active, healthy, attention, and
      older-Core state with inline connection management.
- [ ] Packaged browser verification covers add, duplicate, invalid URL/key,
      successful/failed check, rotate, pause, re-enable, and remove.

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

