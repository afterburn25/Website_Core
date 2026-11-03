# Catering Website Module

The Catering Website module is the first industry package for Cajun Web Pro
Website Core. It supplies a complete catering-focused public site and an
industry-specific administration category while leaving shared content,
navigation, security, users, backups, integrations, and operations in Core.
Its [Module Creation Rules](MODULE_RULES.md) record the required preflight and
adoption of [Cajun Web Pro Module Rules v1.1](../../MOD_RULES.md).

## Current release

- Module version: `2.1.0`
- Module ID: `cajunwebpro/catering`
- Type: `industry`
- Module API: `1`
- Required Core: `>=2.6.2,<3.0.0`
- Development branch: `module/catering`

## Core 2.6 integration in 2.0.0

Catering 2.0 adopts the reusable Core services delivered for service areas,
module layout collections, schema contributions, quote documents/reminders,
and atomic checkout validation. Administrators set a city/state center and
mileage radius once in Core Business Profile. Core geocodes and caches the
center, renders the accessible public radius map, and performs server-side
address checks for both quote planning and eligible online orders. A confirmed
outside address keeps the customer's form values and shows: “This address is
currently outside our service area.” Ambiguous or unavailable lookups remain
manual-review cases. Configured postal delivery zones continue to be an
additional authoritative operating rule.

Core Appearance now receives ten complete Catering layouts—Heritage
Editorial, Modern Culinary, Garden Celebration, Grand Wedding, Corporate
Table, Rustic Gathering, Bayou Night, Market Kitchen, Social Feast, and
Minimal Luxe. They use different compositions and default imagery rather than
one recolored template. Each declares four contrast-checked palettes plus
replaceable hero, feature, and accent images through Core Media. Authenticated
administrators can preview a saved draft before Core publishes or rolls it
back; content and navigation stay unchanged.

Ordering remains quote-only by default. An administrator may explicitly
select direct-order or mixed mode and choose eligible, visible, per-guest
packages. Catering synchronizes only those packages into Core Commerce and
revalidates guest range, date, lead time, blackout dates, capacity,
fulfillment, delivery zone, minimum order, service radius, and manual-price
conditions inside Core checkout. Complex events return to the quotation
workflow. Core continues to own the cart, customer accounts, orders, invoices,
payments, and audit evidence.

Core Media fields now support venue images, event-story galleries, and review
images. Catering also contributes validated Caterer, service, menu, FAQ,
review, and service-area structured data. Staff can create immutable proposal
and Banquet Event Order snapshots and schedule final-headcount reminders from
the linked event while Core owns storage, sanitization, delivery, and audit.

## Core 2.6.1 compatibility refresh in 2.0.1

Catering 2.0.1 pins and verifies the merged Website Core 2.6.1 maintenance
release. Administrators configure the shared radius under **Website → Geo
service area** using City, State / region, optional Country, and Range from
city (miles). Catering continues to register the server-side geocoder and use
Core's saved decision for the public map, quote validation, and eligible
checkout. The audience-location analytics date range remains a separate
report and is not used as service-area configuration.

## Administration repair in 2.0.2

Catering 2.0.2 uses Website Core 2.6.2's module navigation contract so every
Catering link—including both Core-rendered quick managers—displays a different
icon. The release also smoke-tests every registered GET destination.

Appearance now shows a **Catering Themes** library instead of the generic
layout-variant editor. Each of the ten theme cards includes a
layout-specific thumbnail made from that theme's bundled images, a color
palette dropdown, optional Core Media overrides, and a direct Activate button.

If module files were copied directly over an existing installation, open
**Administration → Modules** and choose **Finalize update** for Catering
Website before opening its pages. Core will run all pending Catering migrations
and synchronize the installed version while preserving existing records.

## Commercial theme refinement in 2.1.0

Catering 2.1 replaces recycled shared-image assignments with thirty
theme-specific photographs: a unique hero, feature, and accent image for each
of the ten designs. The active pack now carries through home, inner-page hero,
service, experience, call-to-action, and signature layout imagery.

