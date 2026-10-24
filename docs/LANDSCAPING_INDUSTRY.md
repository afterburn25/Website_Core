# Landscaping Website 1.0

## Release source

- Module: `cajunwebpro/landscaping` 1.0.0
- Core_Mods branch: `module/landscaping`
- Core_Mods commit: `be009de567286b16ea60e4188b6e7eb34f482b83`
- Module release SHA-256:
  `05639d1c98002b33a0aeabfbb728802095c14cc3028280214c7e1572670d8627`
- Core compatibility: `>=2.0.7,<3.0.0`
- Final module CI:
  [run 40125724520](https://github.com/afterburn25/Core_Mods/actions/runs/40125724520)

The `examples/industry-landscaping` snapshot contains the exact deployable
runtime source from the verified Core_Mods release. Development history,
roadmap, and module release metadata remain authoritative in Core_Mods.

## Public and administration scope

The module supplies editable services, projects, maintenance plans, service
areas, seasonal services, about and contact pages, and a protected estimate
request workflow. Its administration surfaces manage services, projects,
plans, areas, seasonal offerings, estimates, and settings. The module declares
starting header/footer navigation and brand artwork; Core imports those once
and owns later administrator changes. The Core Dashboard receives the module's
new-estimates summary without a duplicate module dashboard.

## Themes

| Theme | Role | Release SHA-256 |
|---|---|---|
| Bold Outdoors | Default | `b13e851ee353f7eacb83bdaa5cb489dd4eea87860732d8942fe985c280f8ebb7` |
| Estate Green | Alternate | `d3cecbddfcb9594f3766b0efa91a931b74e29a5bf51645ebc9f91c64327f50f4` |
| Neighborhood Lawn | Alternate | `546c5d73f1b60779f7f25dc12d00c0f9903f8b9a104826f4c25583e65157f36c` |
| Earth & Stone | Alternate | `ff37722ef6269c91caae3521a611384c6597bbafcc25c66bba0cadfdd11b8b8f` |

Each theme is independently installable and uses the same Core-owned business
logo and content. Switching themes changes presentation without replacing
module data or administrator branding choices.

## Delivery gate

Every stable module release must be included in the current active Core line.
The release is considered shipped only after the module branch CI passes, the
verified runtime snapshot is copied into Core, and the complete Core CI matrix
passes. Core CI validates installation, public rendering, estimate storage,
theme installation, native MySQL/MariaDB migration behavior, package size,
and combined TEST-BUNDLE and REPAIR-UPLOAD artifacts.
