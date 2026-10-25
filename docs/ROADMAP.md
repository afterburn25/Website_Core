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
| 1.4 | reusable page patterns, layout variants and parent-theme overrides | complete | commit “Add the 1.4 layout pattern system” |
| 1.5 | consent manager, gated analytics and privacy request operations | complete | commit “Add the 1.5 privacy and consent platform” |
| 1.6 | SEO previews, schema editor, redirect portability and content checks | complete | commit “Build the 1.6 SEO and accessibility toolkit” |
| 1.7 | multilingual pages, localized URLs, language navigation and metadata | complete | commit “Add the 1.7 multilingual content system” |
| 1.8 | custom roles, editorial approval and revision comparison | complete | commit “Add the 1.8 roles and editorial workflow” |
| 1.9 | API keys, webhooks, site transfer and remote backups | complete | commit “Build the 1.9 integration platform” |
| 2.0 | agency site registry, marketplace catalog, managed update feeds and visual navigation tree | complete | commit “Release the 2.0 agency and managed platform” |
| 2.0.1 | module-owned navigation defaults imported into Core's editable tree | complete | commit “Let modules define editable site navigation” |
| 2.0.2–2.0.32 | screen-by-screen hardening, hosted repairs, analytics and mail acceptance | code complete; remaining browser matrix tracked separately | `docs/COMMERCIAL_HARDENING.md` |
| 2.1 | opt-in commerce, manual payments and customer accounts | complete; CI verified | `docs/2.1.0_RELEASE_NOTES.md` |
| 2.2 | shared business profile, module admin helpers, inquiries and quotations | implementation complete; CI verified; packaged-host acceptance pending | `docs/2.2.0_RELEASE_NOTES.md` |
| 2.3 | reusable module-record ordering and bulk actions | implementation complete; CI verified; packaged-host acceptance pending | `docs/2.3.0_RELEASE_NOTES.md` |
| 2.4 | seed-once module content, Core Media fields and safe structured-data contributions | implementation complete; CI verified; packaged-host acceptance pending | `docs/2.4.0_RELEASE_NOTES.md` |
| 2.5 | package starter forms, custom validation, protected submission links/events and notification templates | implementation complete; CI verified; packaged-host and cross-industry adoption pending | `docs/2.5.0_RELEASE_NOTES.md` |

New shared features remain dormant unless an enabled module declares them.
The ordered audit in `docs/COMMERCIAL_HARDENING.md` remains the acceptance
roadmap and must be updated as each packaged surface is verified.

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

## Shared services for industry modules — planning backlog (2026-10-25)

Owner direction: shared behavior should be implemented once in Core and used
by industry modules. Catering is developed independently in
`Core_Mods:module/catering`. Record missing Core capabilities here; do not
implement Core changes or synchronize catering snapshots from the catering
session. This section records delivered contracts and remaining reuse work.
Existing commercial-hardening gates remain.

The current implementation is on `devin/website-core`; `main` still contains
the initial scaffold. Avoid maintaining a second implementation backlog on
the old branch.

### Reuse existing Core services first

Core already supplies page composition/revisions, forms/submissions,
Media, navigation/branding, administrator identities/permissions, queued
mail, audit, backups, search/dashboard contributions, SEO infrastructure,
privacy, and analytics. Modules should use those services through documented
APIs rather than build parallel systems. Catering still contains its own
contact/quote handlers, image whitelist, hard-coded service/FAQ content,
business phone setting, and shared-view setup. Migrate incrementally with
preservation of existing content, inquiry history, IDs, and customizations.

