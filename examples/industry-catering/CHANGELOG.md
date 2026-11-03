# Catering Module Changelog

## 2.1.0 — 2026-10-26

- Reworked all ten themes for a commercial-quality visual hierarchy while
  retaining ten distinct layout systems.
- Added thirty unique optimized theme photographs so no theme reuses another
  theme's hero, feature, or accent defaults.
- Rebuilt the ten Appearance thumbnails from the new photography packs.
- Standardized a compact 72-pixel sticky glass navigation treatment with 25%
  transparency, larger centered menu labels, visible active states, and
  responsive keyboard behavior.
- Added page-change feedback and prevented redundant reloads of the exact
  current navigation destination.
- Strengthened responsive composition, section separation, card treatments,
  focus visibility, and image propagation throughout inner pages.

## 2.0.2 — 2026-10-26

- Assigned a distinct Core administration icon to every Catering link:
  menus, packages, menu collections, events, production, settings, and the
  two Core-rendered quick managers.
- Raised the supported Website Core baseline to 2.6.2 for the validated
  module-icon navigation contract.
- Expanded the blocking route smoke matrix to every static public page, every
  service detail, every Catering administration destination, and an event
  detail so regressions cannot ship as uncaught page errors.
- Documented the required **Finalize update** step after files are replaced
  directly through a hosting file manager; this runs pending migrations and
  resolves schema-related page errors without discarding catering data.
- Replaced the generic layout-variant editor with a visual
  **Catering Themes** library: ten layout-specific thumbnails, one palette
  dropdown per theme, optional image overrides, and a direct Activate action.

## 2.0.1 — 2026-10-25

- Raised the supported Website Core baseline to 2.6.1 and pinned acceptance to
  merged Core commit `99d13cf09488ef4990345ce229c60b334b6ca6d2`.
- Added blocking contract coverage for Core's dedicated **Geo service area**
  route and its City, State / region, and Range from city (miles) controls.
- Kept Catering's existing server-side geocoder, public map, outside-address
  error, manual-review fallback, and checkout validation on the shared Core
  service-area contract; no module database migration was required.
- Rebuilt the standalone module package without Core files or nested ZIPs.

## 2.0.0 — 2026-10-25

- Adopted Core 2.6's shared service-area profile, server-side geocoding cache,
  accessible radius map, audited decisions, and checkout-safe address result.
- Added the exact accessible outside-address error to quote preview, quote
  submission, and direct-order checkout while routing unknown results to
  manual review and retaining postal delivery zones as an additional rule.
- Registered ten structurally distinct Catering layouts with unique default
  imagery, four contrast-checked palettes each, and Core Media slots for hero,
  feature, and accent images; public rendering follows Core's draft/published
  selection without adding a module theme manager.
- Added administrator-selectable quote-only, direct-order, and mixed modes.
  Explicitly eligible per-guest packages synchronize into Core Commerce, where
  Catering rules are atomically revalidated before order creation.
- Added Core Media fields for venue, event-story, and verified-review content,
  plus package-scoped Caterer, service, menu, FAQ, review, and service-area
  structured data.
- Added immutable Core-backed catering proposals and Banquet Event Orders,
  final-headcount reminders, and configurable tasting capacity/lead time.
- Raised the platform requirement to Website Core 2.6 and added contract tests
  for every newly adopted shared service. The archive remains Catering-only
  and contains no nested ZIP.

## 1.5.0 — 2026-10-25

- Added a confirmed-event workspace that synchronizes accepted Core quotes and
  preserves one event per detailed Catering questionnaire.
- Added event states, final-headcount deadlines, run of show, tasks, staffing,
  equipment/returns, delivery manifests, tastings, and revision acknowledgments.
- Added maintainable ingredients and recipe quantities with waste allowance,
  production station/date, container/label notes, scaled preparation sheets,
  and cross-event purchasing totals.
- Added estimated and actual operating-cost records plus accepted-quote revenue
  and margin comparisons without duplicating Core invoices or payments.
- Added structured delivery addresses, venue partners, event case studies,
  verified reviews, and focused school, nonprofit, holiday, and memorial pages.
- Kept radius maps/address geocoding, reusable theme collection management,
  documents/signatures/portal workflows, and guarded direct ordering blocked on
  shared Core contracts instead of shipping private duplicate engines.

## 1.0.0 — 2026-10-25

- Added server-authoritative service-style guest limits and lead times,
  blackout dates, request-capacity ceilings, fulfillment windows, delivery
  zones/fees/minimums, and optional fixed/per-guest service lines.
- Added immutable operations snapshots alongside the existing menu snapshot in
  module questionnaires, Core inquiry summaries, and queued notifications.
- Added no-JavaScript and progressive-enhancement support for combined food and
  configured operations estimates.
