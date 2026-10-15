# Commercial Readiness

## SBOM / provenance

A CycloneDX 1.5 SBOM is emitted as `SBOM.json` with every release build
(`tools/sbom.php`, generated from `composer.lock` runtime packages only —
dev deps never ship) and is checksum-protected inside `SHA256SUMS.txt`.
The CI artifacts job asserts its format and contents.

Runtime dependencies (bundled into COMPLETE-UPLOAD via
`composer install --no-dev`):

| Package | License | Used for |
|---------|---------|----------|
| ezyang/htmlpurifier | LGPL-2.1+ | input-boundary rich-text sanitization |
| phpmailer/phpmailer | LGPL-2.1 | SMTP transport for queued system mail |
| paragonie/sodium_compat | ISC | Ed25519 verify/sign polyfill on hosts without ext-sodium |

Update signing uses PHP's `ext-sodium` when present and sodium_compat
otherwise; the verifier fails closed only when NEITHER is available.

Dev-only: phpunit/phpunit ^12 (BSD-3) plus its transitive tree — never
shipped (excluded from artifacts; verified by artifact contents check in
`tools/build-packages.php` output).

## Open owner/legal decisions (engineering continues regardless)

- Product license terms + legal entity name for copyright notices
  (currently `UNLICENSED-INTERNAL` placeholders — deliberately not MIT/GPL).
- Pricing/support promises/compliance claims — none asserted anywhere.
- Production update-signing trust root (see UPGRADES): signing key exists
  only when `WC_UPDATE_SIGN_KEY` is provided at build time; no key
  material is committed. Hard dependency: installed sites ship
  `trust.allow_unsigned=false`, so unsigned draft/dev artifacts refuse at
  stage time — production releases MUST be built with the sign key and
  target installs configured with `trust.public_keys`.
- Marketplace/agency products — explicitly out of 1.0 scope.

## Rules honored

- Public repo: no customer data, credentials, or signing material.
- No mandatory activation/license phone-home; installed sites keep working
  through any licensing outage.
- Redistribution rights verified per bundled dependency before release —
  tracked here, not assumed.

## Evidence status (as of M5)

| Requirement | Status | Where |
|-------------|--------|-------|
| Core-only install/operation | implemented | installer + core-only boot test |
| Package lifecycle | implemented + tested | PackageManager, /admin/modules, package tests |
| Two reference sites, identical core + unchanged shared add-on | implemented + tested | ReferenceSitesParityTest |
| Theme switch preserves data | implemented + tested | ReferenceSitesParityTest |
| Uninstall preserves / purge removes | implemented + tested | ReferenceSitesParityTest |
| Backup/restore | implemented + tested | BackupService tests, /admin/backups |
| Signed updates | implemented (fail-closed: installs refuse unsigned manifests by default) | SignatureVerifier, CoreUpdater, /admin/updates |
| Failure recovery | implemented | recovery-mode flag, quarantine, update backups |
| Accessibility floor | tested | AccessibilityTest — landmarks/labels/lang on all themes |
| Reproducible artifacts | implemented | tools/build-packages.php + SHA256SUMS |
| Security model | documented | docs/THREAT_MODEL.md |