| ID | Shared requirement | Acceptance criteria / catering dependency |
| --- | --- | --- |
| CORE-REUSE-1 | Stable module-facing service API and shared public/admin rendering helpers | **2.2 foundation delivered:** `adminView()` supplies the complete shared shell and enforces capabilities. Adoption by two industries remains. |
| CORE-REUSE-2 | Reusable record editor and list components | **Delivered through 2.3:** `adminResource()` supplies fields/columns, validation, filtering, pagination, confirmation, CSRF, permissions, audit, visible-row ordering and allowlisted bulk actions while callbacks retain module persistence and ownership rules. Cross-industry adoption remains. |
| CORE-REUSE-3 | Module integration with existing content, media and SEO schema | **2.4 foundation delivered:** declared content types now support durable seed-once defaults plus Core Media single/multiple fields with public-record validation and usage tracking. Packages can register bounded Schema.org providers; Core validates allowlisted types/properties, supplies `@context`, emits markup-safe JSON-LD and isolates provider failures. Existing page-block editing, previews and revision recovery remain the page-composition contract. Catering adoption, visual browser acceptance and proof with a second industry remain. |
| CORE-REUSE-4 | Module integration with existing forms and notifications | **2.5 foundation implemented:** `starterForm()` declares or explicitly adopts edit-preserving Core forms; package callbacks add business validation and receive fault-isolated protected submission IDs; `formSubmission()` and `linkFormSubmission()` enforce package ownership. Core retains CSRF, honeypots, rate limits, generic validation, consent evidence, inbox/status, editable notification templates and queue evidence. Browser retries deduplicate submissions, events and mail. Catering adoption with preserved event/headcount/dietary rules and existing inquiry history, visual browser acceptance and proof with a second industry remain. |
| CORE-REUSE-5 | Shared business profile and reusable presentation blocks | **Profile delivered in 2.2:** one editable identity/contact/location/hours/service-area record plus seed-once module defaults. Reusable FAQ/gallery/testimonial/service/team/CTA blocks remain. |
| CORE-REUSE-6 | Optional feature declarations and dependency enforcement | **Delivered through 2.1–2.2:** declarations control navigation, permissions, routes and data partitions for commerce, accounts, inquiries and quotes. Broader service-version constraints remain. |
| CORE-REUSE-7 | Common enquiry and quotation services | **2.2 foundation delivered:** package-scoped inbox, assignment, priorities, notes, submission deduplication, itemized totals, expiry and secure acceptance. Attachments, reminders and generated documents remain. |
| CORE-REUSE-8 | Shared SDK scaffold and contract test kit | Generate the manifest/provider, admin contributions, migrations, docs and module-only package once; validate compatibility, install/upgrade preservation, access controls and rendering with two different industries. Integration waits for explicit owner instruction; the module ZIP remains directly installable. |
| CORE-REUSE-9 | Shared geocoding and service-radius maps | Extend the Core-owned business profile with a structured service-area center (city, state/region and optional country) and distance radius in miles, resolved through a configurable server-side geocoding provider. Store validated normalized coordinates, provider/precision metadata and cache timestamps; never expose provider secrets or geocode on each public request. Provide a documented Module API service plus an accessible, responsive public map component that draws the radius, includes required provider attribution, supports keyboard/no-JavaScript and text fallbacks, honors privacy/consent rules, and fails safely when lookup or tiles are unavailable. Catering must use this contract for its public Service Area map while retaining postal delivery-zone validation. The service must also accept a customer event/delivery address, geocode it server-side, calculate its distance from the saved center, and return a structured inside/outside/unknown result so modules can show an accessible out-of-area error and block strict-radius submissions without relying on JavaScript. Ambiguous addresses must request correction; provider failures must return unknown/manual-review rather than falsely claiming the address is outside. Permit an audited staff override for approved exceptions. Prove the same contract with Landscaping and test boundary distances, invalid/ambiguous locations, radius bounds, tampered client values, caching/refresh, provider rate limits, mobile layout and unavailable-provider behavior. |
| CORE-REUSE-10 | Module theme collections, layout variants and palette families | Extend Core's theme/layout system so an industry module can register a collection of named public themes through a documented, versioned API. A theme may provide genuinely distinct responsive page compositions, module-scoped assets and Core Media slots rather than only recoloring one shared layout. Core owns the searchable visual chooser, desktop/mobile preview, draft/publish/rollback state, active-theme persistence, palette-token editor and accessible contrast validation. Each theme may declare multiple curated palettes and save its own media assignments/overrides; switching themes must preserve content, navigation and administrator-selected media, and bundled defaults must never overwrite later customization. Reject unsafe paths/templates/tokens, keep inactive theme assets from loading publicly, and provide deterministic fallbacks after disable/uninstall. Catering will prove the contract with ten structurally distinct layouts, ten distinct default image assignments and at least four accessible palettes per layout; verify keyboard operation, reduced motion, responsive breakpoints, Core attribution, cache invalidation, performance budgets, upgrades and adoption by a second industry module. |
| CORE-REUSE-11 | Module-aware commerce checkout validation | Extend Core commerce with a documented, server-side package validation hook that receives the package-owned cart snapshot, requested date/time, fulfillment method, saved or entered address, authenticated customer and payment context before order creation. The hook returns field-scoped errors, manual-review/quote-only outcomes and an immutable validation snapshot; Core must rerun it atomically at final checkout and reject stale/tampered client results. Catering will use it to enforce menu/direct-order eligibility, service style, lead time, blackout dates, capacity, pickup/delivery windows, postal zones, `CORE-REUSE-9` radius results, minimums and manual pricing while routing complex events back to quotations. Provide package isolation, timeout/failure-safe behavior, audit records, accessible retained-input errors, account/reorder support, concurrent-capacity tests and proof with a second industry; modules must not replace Core cart, checkout, orders, accounts, invoices or payments. |

