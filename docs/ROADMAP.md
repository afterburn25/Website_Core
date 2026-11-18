# Roadmap

Historical delivery milestones and planned enhancements for the UNIVERSAL CORE.
Stages adapt to real dependencies; scope is preserved, not deferred silently.

**New feature plan:** [Future enhancements (2026-11-18)](#future-enhancements-2026-11-18).
Existing commercial hardening and release acceptance retain immediate priority.

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
| 2.6 | reusable presentation, service contracts, quote workflows, SDK, service areas, module theme collections and checkout validation | Core implementation complete; CI verified; packaged-host and cross-industry adoption pending | `docs/2.6.0_RELEASE_NOTES.md` |
| 2.6.1 | dedicated city/state/mileage Geo service-area administration and unambiguous audience-location analytics naming | implementation complete; CI and packaged-host acceptance pending | `docs/2.6.1_RELEASE_NOTES.md` |
| 2.6.2 | module-declared administration icons plus visual industry-theme cards, thumbnails, palette dropdowns and direct activation | implementation complete; CI and packaged-host acceptance pending | `docs/2.6.2_RELEASE_NOTES.md` |

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

Historical branch note (2026-10-25): this reuse work was developed on
`devin/website-core` while `main` contained the initial scaffold. Consult
[`PROJECT_STATUS.md`](../PROJECT_STATUS.md) and current repository refs for
integration status before choosing an implementation branch.

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
| CORE-REUSE-5 | Shared business profile and reusable presentation blocks | **Core delivered in 2.6:** canonical editable FAQ/gallery/testimonial/service/team/CTA composer definitions and safe responsive renderers build on the 2.2 profile. Packaged visual acceptance and two-industry adoption remain. |
| CORE-REUSE-6 | Optional feature declarations and dependency enforcement | **Core delivered in 2.6:** manifests may require named semantic Core-service contracts in addition to Core/API/PHP and package dependencies. Missing or incompatible services quarantine the package before provider code runs. Cross-industry adoption remains. |
| CORE-REUSE-7 | Common enquiry and quotation services | **Core delivered in 2.6:** package-isolated protected Media attachments, idempotent queued reminders, delivery jobs and integrity-checked immutable package-rendered proposal/contract/summary snapshots extend the 2.2 quote pipeline. Browser and module adoption remain. |
| CORE-REUSE-8 | Shared SDK scaffold and contract test kit | **Core delivered in 2.6:** one command generates a manifest/provider, shared-admin contribution, migration, fallback view, contract test and docs; the SDK packager creates a directly installable root-level ZIP and rejects nested ZIPs. Existing lifecycle/round-trip tests prove install, enable, access registration and rendering; two-industry adoption remains. |
| CORE-REUSE-9 | Shared geocoding and service-radius maps | **Core delivered in 2.6:** the shared profile accepts center/radius data through a registered server-side provider, caches normalized coordinates/precision, computes inside/outside/unknown distance results, fails safely for ambiguous/provider failures, records audited staff overrides and renders an accessible attributed no-JavaScript radius component. Catering/landscaping provider registration, postal-rule composition and packaged visual/provider acceptance remain. |
| CORE-REUSE-10 | Industry website themes, distinct layouts and palette families | **Core delivered in 2.6 and refined in 2.6.2:** modules register confined layouts/assets, visual thumbnails, accessible palette families and Media slots. Core presents searchable theme cards with a palette dropdown and direct Activate action while retaining safe draft/publish/rollback persistence, path/token/contrast validation and deterministic fallback. Catering's ten layouts/images/four palettes each, browser/performance acceptance and second-industry adoption remain module work. |
| CORE-REUSE-10A | Preserve per-layout theme selections for inactive variants | Extend `ModuleThemeCollections` so each module package retains palette and Media-slot selections keyed by layout/variant, including layouts that are neither the current draft, published version nor rollback predecessor. The visual chooser must repopulate each layout's saved values; switching, publishing, rollback and Core upgrades must not overwrite inactive selections. Migrate the existing draft/published/previous state without loss, preserve package isolation and Media usage/deletion tracking, audit state changes, and add unit, migration, browser and two-industry contract coverage. |
| CORE-REUSE-11 | Module-aware commerce checkout validation | **Core delivered in 2.6:** package hooks receive bounded cart/date/time/fulfillment/address/account/payment context, return field errors or manual-review/quote-only outcomes, fail closed on exception/timeout, rerun inside the order transaction and store immutable evidence without customer PII. Catering rule adoption, concurrent live capacity proof, reorder/browser acceptance and second-industry proof remain. |
| CORE-REUSE-12 | Distinct module administration navigation icons | **Core delivered in 2.6.2:** `adminPage()` accepts a validated optional icon token, preserves the existing `modules` fallback for older packages, and passes the declaration to the shared administration shell. Catering adoption and packaged visual acceptance remain. |

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

## Future enhancements (2026-11-18)

**Owner direction:** add the complete Website Core feature plan discussed on
2026-11-18 to this roadmap. **Status: planned enhancements; implementation
scope and delivery estimates remain to be assessed.** The existing composer,
media library, drafts/revisions, forms, analytics, permissions, recovery,
package contracts, commerce, customer identities and inquiry/quotation
services are the starting point. These entries do not classify those
foundations as missing or change their recorded implementation status.

The current [commercial-hardening program](COMMERCIAL_HARDENING.md) and
[release-readiness evidence](2.6.2_RELEASE_READINESS.md) retain immediate
priority. This backlog does not make every future enhancement a prerequisite
for the current release. Map each item to existing code and acceptance
evidence before estimating or implementing the remaining work; extend the
supported service rather than create a parallel implementation. Assign
versions and dates after that assessment.

### Delivery order

| Order | Work | Intended result |
| --- | --- | --- |
| 0 | Existing commercial hardening and release acceptance | Finish the current supported release against its existing gates. |
| 1 | CORE-NEXT-01 + CORE-NEXT-04: visual editing, hero controls and media previews | Customers can make common content and design changes confidently. |
| 2 | CORE-NEXT-02: reusable site presets and guided setup | Reduce repeated setup work for each new customer site. |
| 3 | CORE-NEXT-03: coordinated preview and publishing | Review and publish related content/design changes together. |
| 4 | CORE-NEXT-05 + CORE-NEXT-06: actionable administration and business reporting | Show what needs attention and which website activity produces business outcomes. |
| 5 | CORE-NEXT-09: module development and compatibility | Make each additional industry easier to implement and maintain. |
| 6 | ADDON-NEXT-01 through ADDON-NEXT-09: optional business and operational tools | Schedule against concrete customer needs and dependencies. |

CORE-NEXT-07 (publishing readiness) and CORE-NEXT-08 (maintenance/recovery)
support the relevant phases. Existing defects in those areas remain part of
current hardening; their new enhancements are separately planned. Reuse the
existing module contracts throughout, even before the later SDK improvement
phase. AI follows the editing, preview, media and setup improvements.

### Ownership and architectural boundaries

[Core boundaries](../CORE_BOUNDARIES.md) and the
[Module API](MODULE_API.md) remain authoritative.

| Layer | Responsibility |
| --- | --- |
| Universal Core | Installation, administration, permissions, pages, media, navigation, rendering, recovery and extension infrastructure. |
| Explicitly enabled Core services | Existing shared commerce, public customer identity, inquiries, quotations and service-area foundations. Preserve capability gating and the separation of administrator and public customer identities. |
| Reusable optional add-ons | Booking, invoicing, advanced automation, customer portals, newsletters, AI, provider integrations and full staging operations. |
| Industry modules | Industry records, behavior and calculations, including catering menus/guest counts/dietary rules and landscaping measurements/service rules. |
| Themes | Presentation, layouts and supported visual choices; no authoritative business rules or records. |
| Cajun Web Pro platform | License sales, financing, hosting procurement, reseller billing and broader management of customer sites. These are external responsibilities, not new Core business engines. |

Cross-industry reuse alone does not make a feature mandatory Core
functionality. Packages use supported APIs, declare dependencies and retain
their industry or provider-specific behavior. Preserve the fixed Cajun Web
Pro attribution and existing package, privacy, permission and data-ownership
contracts.

Preserve manual **Venmo, PayPal and Cash App** payments, with **no cash
option**. A manual payment selection remains unpaid/pending verification
until payment is actually confirmed. Gateway connections remain optional
packages; base checkout must not require a gateway account.

### CORE-NEXT-01 — Complete the visual editing experience

**State:** planned enhancement. **Priority:** 1.
Build on the existing page composer, shared sections and theme controls.

- Select a section in the preview to open its settings, with immediate visual
  feedback for text and image changes.
- Reorder, duplicate, hide, restore and save sections for reuse on other pages.
- Provide desktop, tablet and phone previews.
- Expose supported spacing, colors, button styles and section backgrounds;
  clearly distinguish page-specific settings from website-wide settings.
- Provide a coherent hero/navigation panel: per-page hero images, desktop
  and mobile crop previews, supported heights by screen size, text/button
  placement, readable overlays, navigation alignment/height/background
  opacity and sticky behavior.
- Preview a theme across representative pages before publishing.
- Core supplies editing/rendering contracts; themes and industry modules
  declare the controls their layouts support and own their presentation
  assets. Reuse CORE-REUSE-10/10A for layout and inactive-variant selections.

**Acceptance:** supported controls visibly affect the selected target; edits
survive save, reload, preview and publication; revisions recover prior
content; page/global scope is unambiguous; representative Core-only and
industry layouts work on desktop and mobile with keyboard-accessible
controls. Preserve customer content, saved variant choices and attribution
when changing themes. Do not display controls a layout cannot honor.

### CORE-NEXT-02 — Reusable site presets and guided customer setup

**State:** planned enhancement. **Priority:** 2.
Build on installation, the shared business profile and seed-once package
content instead of introducing a second settings store.

- Gather business/contact details, opening hours, service area, logo, brand
  colors, theme, required pages and the main visitor action (call, request a
  quote, book or shop).
- Enable only supported optional capabilities and prepare pages, navigation,
  contact information and starting settings for review.
- Save successful reusable configurations as presets: a simple five-page
  business site, a service/estimate site, a catalog/quote site, catering and
  landscaping.
- Record required packages and compatible versions. Include reusable design
  and sample content; exclude customer records, private submissions,
  credentials and license keys.
- Keep customer edits authoritative after initial seeding. Preset import must
  explain what it will create or change and preserve existing records.

**Acceptance:** a preset can prepare a fresh site with its declared packages
and consistent business information; incompatible dependencies produce
actionable errors; exported presets contain no customer secrets or records;
reapplying a preset does not silently overwrite customer edits. Preserve the
shared-host runtime and directly usable package requirements, including the
prohibition on nested ZIPs.

### CORE-NEXT-03 — Coordinated preview and publishing

**State:** planned enhancement. **Priority:** 3.
Extend existing drafts, previews, revisions and theme selection recovery into
a reviewable set of related content and design changes.

- Group changes to pages, heroes, navigation links, banners, button text and
  supported theme settings.
- Show all pending changes and before/after comparisons.
- Provide private preview links with expiration, scheduled publication and
  concurrent-edit warnings.
- Publish the approved set coherently and recover its prior content/design
  state without reverting unrelated business activity.
- Keep preview authorization and scheduling permissions explicit and use the
  existing job, audit and revision infrastructure.

**Acceptance:** private previews show the complete intended set without
exposing drafts publicly; expiration is enforced; conflicting edits are
identified; interrupted or repeated publication does not leave a mixed
content set. Scheduled publication is auditable and repeat-safe. Recovery
preserves inquiries, orders and other customer activity. Full staging-site
creation and promotion are tracked separately as ADDON-NEXT-09.

### CORE-NEXT-04 — Smarter media management

**State:** planned enhancement. **Priority:** 1.
Extend existing focal points, image derivatives, protected media, usage
tracking and replacement workflows.

- Show desktop/mobile crops side by side and recommended proportions for
  each placement.
- Warn when an image is too small for its intended display.
- Generate optimized image sizes while preserving the original.
- Support a separate mobile image when a layout declares that capability.
- Detect duplicate uploads.
- Preview a replacement with the list of affected pages and placements.
- Show storage use, large files and unused assets.

**Acceptance:** users can see the actual crop before saving; generated
variants retain appropriate proportions and work on supported hosts;
originals remain available; replacements update the intended references;
duplicate detection does not merge distinct files incorrectly. Preserve
private-file access controls, media usage/deletion protections and original
customer content. Cover page, module and theme-slot usages.

### CORE-NEXT-05 — Actionable administration dashboard and search

**State:** planned enhancement. **Priority:** 4.
Build on the existing dashboard/search contribution contracts.

- Present relevant cards for unanswered inquiries, expiring quotes, failed
  notifications, backup problems, missing images/broken links, updates ready
  for review and content awaiting publication.
- Adapt cards and actions to enabled capabilities and the signed-in user's
  permissions. Link each item to its relevant workflow.
- Provide one administration search across settings, page titles, media
  filenames, inquiry references and other permitted records.
- Support task-oriented discovery such as "change phone number", "homepage
  image" and "business hours" using indexed labels and aliases; AI is not
  required.

**Acceptance:** a Core-only information site has an appropriately small
dashboard; enabling a module adds its declared contributions; cards show
real state and working destinations. Search and summary counts enforce
record/package permissions, do not reveal private or inaccessible results,
and do not expose disabled capability routes.

### CORE-NEXT-06 — Reporting tied to business results

**State:** planned enhancement. **Priority:** 4.
Extend existing privacy-aware analytics and supported business records.

- Report completed inquiries, their originating pages and available campaign
  attribution.
- Report quotes sent/accepted and confirmed orders where the relevant
  services are enabled.
- Track phone/email-link clicks with their actual event meaning.
- Identify form abandonment through proportionate, consent-aware events,
  without recording sensitive unfinished field values.
- Connect each result to the relevant pages, campaigns or permitted workflow.
- Add search-performance/query reporting through an optional Search Console
  connector under ADDON-NEXT-07, with clear reporting limitations.

**Acceptance:** define and document each metric; avoid duplicate events;
respect consent, retention and access controls. Link clicks are not reported
as completed calls, accepted quotes as paid orders, or manual payment
selections as confirmed revenue. Unavailable or omitted query data is shown
as unavailable, not invented or implied complete. Keep third-party tracking
and provider integrations outside the mandatory Core.

### CORE-NEXT-07 — Helpful checks before publishing

**State:** planned enhancement. **Priority:** supports editing and publishing.

- Consolidate existing SEO, accessibility and content checks into a page
  readiness review.
- Flag missing titles/descriptions, broken internal links, missing images,
  incomplete business contacts, missing meaningful image descriptions,
  heading/form-label issues, insufficient contrast, unusually large images
  and potentially accidental noindex settings.
- Link findings directly to their editable content or setting and recognize
  intentional settings and decorative-image cases.
- Draw supported structured data consistently from the shared business
  profile and bounded package contributions.
- Use WCAG 2.2 AA as the design/acceptance target, with automated checks plus
  keyboard and other manual evaluation.

**Acceptance:** findings identify a concrete page/field and can be resolved
through working controls; rerunning checks reflects the change. Preserve
intentional indexing choices and safe structured-data encoding. Automated
checks do not claim complete accessibility conformance, and structured data
does not promise a particular ranking or search-result appearance.

### CORE-NEXT-08 — Understandable maintenance and recovery

**State:** planned enhancement. **Priority:** supports current hardening and
future release phases; existing defects retain their current priority.

- Summarize Core/module/theme compatibility before updates and explain
  failures with an actionable next step.
- Show the last successful backup and restore-verification evidence,
  distinguishing archive inspection from an actual successful restore.
- Preview restoration scope, including files, database/schema changes,
  uploads and business records.
- Provide a redacted support report and Core/module/theme change history.
- Link health warnings to the relevant repair or recovery destination.
- Explain notification failures separately from saved inquiry/submission
  status, including available delivery/retry information.
- Use OWASP ASVS as a structured reference for shared security requirements
  and verification.

**Acceptance:** displayed status is backed by recorded outcomes; failures
remain diagnosable and support exports omit secrets. Recovery previews
accurately explain affected data and consequences before execution.
Compatibility and recovery work preserve signing, permission, backup and
package protections; verify the documented restore path on supported
packaged hosts. Never describe a file-only restore as complete recovery
when database changes or business records are outside its scope.

### CORE-NEXT-09 — Module development and compatibility

**State:** planned enhancement. **Priority:** 5.
Improve the existing SDK, shared services and contract kit.

- Expand examples for common module features.
- Show a capability report, unsupported dependencies and compatible Core/API
  versions before installation.
- Preview the administration screens and permissions a package will add.
- Standardize module dashboard contributions.
- Provide a repeatable acceptance process covering installation, editing,
  theme changes, updates, disabling/re-enabling, restoration and removal.
- Reuse shared record editors, forms, content, media, quotations and
  service-area services while modules retain their own industry fields,
  validation, calculations and records.

**Acceptance:** the shared contracts work in a Core-only install and with
representative different industries; modules integrate through documented
APIs without editing Core routers or accessing another package's private
tables. Compatibility failures are actionable before activation. Lifecycle
verification demonstrates preserved customer content/data and permissions,
including data-preserving removal and separately authorized purge. Package
outputs remain directly installable with no nested ZIPs.

### Optional add-on backlog

All entries below are **planned optional capabilities**. Reuse existing Core
identity, permission, job/outbox, notification, document-snapshot and business
record services where appropriate; do not duplicate them or move specialized
business engines into always-on Core.

| ID | Capability | Planned scope and acceptance focus |
| --- | --- | --- |
| ADDON-NEXT-01 | Appointments and availability | Time slots, blackout dates, reminders and booking requests for consultations, salons or service visits. Keep industry capacity, scheduling and fulfillment rules in the responsible package; verify permission checks and conflicting/resubmitted booking behavior. |
| ADDON-NEXT-02 | Invoicing and deposits | Invoices, balances due, deposit requests and receipts for uses such as catering deposits and landscaping projects. Link to shared quotes/orders where supported; distinguish requested, pending and confirmed payments; avoid duplicating checkout or embedding gateway SDKs in Core. |
| ADDON-NEXT-03 | Customer portal | A customer-facing workspace for documents, requests and supported transactions, including quote review, project files and order details. Reuse opt-in public customer identity; enforce ownership on every record/file and keep administrator identity separate. |
| ADDON-NEXT-04 | Follow-up automation | Rules for reminders, assignments and follow-up tasks, such as unanswered-inquiry reminders. Build on existing reminders and jobs; keep advanced business rules in the add-on; provide auditable, repeat-safe execution and visible delivery outcomes. |
| ADDON-NEXT-05 | Reviews and testimonials | Collection requests after completed work, moderation and publishing workflows. Reuse existing presentation blocks; preserve submitted content and explicit moderation/publication state. |
| ADDON-NEXT-06 | Blog and news publishing | Articles, categories, authors and editorial publishing workflows for updates and educational content. Reuse Core revisions, previews, media and permissions while keeping article-specific records/workflows in the package. |
| ADDON-NEXT-07 | Advanced integrations | Optional accounting, calendar, payment-provider, Search Console and other business-system connections, including sharing accepted quotes. Provider SDKs/credentials/contracts stay in packages; use supported Core HTTP/webhook/jobs boundaries, scoped permissions and auditable delivery/retries. Preserve manual payment options and report incomplete provider data honestly. |
| ADDON-NEXT-08 | AI assistance | Drafting/rewriting, shorter headings, image-description suggestions, FAQs from approved business facts, content organization and finding outdated details such as old phone numbers. See the controls below. |
| ADDON-NEXT-09 | Full staging and promotion | Optional staging-site creation, review and controlled promotion beyond Core's content/design change sets. Isolate staging side effects and protect private data. Promotion must preserve live inquiries, orders and customer activity created since the staging copy; show what will change and support a verified recovery path. |

Newsletter functionality remains an optional add-on responsibility under the
existing boundaries; it is not introduced as a mandatory Core publishing or
mail engine by this plan.

#### ADDON-NEXT-08 — AI behavior and delivery controls

- Operate on approved business facts and selected content.
- Present generated material as drafts with previews and revision history.
- Provide configurable spending limits and a choice of supported providers.
- Keep publication an explicit action in the editing workflow.
- Support the proposed editing tasks without making AI a requirement for
  administration search, content editing or the base website.
- Schedule after the editing, preview, media and guided-setup improvements.

**Acceptance:** generated changes are reviewable and reversible; the editor
retains original content and identifies the proposed changes; provider
failure or an exhausted budget does not break normal site operation.
Verify permissions and the configured spending controls before enabling
the package for a customer.

### Planning and completion evidence

- Keep these stable IDs when splitting work into implementation issues or
  pull requests, and link each delivered slice back to the roadmap.
- First record the existing implementation and the remaining enhancement for
  each item. Reuse completed work and retain current hardening/release gates.
- Record implementation, automated verification, representative browser
  checks, packaged-host evidence and cross-industry adoption separately where
  applicable. A merged change alone is not commercial acceptance.
- Preserve the historical roadmap and existing requirements; update an item's
  state only with evidence. Do not assign invented completion percentages,
  release versions or delivery dates.
- This roadmap edit schedules planning work; it does not implement features
  or change runtime configuration, package snapshots or payment state.

### Reference standards and integration documentation

- [WCAG 2.2 quick reference](https://www.w3.org/WAI/WCAG22/quickref/)
  — accessibility criteria and implementation guidance.
- [W3C accessibility evaluation overview](https://www.w3.org/WAI/test-evaluate/)
  — automated tools require complementary manual evaluation.
- [OWASP Application Security Verification Standard](https://asvs.owasp.org/)
  — shared security requirements and verification reference.
- [Google LocalBusiness structured data](https://developers.google.com/search/docs/appearance/structured-data/local-business)
  — supported business data and search-appearance limitations.
- [Search Console report dimensions and data groupings](https://support.google.com/webmasters/answer/17011259)
  — search-query reporting and omitted data.
