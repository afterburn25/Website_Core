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

## Licensed-bundle signing acceptance (production gate)

The licensed-bundle builder requires `WC_BUNDLE_SIGN_KEY` and signs the
customer's final license-bearing `addon.json` plus complete per-file
SHA-256 inventory; missing/invalid signing material fails closed. CI uses
only ephemeral Ed25519 keys to test this contract, signature verification,
wrong-key/tampered-license rejection, and archive contents. No key or mock
customer license is published as a commercial asset.

**Still owner-controlled and unverified on production hosting:** provision
the protected production signer and trusted public key, provide an actual
authorized customer/domain license issued by Cajun Web Pro, verify the
purchase/payment/license authorization lifecycle against the live
nonproduction provider, and have counsel/owner approve legal terms.
Never turn on live merchant transactions merely to satisfy tests.

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
- Redistribution rights verified per bundled dependency before release —
  tracked here, not assumed.

## DRM / licensing (new)

Website Core supports a DRM licensing model for bundled products:

- **License keys**: Unique per purchase, embedded in module manifests,
  validated before install and periodically thereafter.
- **Domain binding**: Keys are tied to domains registered in the licensing
  portal; changes deactivate the old site.
- **Two-instance support**: Same server (IP) can host public + test sites
  on the same license key.
- **Build tool**: `php tools/build-packages.php bundle <industry>
  <license_key>` creates bundled ZIPs.

**Status (2026-11-03)**: The licensing API contract is documented in
`docs/LICENSING_API.md`. Merged [Website Core PR #7](https://github.com/afterburn25/Website_Core/pull/7)
reports that the companion service on `Cajun_Web` `main` has a validation
endpoint, license/domain/bundle storage, paid-invoice fulfilment, and portal
domain/download management. Core's MySQL/MariaDB installer and periodic
authorization-denial-reactivation cycle passed against a **mock license
service** in CI. **No real purchase → licensed download → installed host →
production API validation has been verified for this Core release.** Recheck
the current companion-service revision and configure the real server, keys,
and trusted domain registration before publication. See
`docs/2.6.2_RELEASE_READINESS.md` for the live acceptance steps.

## Performance posture

Website Core is intentionally simple for shared hosting constraints:

- **In-request caching**: SettingsRepository maintains an in-request cache
  (no persistent cache required). HTMLPurifier uses a serializer cache
  (`storage/cache/htmlpurifier`) to avoid re-parsing config on every
  sanitize call.
- **No complex caching layers**: No Redis, memcached, or daemons — by design.
  Platform performs adequately for ordinary business websites on shared
  hosting without external dependencies.
- **OPcache integration**: opcache is reset after core updates
  (`opcache_reset()` in SystemToolsController) to ensure new code is loaded.
- **Database indexes**: Migration 0005 adds hot-path indexes on jobs,
  system_messages, page_revisions, menu_items, and media_usage.
- **No N+1 queries**: All database access uses prepared statements with
  bounded queries; admin list views paginate.

Performance testing (load tests, profiling) is not claimed — the platform
targets ordinary business sites on shared hosting, not high-traffic
applications.

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
| Accessibility | implemented | aria-label on nav, for labels on inputs, alt text on images, focus-visible outlines, prefers-reduced-motion support (admin layout, base theme, noir theme, terra theme) — documented in docs/SECURITY.md |
| Performance posture | documented | intentionally simple for shared hosting: in-request settings cache, HTMLPurifier serializer cache, opcache reset after updates, no complex caching layers (by design) — documented in docs/COMMERCIAL_READINESS.md |
