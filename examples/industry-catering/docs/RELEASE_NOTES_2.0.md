# Catering 2.0.0 Release Notes

Catering 2.0.0 is the Core 2.6 integration release. It completes the module
side of the shared service-area, layout-collection, quote-workflow, structured
data, and checkout-validation contracts while keeping Website Core and the
Catering package separately installable.

## Before upgrading

- Back up the complete Website Core installation and database.
- Upgrade Website Core to a compatible `2.6.x` release first. Catering 2.0.0
  requires Core `>=2.6.0,<3.0.0` and Module API 1.
- Install only `industry-catering-2.0.0.zip`. It contains no Core files and no
  nested archive.
- Quote-only remains the default. Direct ordering is never enabled by an
  upgrade and must be explicitly configured after catalog and checkout review.

## Service area and delivery addresses

Core Business Profile now accepts the service-area center city/state and a
1–500 mile radius. Catering registers a server-side OpenStreetMap Nominatim
adapter; Core owns provider selection, normalized caching, persisted center
coordinates, audited overrides, and the accessible public map. No provider
credential or browser-side geocoding request is exposed.

Quote preview, quote submission, and eligible delivery checkout all submit the
complete address to Core. A confirmed outside result produces the accessible
field error “This address is currently outside our service area.” An ambiguous
or unavailable result remains unverified for manual staff review. Postal zones,
fees, and minimum food orders remain separate Catering operating rules; the
radius illustration is guidance and does not independently promise service.

## Ten layout collection

The module registers ten page compositions in Core Appearance:

1. Heritage Editorial
2. Modern Culinary
3. Garden Celebration
4. Grand Wedding
5. Corporate Table
6. Rustic Gathering
7. Bayou Night
8. Market Kitchen
9. Social Feast
10. Minimal Luxe

Each has its own PHP composition, responsive treatment, default hero/feature/
accent image assignment, and four contrast-checked palettes. Core owns the
chooser, Media selections, draft, publication, rollback, audit, and deletion
usage tracking. Catering settings links to Core Appearance and provides an
authenticated saved-draft preview URL.

## Optional online ordering

Administrators may keep quote-only mode or explicitly enable direct-order or
mixed mode. Only selected visible packages with a positive per-guest price are
synchronized as Core products. Cart quantity represents guest count. Core
checkout calls Catering's server-side validator before and during order
creation for package eligibility, guest range, event date, lead time, blackout
dates, capacity, requested time, pickup/delivery, postal zone, minimum order,
service radius, and manual or variable pricing. Any complex case returns to the
quote workflow. Core owns the storefront, cart, customer identity, orders,
payment method, and validation evidence.

## Sales, media, and search presentation

- Event staff can create immutable proposal and Banquet Event Order snapshots
  and schedule final-headcount reminders through the linked Core quote.
- Venue partners have a Core Media image, event stories have a Media gallery,
  and verified reviews can include an image.
- Catering contributes public Caterer, service, menu, FAQ, review, and
  service-area structured data through Core's validated schema registry.
- Tasting scheduling enforces administrator-configured weekly capacity and
  minimum lead time.

## Compatibility notes

No Catering migration was added in 2.0.0: the new choices use Core-owned
tables or existing module settings, so existing menus, questionnaires, events,
production records, content, and administrator navigation remain intact.
Disabling direct ordering deactivates module-managed `CATERING-PKG-*` products
without deleting orders or historical records.

The module does not add electronic signatures, contract acceptance, recurring
corporate subscriptions, or its own invoice/payment engine. Those remain Core
account and commerce responsibilities.
