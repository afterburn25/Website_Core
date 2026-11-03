# Catering 1.0.0 Release Notes

Catering 1.0.0 is the production module baseline for Website Core 2.3. It
combines the menu planner, shared Core sales integration, catalog management,
seasonal collections, configurable questionnaires, and operational request
screening in one directly installable module package.

## Highlights

- Exact per-person, tray, fixed-event, minimum-quantity, and guest-tier food
  estimates with immutable inquiry snapshots.
- Core-owned business profile, inquiry/quotation workspace, admin rendering,
  permissions, audit records, mail queue, navigation, and brand assets.
- Configurable event types, service styles, budgets, and optional/required
  planning questions.
- Seasonal dish/package availability and reusable curated menu collections.
- Service-style guest limits and lead times, blackout dates, request-capacity
  ceilings, pickup/delivery windows, delivery zones, fees, food minimums, and
  optional fixed/per-guest service lines.
- Server-authoritative validation shared by JavaScript estimates, no-JavaScript
  previews, and final submissions.
- A pinned cross-module contract test proving Catering and Landscaping remain
  separate Module API 1 packages with isolated identities, namespaces, tables,
  and shared Core navigation/branding/admin/health contracts.

## Important behavior

Planner totals are preliminary. Submission does not reserve a date, capacity,
or fulfillment window and does not create checkout, payment, or a final quote.
Core quotations remain authoritative. Historical inquiry snapshots are not
rewritten when catalog or operations settings change.

## Upgrade notes

Back up the complete Core site first, then install the 1.0.0 ZIP through the
package manager. Migrations are additive and preserve existing dishes,
packages, inquiries, navigation, and administrator customization. Review the
new operations settings before accepting public requests. See the
[Operator Guide](OPERATOR_GUIDE.md) for the supported upgrade and rollback
policy.

Shared content/media editing, generic forms, reminders, CSV exports,
notification templates, spam reporting, attachments, proposal documents,
structured data, commerce, accounts, and payments remain Core-owned and are
not duplicated in Catering.
