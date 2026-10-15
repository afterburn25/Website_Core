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

**Status**: API specification in `docs/LICENSING_API.md`. Cajunwebpro.com
licensing service is NOT yet implemented — needs license key generation,
domain registration portal, validation endpoint, and domain change
workflow.

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
  (`opcache_reset()` in SystemController) to ensure new code is loaded.
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
