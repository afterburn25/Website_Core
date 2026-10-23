# Release Checklist

From a clean, tested commit — never a renamed scaffold.

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
   - Separately versioned example package ZIPs (never inside
     COMPLETE-UPLOAD) + `SHA256SUMS.txt` + `SBOM.json` (CycloneDX 1.5,
     runtime deps only — emitted by `tools/sbom.php`, CI-asserted).
5. Verify extracted artifacts: member paths, no missing files, version
   consistency, dependency hashes, source↔package equivalence.
6. Fresh install + upgrade using the actual artifacts on a clean host dir
   (`php tools/verify-artifact.php <COMPLETE-UPLOAD.zip>` extracts,
   provisions, and serves the real artifact).
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
