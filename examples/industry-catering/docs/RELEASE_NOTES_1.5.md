# Catering 1.5.0 Release Notes

Catering 1.5.0 is the commercial event-operations release for Website Core
2.3. It preserves the 1.0 request-screening and immutable snapshot behavior,
then carries accepted work into production without creating a parallel sales,
account, invoice, or payment system.

## Event and fulfillment workspace

- One tentative or confirmed event is linked to each detailed quote request.
- Accepted Core quotations confirm the existing event instead of duplicating it.
- Event state, schedule, final count/deadline, run of show, tasks, staffing,
  equipment, delivery details, tastings, and change acknowledgements are
  managed through capability- and CSRF-protected Core-rendered admin screens.
- Structured delivery addresses are retained for the future shared Core
  service-radius validator. Postal delivery zones remain authoritative today.

## Kitchen production and costing

- Ingredient cost records and per-guest recipes scale to final headcount.
- Waste, station, preparation lead days, label/container instructions,
  allergens, and preparation notes feed printable event worksheets.
- Upcoming tentative/confirmed events produce a combined purchasing list.
- Estimated and actual event costs are compared with the accepted Core quote
  total; incomplete actuals remain visibly incomplete.

## Public sales proof

- Core content types manage venue partners, event case studies, and verified
  reviews, while Catering owns their public presentation.
- Focused service pages cover weddings, corporate events, schools, nonprofits,
  holidays, memorial gatherings, private celebrations, and drop-off catering.

## Deliberately deferred shared capabilities

- `CORE-REUSE-9`: shared city/state + mileage radius maps and server-side
  inside/outside/unknown delivery-address validation.
- `CORE-REUSE-10`: reusable ten-layout theme collections, per-theme palettes,
  media slots, preview, publish, and rollback.
- `CORE-REUSE-4` / `CORE-REUSE-7`: generated proposals/BEOs, attachments,
  signatures, reminder delivery, and customer portal actions.
- `CORE-REUSE-11`: module checkout-validation hooks required before Catering
  may safely enable Core direct ordering.

The release ZIP is module-only, has `addon.json` at its root, contains no
nested ZIP, and does not install or modify Website Core.
