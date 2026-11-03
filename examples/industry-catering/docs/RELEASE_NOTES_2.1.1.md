# Catering Website module 2.1.1

Date: 2026-11-03

## Compatibility

- Module API: 1
- Website Core: >=2.6.2,<3.0.0
- Type: industry
- Upgrade from Catering 2.1.0: presentation-only changes; no migration or database-schema update.

## Repair

All ten visual Catering theme layouts honor the global Website Core social
placement setting: **Header**, **Footer**, or **Both**. Previously some layouts
silently omitted header or footer social links despite an enabled URL.
Every theme uses Core-supplied `headerSocialLinks` and `footerSocialLinks`
and the existing escaped `ThemeSupport::social()` renderer. Links remain
absent when the corresponding location or profile has not been enabled.

## Validation requirements

- Verify 10/10 theme source templates expose both placements and their
  accessible `Social media` nav labels.
- Run PHP lint, module release-readiness/integration and the packaged
  install/upgrade lifecycle against Website Core 2.6.2 on MySQL/MariaDB.
- Confirm the 2.1.1 release archive's `addon.json` version, file inventory,
  manifest digest, and absence of nested ZIPs.
- Perform interactive responsive browser smoke testing on a supported host
  before treating this as commercially accepted.

The 2.1.0 release artifact and checksum must not be modified or replaced.
