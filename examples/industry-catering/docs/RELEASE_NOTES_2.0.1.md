# Catering 2.0.1 Release Notes

Catering 2.0.1 is the Website Core 2.6.1 compatibility release. It keeps the
complete Catering 2.0 feature set while requiring the corrected shared Geo
administration experience.

## Platform requirement

- Website Core `>=2.6.1,<3.0.0`
- Module API 1
- Install `industry-catering-2.0.1.zip` through Core's package manager.

## What changed

- Core now provides **Website → Geo service area** with City, State / region,
  optional Country, and Range from city (miles) controls.
- Catering registers its bounded server-side geocoding provider and continues
  to consume Core's verified center/radius decision for the public map, quote
  preview/submission, and eligible checkout.
- Core's Audience locations report remains analytics-only; its From and To
  date fields are not service-area settings.
- Acceptance is pinned to merged Core 2.6.1 commit
  `99d13cf09488ef4990345ce229c60b334b6ca6d2` and fails if the dedicated Geo
  route or required city/state/mileage fields disappear.

No Catering migration was added. Existing catalog, inquiries, events, theme
choices, service-area settings, and historical snapshots are preserved.
The release is a standalone module and contains neither Core nor another ZIP.
