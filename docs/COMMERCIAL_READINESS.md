# Commercial Readiness

## SBOM / provenance

Runtime dependencies: **none yet** (this increment is dependency-free PHP).
Planned maintained libraries as features land (each needs license review):

| Feature | Candidate library | Status |
|---------|------------------|--------|
| Rich-text sanitization | ezyang/htmlpurifier (LGPL-2.1+) | pending M2 |
| SMTP transport | phpmailer/phpmailer (LGPL-2.1) | pending M4 |
| Update signing | sodium / paragonie/sodium_compat (ISC) | pending M4 |
| Build-time semver | composer/semver (MIT) — tooling only | planned |

Dev-only: phpunit/phpunit ^12 (BSD-3) — never shipped.

## Open owner/legal decisions (engineering continues regardless)

- Product license terms + legal entity name for copyright notices
  (currently `UNLICENSED-INTERNAL` placeholders — deliberately not MIT/GPL).
- Pricing/support promises/compliance claims — none asserted anywhere.
- Production update-signing trust root (see UPGRADES).
- Marketplace/agency products — explicitly out of 1.0 scope.

## Rules honored

- Public repo: no customer data, credentials, or signing material.
- No mandatory activation/license phone-home; installed sites keep working
  through any licensing outage.
- Redistribution rights verified per bundled dependency before release —
  tracked here, not assumed.
