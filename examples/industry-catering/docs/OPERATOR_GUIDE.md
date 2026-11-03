# Catering 2.0 Operator Guide

## Supported platform

Catering 2.1.0 supports Website Core `>=2.6.2,<3.0.0` and Module API 1. Install
the single `industry-catering-2.1.0.zip` through Core's package manager. The
archive is a module-only package: it does not contain Core or another ZIP.

Before installation or upgrade, create a complete Website Core backup using
the site's normal Core operations workflow. Module data uses additive,
journaled migrations and is retained when the module is disabled or
uninstalled without an explicit purge.

If the files were instead replaced through a hosting file manager, do not use
the Catering screens yet. Open **Administration → Modules**, locate Catering
Website, and select **Finalize update**. Core validates the on-disk package,
runs every pending module migration, synchronizes the recorded version, and
preserves the module's enabled or disabled state.

## First-run checklist

1. Open Core's Business profile and confirm the public name, email, phone,
   address, and hours. Then open **Website → Geo service area** and set the
   center city/state, optional country, mileage range, and server geocoding
   provider. Confirm the generated map on the public Service Area page.
2. Review Catering settings, public event choices, minimum guests, lead time,
   capacity, blackout dates, fulfillment windows, delivery zones, and optional
   services. The bundled values are examples, not business commitments.
3. Review every dish's price, serving yield, dietary labels, known allergens,
   handling notes, image, and availability dates.
4. Configure package guest ranges, category allowances, optional curated menu
   collections, and availability dates.
5. Submit a test quote for pickup and, when enabled, each delivery zone. Check
   the immutable menu and operations summaries in Core's inquiry workspace.
6. Open Core Appearance and find **Catering Themes**. Compare the ten visual
   thumbnails, choose a color from that theme's palette dropdown, optionally
   override its hero/feature/accent images from Core Media, and select
   **Activate theme**.
   Each theme includes its own hero, feature, and accent photography. Replacing
   an image in one theme does not change another theme's bundled defaults.
7. In Catering → Production catalog, enter verified ingredient costs and
   recipe quantities before relying on a production or purchasing total.
8. Publish venue, case-study, and verified-review records—including their Core
   Media fields—through Core Content.
9. Keep quote-only ordering until the eligible package prices, guest ranges,
   dates, capacity, fulfillment windows, zones, radius behavior, minimums, and
   Core payment settings have been tested. Then explicitly enable mixed or
   direct ordering and select only the packages intended for checkout.

## Operations configuration formats

The settings screen validates each structured line before saving:

- Service rules: `Style|min guests|max guests|lead days`
- Optional services: `key|public name|price|fixed` or `key|public name|price|per_guest`
- Delivery zones: `Name|postal codes separated by commas|fee|minimum food order`
- Blackout dates: one `YYYY-MM-DD` date per line
- Pickup and delivery windows: one public label per line

Use `*` as a postal code only when the team intentionally reviews every
delivery location manually. Removing every delivery zone removes Delivery
from the public planner. Quote estimates include configured extras and zone
fees but remain inquiries, not orders, reservations, invoices, or payments.

## Daily workflow and ownership

- Core owns business identity, users, permissions, navigation, media, mail,
  inquiries, quotations, audits, backups, and operations.
- Catering owns the detailed event questionnaire, catalog, package rules,
  menu/operations snapshots, seasonal availability, and request screening.
- Update inquiry status, assignment, internal notes, and final quote amounts
  in Core. Do not treat a planner estimate as a confirmed price or booking.
- Changing catalog or operations settings never rewrites a historical inquiry
  snapshot.

## Event and production workflow

1. Build and send the final quotation from Core's inquiry workspace. An
   accepted Core quote confirms the linked Catering event automatically the
   next time the event workspace opens.
2. Use Catering → Events & tastings to promote a qualified request to a
   tentative event when operational planning must begin before acceptance.
3. Confirm the final headcount deadline, run of show, venue, delivery details,
   tasks, staffing, equipment and tasting state. Event-plan changes create an
   acknowledgement item for the team.
4. Maintain recipe station, prep lead days, label/container notes, quantities,
   waste, ingredient unit costs, and supplier names in Production catalog.
5. Print the event worksheet for the preparation schedule, menu labels,
   ingredient pull, delivery manifest, and cost summary. The combined
   purchasing list excludes completed and cancelled events.
6. Record actual food, labor, rental, travel, and other event costs as they
   become known. Revenue comes only from the accepted Core quote; incomplete
   actual costs remain marked incomplete.

The public quote flow uses both postal delivery zones and Core's service-radius
decision. A confirmed outside address is rejected with the retained form
values; an ambiguous or unavailable lookup is flagged for manual review. The
radius map is guidance, not a promise that every address inside the circle is
operationally eligible.

For a linked accepted quote, the event workspace can create immutable proposal
and Banquet Event Order snapshots and schedule a final-headcount reminder in
Core. Core remains the authority for document evidence and reminder delivery.

In mixed/direct mode, Catering publishes only the explicitly selected
per-guest packages to Core Commerce. Quantity is guest count. Catering's
validator runs on both preliminary and final order creation; manual prices,
variable delivery fees, unknown address decisions, and other complex cases
return to manual review or the quote workflow.

## Upgrade and rollback policy

Upgrade only through Core's package manager and do not skip the pre-upgrade
backup. Catering migrations are forward-only. A code downgrade is not a data
rollback: restore the complete Core backup if an upgrade must be reversed.
Never install an older module ZIP over newer migrated data.

The acceptance matrix covers clean install; upgrades from the valid 0.1.21
release, a rebuilt 0.1.23 source fixture, the exact 0.2.0 release, and the exact
0.4.0 release; disable/enable; uninstall without purge; and reinstall with
retained customer catalog and inquiry records. Whole-site backup restoration
remains Core-owned and is not reimplemented by this module.

## Troubleshooting

- A rejected date can be inside the global or service-specific lead time, on a
  blackout date, or beyond the configured event capacity.
- A rejected delivery can have the wrong window, an unmatched postal code, or
  a food estimate below that zone's minimum. It can also be outside the shared
  mileage radius; unavailable address verification is a manual-review case.
- If a theme draft is not visible publicly, preview it while signed in with
  Appearance permission, then publish the draft in Core Appearance.
- If no Catering products appear in Core Store, confirm mixed/direct mode, a
  visible package, a positive per-guest price, and explicit package eligibility.
- Manual-price dishes make the complete estimate require team confirmation.
- If detailed inquiries are not visible, run Core health checks and confirm the
  module's questionnaire-to-Core link health check is green.
- If JavaScript is unavailable, the Calculate food estimate button uses the
  same server rules and preserves form input.
