# Catering Industry Package

`examples/industry-catering` is the first customer-facing industry package
for Website Core. Its package id is `cajunwebpro/catering` and its build name
is `catering`.

## Visual direction

The default presentation follows the approved editorial concept: warm ivory,
deep forest green, muted sage, restrained gold, large food/event photography,
Georgia display typography, clean sans-serif body copy, and generous spacing.
The package carries its own namespaced CSS, JavaScript, views, and optimized
WebP photographs, so it does not rely on a remote image provider.

## Public experience

| Route | Purpose |
|---|---|
| `/` | Editorial homepage with services, menu features, packages, testimonial placeholder, and process |
| `/services` | Service directory |
| `/services/{type}` | Full wedding, corporate, private-event, or drop-off page |
| `/menus` | All visible menu categories and items |
| `/menus/{slug}` | Focused category menu |
| `/packages` | Package comparison |
| `/packages/{slug}` | Package inclusions and quote handoff |
| `/gallery` | Event and culinary gallery layout |
| `/about` | Company-story framework without invented claims |
| `/service-area` | Delivery, pickup, travel, and venue coverage |
| `/faq` | Planning questions |
| `/contact` | General inquiry form |
| `/request-a-quote` | Four-step event quote planner |

Demonstration claims and testimonial text are visibly marked for replacement.
Displayed pricing is identified as a starting point rather than a final quote.

## Administration

- `/admin/catering` — package dashboard and recent inquiries.
- `/admin/catering/menus` — menu categories and items.
- `/admin/catering/packages` — packages, starting prices, guest ranges, and
  inclusions.
- `/admin/catering/inquiries` — leads and the catering status pipeline.
- `/admin/catering/settings` — phone, service area, travel note, guest minimum,
  lead time, and quote expiry defaults.

The module declares `catering.manage` and `catering.inquiries`. Owner and admin
roles receive package permissions through the core permission contract.

## Persistence and security

The migration owns only `catering_*` tables. Quote/contact submissions use core
CSRF, rate limiting, settings, and queued system mail services. Honeypot input
is silently discarded. Public input is length-bounded, email-validated, and
written through prepared statements. Lead status changes use an allowlist.

## Build a keyless test package

```bash
php tools/build-packages.php test-bundle catering dist/
```

The installer discovers the bundled industry package, applies its migration,
and enables it without a license row or licensing API call.

## Planned follow-on add-ons

Payments/deposits, e-signatures, customer accounts, advanced staff scheduling,
inventory/recipe costing, SMS, accounting, and external calendar connections
remain separate add-ons. They are intentionally not coupled to the catering
industry package.