Every theme now uses a compact 72-pixel sticky navigation bar with a
75%-opaque glass surface, larger centered menu labels, a visible active-page
state, keyboard-safe mobile dismissal, and clear page-change feedback. The
ten themes retain genuinely different composition systems while sharing
commercial navigation and accessibility standards.

## Menu planning in 0.2.0

Customers can select dishes and a package in Request a Quote, enter a guest
count, filter business-verified dietary labels, and calculate a preliminary
food estimate. The same server calculation handles preview and submission.
Every selected dish is assumed to serve the full headcount. Per-guest prices,
rounded-up tray quantities, fixed event prices, minimum billing quantities
and optional guest-count discounts are supported. Unknown/manual prices stay
unconfirmed instead of producing a misleading complete total.

Catering → Menus now edits existing dishes, serving sizes, pricing methods,
five optional discount tiers, dietary labels, known allergens and handling
notes. Catering → Packages edits existing packages, guest ranges and the
minimum/maximum number of dishes allowed from each included category.
Configured package dishes are included in the package price and never billed
again. A package with no configured allowances requires team confirmation for
any selected dishes. Existing packages are not assigned invented allowances.

Inquiries retain the selected menu, labels, quantities and calculated prices
as a submission snapshot, even if the catalog changes later. Core queues the
notification email. Estimates exclude taxes, staffing, rentals, delivery and
service charges; no order, payment, availability reservation or final quote is
created. Dietary labels do not establish allergy safety. Empty allergen lists
mean information has not been supplied. Review starter recipes before assigning
structured labels; the upgrade does not invent allergen or dietary claims.

## Catalog management in 0.2.1

Core 2.3's shared resource workspace now supplies searchable quick management,
keyboard-accessible visible-row ordering, pagination, bulk selection, CSRF,
permissions, confirmations and audit records for dishes and packages. Catering
owns the persistence callbacks and rejects IDs outside its tables. Quick edits
change only the record name, category and visibility; the detailed Catering
editors continue to own prices, serving sizes, discount tiers, dietary and
allergen data, images, guest ranges and package choice rules. Quick-created
records use manual pricing until those details are completed.

The Core 2.2 inquiry-detail schema defect is fixed and the 0.2.1 acceptance
suite treats the shared detail/notes contract as a required check.

## Quote configuration in 0.3.0

Catering settings now controls the public event-type, service-style, and
budget choices. The administrator may also show, hide, or require the
Catering-specific company, phone, event time, venue, service style, budget,
menu-interest, dietary, and additional-notes questions. The server uses the
same saved configuration for validation, rejects unsupported choices, and
does not retain values submitted for disabled questions. Existing inquiry
records and immutable menu snapshots are unaffected by later configuration
changes.

This is a module-owned event questionnaire layer. Generic form schemas,
notifications, exports, spam operations, attachments, and generated documents
remain Core services and are not duplicated here.

## Seasonal catalog and collections in 0.4.0

Dishes and packages may have optional inclusive availability dates. Reusable
menu collections group curated dishes and may be linked to multiple packages.
A linked package accepts only current members of its collection in addition to
its existing category choice limits. Public pages, JavaScript estimates, and
no-JavaScript submissions all use the same server-authoritative availability
and membership rules. Expired records and removed relationships do not rewrite
historical inquiry snapshots.

## Operations screening in 1.0.0

Catering settings now defines service-style guest limits and lead times,
blackout dates, a per-request capacity ceiling, pickup/delivery windows,
delivery zones with fees and food minimums, and optional fixed or per-guest
staffing/setup/rental/cleanup lines. The public planner, JavaScript estimate,
no-JavaScript preview, and final submission use the same server rules.

The detailed questionnaire retains an immutable operations snapshot alongside
the menu snapshot and summarizes both in Core's inquiry workspace and queued
notification. These are request-screening and preliminary estimate tools only:
they do not reserve capacity, create checkout, accept payment, or replace
Core's final quotation workflow.

## Commercial event operations in 1.5.0