### Commercial operations follow-ups

| ID | Requirement | Acceptance criteria |
| --- | --- | --- |
| CORE-OPS-1 | Actionable SMTP provider-host discovery | When DNS and TCP succeed but the encrypted SMTP handshake fails, safely inspect the resolved server's reverse/provider hostname. Suggest it only when forward DNS maps it back to the same server address; never weaken certificate checks or save the suggestion automatically. Also test and report the standard SSL/465 and STARTTLS/587 pair separately so the owner receives a concrete hostname/encryption correction instead of a generic connection error. Add unit coverage for unverified PTR results, timeouts, and secret-free diagnostics, then verify on shared hosting. |
| CORE-OPS-2 | Verified mail-certificate deployment handoff | Track the external Privora Command Center dependency exposed while provisioning `mail.cajunwebpro.com`: an ACME client success is not a completed AutoSSL operation until the issued certificate and private-key files are located, readable, cryptographically matched and verified for the requested hostname. The control panel must support the ACME client's reported output plus common RSA/ECC layouts without relying on one hard-coded directory; install the full chain and key atomically with mail-service ownership and restrictive permissions; bind them to SMTP and IMAP; reload the affected services; and verify the certificate presented on SSL/465 and STARTTLS/587. Report DNS validation, ACME issuance, file discovery, installation, binding, reload and final handshake as separate states with secret-free errors. A retry must be idempotent, retain the last valid certificate on any failure and never report success when files are absent. Core keeps strict certificate verification and consumes only the verified hostname/result; it does not receive server certificate keys or attempt privileged repair. |

CORE-OPS-1 implementation: **2.2.1 implemented; CI verified** adds a separate credential-free
diagnostic action with forward-confirmed provider discovery, pinned-address
sockets, strict TLS checks, separate SSL/465 and STARTTLS/587 results, and
bounded network probes. CI run 535 passed all six jobs; real shared-host
verification remains pending.
No provider correction or successful email delivery has been claimed.

CORE-OPS-2 state: **external Privora/server repair required.** Live provisioning
reached ACME success, but Privora reported that the certificate files were
missing before SMTP/IMAP deployment. This is no longer a DNS-pending or SMTP
credential problem. Website Core cannot install or bind privileged mail-server
certificates; hosted acceptance for `mail.cajunwebpro.com` remains blocked until
Privora completes the deployment handoff and both encrypted SMTP ports present
a certificate valid for that hostname.

### Delivered common product services

Core 2.1 delivers optional shared catalog/products, cart, checkout/orders,
customer accounts and manual Venmo/PayPal/Cash App payment methods, with no
cash option. Customer accounts and commerce are enabled only when the selected
module supports them.
A manual payment selection records an unpaid/pending-verification order; it
does not imply payment-provider confirmation. Industry-specific choices and
pricing extend shared services rather than copying a cart or account system.

### Contract alignment

`CORE_BOUNDARIES.md`, the Module API, rules, and contract tests now recognize
the opt-in commerce, account, inquiry, quote, and shared-profile services.
Core remains industry-neutral;
shared optional services do not move catering menus, recipes, guest rules,
dietary logic or event operations into Core.

### CORE-REUSE-7A — Inquiry detail schema regression (2026-10-25)

Catering module acceptance against Core 2.2.0 and source inspection of 2.2.1
found that `Core\Leads\InquiryService::find()` joined `admins` using
`a.name AS admin_name`, but the canonical administrator schema uses
`username`. SQLite raises `no such column: a.name`, preventing the shared
inquiry detail and notes screen from opening after successful submissions.

- Repair the display-name lookup using supported administrator fields on
  SQLite, MySQL and MariaDB; preserve existing inquiry and note records.
- Add regression coverage that submits an inquiry, opens its detail with zero
  notes, adds a note, then reopens it with the author's display name.
- Verify package ownership isolation and the rendered admin detail screen.
- Catering keeps its module questionnaire/menu snapshot and links it through
  the existing service; it must not add a duplicate inbox or patch Core here.

State: repaired in Core 2.2.2. The query and administrator selector now use
the canonical `username` field on SQLite, MySQL and MariaDB. Regression
coverage verifies zero-note detail, note attribution, package isolation and
rendered output. No migration or combined module installer changed.
