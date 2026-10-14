# Release Checklist

From a clean, tested commit — never a renamed scaffold.

1. All milestone acceptance evidence present (PROJECT_STATUS + test runs).
2. `composer audit` clean; SBOM + license inventory updated
   (COMMERCIAL_READINESS).
3. Build from clean tree:
   - `Website-Core-VERSION-FULL-PROJECT.zip` — source+docs+tests+sdk+
     examples+CI; no .git/secrets/debris.
   - `Website-Core-VERSION-COMPLETE-UPLOAD.zip` — `site/` contents only at
     root; no enclosing wrapper, no dev files, bundled runtime deps.
   - `Website-Core-VERSION-UPDATE.zip` — upgrade payload preserving
     config/keys/content/themes/modules/storage.
   - Separately versioned example package ZIPs (never inside
     COMPLETE-UPLOAD).
4. Verify extracted artifacts: member paths, no missing files, version
   consistency, dependency hashes, source↔package equivalence.
5. Fresh install + upgrade using the actual artifacts on a clean host dir.
6. Independent review gate on the exact main commit → GitHub release
   (labeled), assets downloaded back and byte-verified. Published tags
   never move; ZIPs never silently replaced.
7. Release notes + supported-host matrix + recovery instructions + test
   report attached; manifest/checksums/signatures published.
