# Release Checklist

From a clean, tested commit — never a renamed scaffold.

Before starting this checklist, every applicable surface in
`docs/COMMERCIAL_HARDENING.md` must be **verified**, not merely implemented.

1. Run `php tools/release-gate.php`; all versions, manifests, migrations,
   installer cleanup, immutable attribution and packaging contracts must pass.
2. All milestone acceptance evidence present (PROJECT_STATUS + test runs).
3. `composer audit` clean; SBOM + license inventory updated
   (COMMERCIAL_READINESS).
4. Build from clean tree via `php tools/build-packages.php`:
   - `Website-Core-VERSION-FULL-PROJECT.zip` — source+docs+tests+sdk+
     examples+CI; no .git/secrets/debris.
   - `Website-Core-VERSION-COMPLETE-UPLOAD.zip` — `site/` contents at
     archive root (no enclosing wrapper) + `composer install --no-dev`
     vendor bundle; no dev files.
   - `Website-Core-VERSION-UPDATE.zip` — same payload + `update.json`
     (per-file sha256 manifest, signed when `WC_UPDATE_SIGN_KEY` is set);
     apply never touches config/keys/content/storage/installed packages.
   - `Website-Core-INDUSTRY-VERSION-REPAIR-UPLOAD.zip` — Core runtime,
     browser installer, and one industry at its exact installed path for
     recovering partial shared-host file copies; runtime site data remains
     excluded.
   - Every downloadable artifact is one directly usable ZIP. It must contain
     the payload at its root and must not contain another ZIP. GitHub Actions
     uploads extracted staging directories, including hidden guard files, so
     the Actions-generated download ZIP is the deployable package itself.
   - Separately versioned example package ZIPs (never inside
     COMPLETE-UPLOAD) + `SHA256SUMS.txt` + `SBOM.json` (CycloneDX 1.5,
     runtime deps only — emitted by `tools/sbom.php`, CI-asserted).
5. Verify downloaded/extracted artifacts: member paths, no missing files, version
   consistency, dependency hashes, source↔package equivalence. From a trusted
   release channel, obtain the exact downloaded ZIP and its SHA256SUMS.txt:
   ```bash
   python3 tools/verify-release-download.py \
     Website-Core-2.6.2-COMPLETE-UPLOAD.zip \
     --checksums SHA256SUMS.txt --version 2.6.2 \
     --expected-sha256 <independently-recorded-64-character-hex-digest>
   python3 tools/verify-release-download.py \
     Website-Core-2.6.2-UPDATE.zip \
     --checksums SHA256SUMS.txt --version 2.6.2 \
     --expected-sha256 <independently-recorded-update-digest>
   ```
   The tool does not extract or execute ZIP entries. It rejects unsafe paths,
   duplicates, nested ZIPs, private runtime files, wrong versions and broken
   UPDATE per-file hashes. The checksum file alone does not establish origin:
   obtain expected digests independently and verify update signing/trust through
   the real updater before publication. A local CI-generated archive PASS is
   **not** proof that the GitHub Release asset has the same bytes.
   For the signed production UPDATE, also verify with the external owner-pinned
   Ed25519 **public** key (never a key embedded in the archive):
   ```bash
   php tools/verify-signed-update-offline.php \
     Website-Core-2.6.2-UPDATE.zip /private/trusted-update-public.txt \
     --version 2.6.2 --expected-sha256 <independently-recorded-update-digest>
   ```
   This verifies actual ZIP members against the signed manifest with no
   extraction, network connection or host mutation. It refuses an expired,
   unsigned, wrongly signed, mismatched or tampered UPDATE. The key's provenance,
   the live updater trust store, staged rollback and production deployment
   still need separate operator evidence.
6. Fresh install + upgrade using the actual artifacts on a clean host dir
   (`php tools/verify-artifact.php <COMPLETE-UPLOAD.zip>` extracts,
   provisions, and serves the real artifact). Record the separate static
   read-only staging receipt from the source-only tool:
   `php tools/verify-host-preflight.php <installed-webroot> --expected-core 2.6.2 --expected-industry cajunwebpro/catering@2.1.2`
   (or `--no-industry` for a Core-only site). A passing receipt only proves
   on-disk checks; it never replaces actual Apache/LiteSpeed+PHP-FPM,
   authenticated browser, licensing, database, or restore acceptance.
7. Confirm public core pages receive ETags, while previews and industry forms
   containing CSRF tokens remain `private, no-store`.
8. Confirm every public HTML response contains the locked Cajun Web Pro LLC
   credit and both `https://cajunwebpro.com` links.
9. Fresh-install cleanup must physically remove `install.php` and `install/`,
   or show the fail-closed manual-cleanup screen until both are gone.
10. Independent review gate on the exact main commit → GitHub release
   (labeled), assets downloaded back and byte-verified. Published tags
   never move; ZIPs never silently replaced.
11. Release notes + supported-host matrix + recovery instructions + test
   report attached; manifest/checksums/signatures published.