- Added release-readiness and pinned Landscaping contract-isolation checks.
- Published supported Core, upgrade, backup, rollback, launch, and daily
  operations guidance.

## 0.4.0 — 2026-10-25

- Add inclusive availability windows to dishes, packages, and reusable menu
  collections; out-of-window catalog records remain stored but are omitted
  from public pages and server-authoritative quote planning.
- Add reusable curated menu collections with validated dish membership and
  package links. Linked packages accept only current collection members while
  retaining their existing category choice limits.
- Add a protected Menu Collections workspace, relationship cleanup, public
  package details, planner guidance, and no-JavaScript-safe server validation.
- Defer module-supplied structured data until Core documents a safe SEO/schema
  contribution API under CORE-REUSE-3.

## 0.3.0 — 2026-10-25

- Add administrator-managed event types, service styles, and budget ranges to
  the quote planner without introducing a parallel generic form engine.
- Allow each Catering-specific planning question to be shown, hidden, or
  required while keeping identity, event, menu, consent, and contact essentials
  mandatory.
- Enforce the saved configuration server-side, discard disabled-field input,
  reject unsupported select values, preserve existing inquiry snapshots, and
  audit settings changes.
- Correct service-page quote links so their preselected event type matches the
  validated quote option.
- Keep generic form notifications, CSV export, reminders, attachments, spam
  reporting, and proposal documents on the existing Core reuse backlog.

## 0.2.1 — 2026-10-25

- Require Website Core 2.3 and adopt its shared `adminResource()` workspace
  for searchable dish/package quick management, pagination, accessible
  visible-row ordering and declared bulk visibility/featured actions.
- Keep advanced pricing, serving, discount-tier, dietary, allergen, image,
  guest-range and package-choice fields in Catering's detailed editors; quick
  edits preserve those fields and quick-created records require manual price
  confirmation.
- Validate every resource callback against module-owned tables, preserve
  unsubmitted ordering positions and retain Core's shared permission, CSRF,
  confirmation and audit workflow.
- Pin module acceptance to the CI-verified Core 2.3.0 source, require the fixed
  inquiry detail/notes contract, and add direct upgrade coverage from 0.2.0.

## 0.2.0 — 2026-10-25

- Reconcile the menu planner with the 0.1.25 Core 2.2 adoption, preserving the
  shared business profile, admin renderer and sales workspace.
- Fix quote-submission ID typing; serialize inquiry linking and include the
  submitted menu snapshot in Core's readable inquiry summary.
- Record Core's inquiry-detail schema defect as CORE-REUSE-7A; this candidate
  is not production-ready until that dependency passes acceptance.

- Add a dish/package menu planner to the quote workflow with server-computed
  preliminary food estimates and an accessible no-JavaScript preview.
- Add per-guest, tray and fixed food pricing, tray serving sizes, minimum
  quantities and guest-count discount tiers using integer-cent calculations.
- Add editable package guest ranges and included category choice limits;
  validate selections server-side without charging included dishes twice.
- Add structured dietary filters, business-supplied known allergens and
  cross-contact notes while preserving existing dietary display text.
- Add existing dish/package editors, retained validation errors, server-side
  permissions/CSRF, confirmed removal and Core audit records.
- Save an authoritative menu/price snapshot with each inquiry and include
  selected dishes and food estimates in Core-queued notifications.
- Keep estimates incomplete for manual pricing or unconfigured package dish
  allowances, and distinguish requests from bookings, orders and payments.
- Preserve edited legacy demo dishes/categories when their older Cajun-menu
  migration is still pending. Fix portable menu/category proposal URLs and
  invalidate assets using the module manifest version.
- Add isolated pricing, controller, database and exact-ZIP lifecycle checks,
  plus module-specific PHP/MySQL/MariaDB CI. No Core implementation, bundled
  catering snapshot or combined installer is changed.


## 0.1.25 — 2026-10-25

- Requires Website Core 2.2 and opts Catering into the Core-owned business
  profile, inquiry workspace, and itemized quotations.
- Seeds only blank shared business fields from existing settings, then reads
  the live Core profile for the public name, email, phone, and service area.
- Preserves detailed Catering questionnaires while linking existing and new
  records into the Core sales pipeline through `InquiryService`.
- Retires the duplicate Catering inquiry screen, dashboard/search
  contributions, and identity settings; old inquiry bookmarks redirect to the
  shared workspace.
- Retires the obsolete `catering.inquiries` permission; Core activates and
  enforces its shared `inquiries.manage` and `quotes.manage` capabilities.
- Uses Core's shared module-admin renderer for consistent permissions,
  navigation, notices, branding, and page presentation.

## 0.1.24 — 2026-10-24

- Adds a safe upgrade migration that completes missing search metadata on the
  Core starter home page while preserving any existing custom title or text.
