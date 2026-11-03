# Catering Module Project Status

- Current version: `2.1.0`
- State: commercial theme release published with green CI; responsive browser acceptance pending
- Development branch: `module/catering`
- Repository folder: `modules/industry-catering/`
- Module API: `1`
- Required Core: Website Core `>=2.6.2,<3.0.0`
- Last updated: 2026-10-26

## 2.1.0 commercial design scope

- Replaces cross-theme image reuse with thirty unique, optimized WebP
  photographs: hero, feature, and accent packs for all ten themes.
- Rebuilds all ten theme thumbnails from their actual new photography.
- Gives every theme a compact sticky navigation bar with a 75%-opaque blurred
  surface, larger centered labels, clear active-page state, and a polished
  mobile menu.
- Adds page-change feedback, prevents a redundant reload when the current page
  link is selected, and supports Escape/outside-click mobile dismissal.
- Reorganizes each theme's sections, cards, imagery, hero rhythm, and footer
  hierarchy while retaining ten genuinely different layout systems.

## 2.0.2 maintenance scope

- Declares a different Core administration icon for all eight Catering links.
- Presents ten visual Catering theme cards with unique thumbnails, direct
  activation, per-card palette selection, and optional image overrides.
- Opens every public and Catering administration GET route in the blocking
  integration suite.
- Requires operators who copied module files through a file manager to use
  Core's **Finalize update** action so pending migrations run before use.

## 2.0.1 compatibility scope

- Pins acceptance to merged Website Core 2.6.1 commit
  `99d13cf09488ef4990345ce229c60b334b6ca6d2`.
- Requires Core's dedicated **Website → Geo service area** route with City,
  State / region, optional Country, and Range from city (miles) controls.
- Retains the Catering geocoder, public map, outside-address error,
  manual-review fallback, postal rules, and checkout validation without a new
  module migration or duplicated Core settings screen.

## 2.0.0 integration scope

- Core-owned city/state + mileage-radius configuration, cached server geocoding,
  accessible public map, staff overrides, and inside/outside/unknown address
  decisions used by quote preview, final submission, and eligible checkout.
- Ten unique Catering layout templates, forty accessible palette choices, and
  three Core Media image slots per layout, following Core's draft/publish/
  rollback state with authenticated saved-draft preview.
- Safe quote-only default plus explicit direct-order/mixed modes. Only selected
  per-guest packages synchronize to Core products; Catering's event, date,
  capacity, fulfillment, zone, minimum, and radius rules revalidate atomically.
- Core-backed proposal/BEO snapshots and reminders, configurable tasting
  capacity/lead time, Core Media fields for public proof, and package-scoped
  structured data.
- No combined installer and no Core source bundled with the module.

## 1.5.0 commercial operations scope

- One tentative/confirmed event per Catering quote request with automatic
  accepted-Core-quote synchronization and preserved immutable snapshots.
- Final headcount/deadlines, run of show, tasks, staffing, equipment/returns,
  delivery manifests, tasting workflow, and change acknowledgements.
- Maintainable ingredients and recipes with waste, production station/date,
  label notes, scaled worksheets, and cross-event purchasing totals.
- Estimated/actual event operating costs compared with accepted Core quote
  revenue without duplicating invoices, taxes, or payments.
- Core-managed venue, case-study, and verified-review records plus focused
  school, nonprofit, holiday, and memorial service pages.

## 0.2.0 delivered scope

- Menu planner integrated into the four-step quote flow, with dietary filters
  and server-rendered preview when JavaScript is unavailable.
- Editable dishes/packages, included category choices, guest ranges, per-guest,
  tray and fixed food pricing, serving sizes, minima and discount tiers.
- Business-verified dietary labels, known allergens and handling notes.
- Server-authoritative selection/price snapshots retained with inquiries and
  surfaced in the lead workspace and Core-queued notification email.
- Additive migration and guards against overwriting edited older demo content.
- Local pricing, integration, packaged install/upgrade/lifecycle and browser
  acceptance; the current ZIP is complete and below the upload-size ceiling.

## 0.2.1 release scope

- Core-owned searchable resource workspaces for dish and package quick edits.
- Keyboard-reachable visible-row ordering with safe partial-order persistence.
- Bulk show, hide, feature and unfeature actions limited to module records.
- Detailed module editors retained for all industry-specific configuration.
- Required Core inquiry-detail and note-attribution regression acceptance.

## 0.3.0 release scope

- Administrator-managed event types, service styles, and budget ranges.
- Show/hide/required controls for Catering-specific planning questions.
- Matching public rendering and server-side validation with disabled-input
  rejection and preserved historical inquiry snapshots.
- Shared reminders, export, notification templates, spam operations,
  attachments, and proposal documents remain deferred to Core services.

## 0.4.0 candidate scope

- Inclusive availability windows for dishes, packages, and menu collections.
- Reusable curated dish collections linked to multiple packages.
- Public and server-side enforcement of current availability and collection
  membership, with package category limits retained.
- Structured-data contribution remains blocked on CORE-REUSE-3 rather than
  bypassing Core's SEO and schema editor.

## 1.0.0 candidate scope

- Service-style guest limits and lead times, blackout dates, and a configurable
  event request-capacity ceiling.