Accepted Core quotes now become one linked confirmed event without re-entering
the preserved questionnaire, menu, or operations snapshot. Staff can also
promote a qualified request to a tentative event, then manage event state,
headcount deadlines, run of show, tasks, staffing shifts, equipment pulls and
returns, delivery manifests, tastings, and revision acknowledgements.

The production catalog stores maintainable ingredient costs and per-guest
recipe quantities, including waste allowance, station, preparation date, and
container/label notes. Event worksheets scale from final headcount, group work
by station and preparation date, aggregate upcoming purchasing needs, and
compare module-owned estimated/actual operating costs with revenue from the
accepted Core quote. No invoice, tax, payment, signature, or customer-account
record is duplicated in Catering.

Core content types now support published venue partners, event case studies,
and verified reviews. Focused wedding, corporate, school, nonprofit, holiday,
memorial, private-celebration, and drop-off pages provide commercial entry
points while shared media, page composition, schema, analytics, and theme
selection remain Core-owned.

## Included features

- Catering home, about, services, menus, packages, gallery, FAQ, service-area,
  venue, event-story, contact, and quote pages
- Detail pages for services, menus, and packages
- Quote and inquiry capture linked to Core's shared inquiry and quotation
  workspace while preserving detailed event questionnaires
- Core-owned business identity plus module management screens for settings,
  menus, packages, events, tastings, and production
- Responsive catering theme assets, thirty unique theme-pack WebP photographs,
  fourteen catalog/general photographs, ten optimized theme-preview
  thumbnails, and a transparent
  generic CATERING CO. starter logo without an opaque footer frame
- Database migration and seeded catering content
- A ten-dish Cajun starter menu with Gumbo, Crawfish Bisque, Crawfish Étouffée,
  Shrimp Fettuccine, Jambalaya, Blackened Redfish, appetizers, and dessert
- Complete starter home-page search metadata with an upgrade-safe repair for
  the original short Welcome placeholder
- Integration with Core navigation, administration, permissions, installation,
  licensing, and version reporting
- Module-owned navigation defaults imported through Core's Module API, followed
  by live use of Core's editable tree, nesting, and configured link targets
- One-time logo handoff through Core's brand asset API; Core owns later logo
  and favicon uploads, replacements, clearing, and shared admin rendering
- Separate one-time header/footer navigation defaults, followed by live Core
  tree rendering and an editable Core-owned copyright notice
- A live new-inquiry widget on Core's main Dashboard; the module category
  contains only actionable catering management screens
- Core-owned social profiles rendered after Request a Quote in the header,
  in the footer, or both according to Core's shared Appearance setting

## Installation

Install the single `industry-catering-2.1.0.zip` through Website Core’s
module/package manager. This delivery contains only the module; use an
already-installed compatible Core. It contains no nested ZIP and does not
include or update Core. Combined installers require an explicit owner request. The installable module ZIP keeps
`addon.json` at its archive root as required by Package Spec v1.

Production licensing remains controlled by Core. The historical 0.1.22 and
0.1.23 repository ZIPs were found truncated during this release's acceptance;
use the new validated package. Upgrade coverage includes the valid published
0.1.21 package and a clearly identified fixture rebuilt from historical
0.1.23 source. See the testing record for exact evidence and limitations.

## Development records

- [Project status](docs/PROJECT_STATUS.md)
- [Roadmap](docs/ROADMAP.md)
- [Testing](docs/TESTING.md)
- [Session handoff](docs/SESSION_HANDOFF.md)
- [Operator guide](docs/OPERATOR_GUIDE.md)
- [1.0 release notes](docs/RELEASE_NOTES_1.0.md)
- [1.5 release notes](docs/RELEASE_NOTES_1.5.md)
- [2.0 release notes](docs/RELEASE_NOTES_2.0.md)
- [2.0.1 release notes](docs/RELEASE_NOTES_2.0.1.md)
- [2.0.2 release notes](docs/RELEASE_NOTES_2.0.2.md)
- [2.1.0 release notes](docs/RELEASE_NOTES_2.1.0.md)
- [Changelog](CHANGELOG.md)