- Replaces the untouched short “Welcome” label with the installed business
  name and supplies a useful catering search title and description.
- Keeps the public module-rendered home page unchanged; this repair brings the
  Core page record and SEO audit into agreement with the live catering site.

## 0.1.23 — 2026-10-24

- Re-optimized all ten Cajun dish photographs to 1000×667 WebP assets while
  preserving the approved visual quality and crop.
- Reduced the install archive well below common shared-hosting 2 MB request
  limits after the live site showed no stage or upgrade journal entry for the
  larger 0.1.22 upload.
- Kept the same portable Cajun data migration so sites still on 0.1.21 upgrade
  directly to the complete menu and photography set.

## 0.1.22 — 2026-10-24

- Replaced all generic starter dishes with a cohesive Louisiana Cajun menu,
  including Gumbo, Crawfish Bisque, Crawfish Étouffée, Shrimp Fettuccine,
  Jambalaya, Blackened Redfish, starters, and dessert.
- Added ten original dish-specific WebP photographs and renders each image on
  the homepage, menu overview, category pages, and menu administration.
- Added a portable upgrade migration that preserves unrelated custom records,
  adds the menu-image field on SQLite/MySQL/MariaDB, and upgrades recognized
  demo rows in place.
- Improved the public menu into polished photographic cards and upgraded the
  admin item list with dish thumbnails and category names.
- Added a validated bundled-image selector for new menu items and corrected
  the category-page proposal URL.

## 0.1.21 — 2026-10-24

- Removed the opaque footer frame from the existing transparent CATERING CO.
  logo so the artwork blends directly with the footer surface.
- Increased the footer logo presentation slightly while retaining the Core-
  owned live brand asset and the original transparent PNG.
- Documented Core 2.0.7's shared social placement control; Catering continues
  to render only Core-filtered header and footer records.

## 0.1.20 — 2026-10-24

- Added Core-owned social profile rendering in the header, footer, or both.
- Header icons appear immediately after Request a Quote; footer icons appear
  above the copyright row.
- Blank profiles render no link or placeholder, and configured links open with
  accessible labels and opener isolation.
- Updated shared module rules so future industries use the same Core-owned
  profile and placement contract.

## 0.1.19 — 2026-10-24

- Removed the duplicate catering dashboard route, view, and navigation item.
- Kept the live new-inquiry summary on Core's main Dashboard through the
  existing capability-filtered `dashboardWidget()` contract.
- Updated permanent module rules so future industries use one Core-owned
  overview and keep their categories focused on actionable workspaces.

## 0.1.18 — 2026-10-24

- Added a module-appropriate starting footer menu as a separate Core navigation
  location with the same one-time import and administrator-ownership rules.
- Replaced hard-coded footer links with the current Core-managed footer tree,
  including nested items and configured link targets.
- Renders Core's editable token-aware business copyright while leaving the
  mandatory Cajun Web Pro attribution unchanged.
- Updated the persistent module rules so future industries must declare and
  render both header and footer locations through Module API v1.

## 0.1.17 — 2026-10-24

- Added the approved transparent generic **CATERING CO.** starter logo.
- Declares the logo through Core 2.0.5 `brandAssets()` so Core copies it into
  Media once and owns all later administrator changes.
- Renders the current Core-owned logo and favicon in the public catering layout
  and uses the current logo/favicon in module administration.
- Preserves text branding when no logo is selected and adds a contrast-safe
  footer frame for transparent wordmarks.
- Requires Website Core `>=2.0.5,<3.0.0` with Module API 1.

## 0.1.16 — 2026-10-24

- Replaced the hard-coded catering header links with Website Core's editable
  primary navigation tree.
- Declares its existing header links with Core's reusable `navigationMenu()`
  API; Core imports them when no primary menu exists and takes over editing.
- Records one-time initialization so an administrator may intentionally remove
  every menu item without the defaults returning.
- Added nested submenu rendering and honored same-tab, new-tab, and dedicated
  new-window targets selected in Core's Navigation Menu editor.
- Preserved the original catering links as a safe fallback until an
  administrator saves a custom primary menu.
- Requires Website Core `>=2.0.1,<3.0.0` for the navigation API.

## 0.1.15 — 2026-10-23

- Added compatibility with Website Core's grouped, persistent administration
  navigation and protected built-in Core module behavior.
- Completed browser-deployment integration and release-bundle verification.
- Verified the module in the Website Core 2.0.0 keyless catering TEST-BUNDLE.

## 0.1.0 through 0.1.14

- Established the catering schema, provider, controllers, public routes,
  administration pages, responsive theme, seeded content, inquiries, menus,
  packages, services, gallery, service-area, contact, and quote workflows.
- Added package manifest, licensing integration, module permissions, bundled
  imagery, and Website Core installer integration.