- Pickup/delivery windows, delivery-zone postal rules, zone fees, food
  minimums, and optional fixed/per-guest service estimate lines.
- Immutable operations snapshots retained with menu snapshots and summarized
  through Core's inquiry and mail services.
- Release-readiness checks for accessibility, responsive/reduced-motion
  support, client injection primitives, CSRF/rate limiting, capability guards,
  migration order, and package documentation.
- Pinned read-only contract/isolation verification against Landscaping 1.0.0;
  no Landscaping code, Core code, or combined installer is changed.
- Operator guide, 1.0 release notes, supported Core policy, forward-only
  migration policy, and Core-owned backup/restore guidance.

## Completed

- Public multi-page catering website and detail pages
- Responsive catering layout, styles, scripts, and WebP imagery
- Catering data migration and seeded starter content
- Inquiry and quote workflows
- Catering administration category
- Menu, package, and settings administration; inquiries use Core
- Core module/package, permission, version, installer, and licensing integration
- Module-owned navigation declaration imported and managed by Core
- Core-owned editable navigation tree rendered by the catering header
- Generic transparent CATERING CO. starter logo declared through the Core brand
  asset handoff, with current Core logo/favicon rendered publicly and in admin
- Separate Core-owned header/footer navigation trees and Core-owned editable
  business copyright rendered by the public catering layout
- Shared inquiries/quotations capability enabled through Core without a
  duplicate Catering dashboard or inbox
- Core-owned optional social icons rendered in the configured header/footer
  locations using one shared Core placement, with blank profiles omitted
- Transparent footer logo presentation without an opaque frame
- Ten-dish Cajun demo menu with matching original photography across public
  pages and administration
- Portable in-place upgrade migration from the original generic demo menu
- Upgrade-safe home-page title and search metadata repair for untouched Core
  installer placeholders
- Versioned module ZIP and SHA-256 latest-release record
- Website Core 2.0.1 bundle, installer, MySQL, and MariaDB acceptance coverage
- Core-owned business profile, inquiry pipeline, and quotation capability
- Upgrade-safe linking of preserved Catering questionnaires to Core inquiries
- Shared Core module-admin rendering with duplicate lead surfaces removed

## Current focus

- Treat this repository branch as the authoritative module-development source.
- Develop and release catering independently while Core is developed separately.
- Synchronize the Website Core snapshot and build a combined installer only
  when the owner explicitly requests integration (direction: 2026-10-25).
- Defer combined Core bundle CI until that integration is requested; preserve
  honest compatibility requirements and continue module validation.
- Build, publish, and verify the exact 2.0.1 module archive against merged
  Core 2.6.1. Keep signatures, contract acceptance, recurring ordering,
  invoices, and payments on their Core dependencies instead of duplicating
  them here.

## Known issues and release gates

- Catering 2.0.1 is published at exact source/archive tree commit
  `8755485a2bdde6f0ddd158048c3125c3866cfe5a`. Module CI `40249108939` and
  all four Catering acceptance jobs in `40249108960` passed on PHP 8.4/8.5,
  SQLite, MySQL 8.4, and MariaDB 11.4 against merged Core 2.6.1.

- Catering 2.0.0 is published at final branch commit
  `66b9c4605458833a2414fc5de47feae8daac4780`. Module CI `40246534104` and
  all four Catering acceptance jobs in `40246534087` passed on PHP 8.4/8.5,
  SQLite, MySQL 8.4, and MariaDB 11.4.

- Catering 1.5.0 is published at final branch commit
  `ffeab7cbd0908f5501fd01b123f9f0986431f60a`. Its prior module acceptance
  remains historical evidence; 2.0.0 publication and CI are not yet claimed.

- CORE-REUSE-7A was repaired in Core 2.2.2; 0.2.1 makes its detail/notes
  diagnostic blocking against the Core 2.3 fixture, and that diagnostic passes.
- The final 0.2.1 tree passed Module CI run `40219209542` and Catering
  acceptance run `40219209508`, including PHP 8.4/8.5, SQLite, MySQL 8.4,
  MariaDB 11.4, direct 0.2.0 upgrade, and clean-install registration.
- Catering 1.0.0 passed Module CI `40223586738` and all four acceptance jobs
  in `40223586850`, including PHP 8.4/8.5, SQLite, MySQL 8.4, MariaDB 11.4,
  cross-module contract isolation, release-readiness, and three packaged
  lifecycle baselines.
- No combined Core bundle or live-host installation was performed.
- Shared content/media/forms integration remains on CORE-REUSE-3/4.
- Historical 0.1.22/0.1.23 archives are truncated. Only the complete current
  ZIP remains in releases; legacy-source fixtures are explicitly test-only.
- CORE-REUSE-9 and CORE-REUSE-11 are implemented in Core 2.6 and their Catering
  integrations pass local contract acceptance.
- CORE-REUSE-10 is implemented for registration, contrast checks, Media slots,
  draft/public selection and rollback. Core currently retains only draft,
  published and previous selections, so arbitrary inactive-layout palette and
  Media choices are not independently retained. Catering does not add a second
  state store; this remaining Core behavior is documented rather than hidden.
