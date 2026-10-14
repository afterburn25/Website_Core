# Threat Model

## Assets

Administrator credentials + sessions; site content/media; package code +
data; configuration secrets (`config.php`, app keys); private uploads;
backups; job queue contents; audit trail integrity.

## Actors

- Unauthenticated internet visitor (XSS/CSRF/injection/claim-seize).
- Authenticated editor/admin attempting privilege escalation or
  cross-object access.
- Malicious/compromised package archive (traversal, symlink, oversize,
  tampered, unsigned).
- Trusted-but-buggy package crashing boot.
- Host-level co-tenant (shared hosting realities).

## Trust boundaries

- PHP packages execute in-process as **trusted code**: the capability
  manifest is policy/review documentation — it is NOT a sandbox. Untrusted
  marketplace distribution is out of scope for 1.0.
- Templates (theme/module) are trusted code authored by developers;
  ordinary editors cannot upload/execute PHP or arbitrary JS.
- The installer is exposed pre-auth by design; the claim model (seeded
  token / bound-session) is the boundary, documented in INSTALLATION.md.
- `upload/` staging is protected; extraction never goes directly into live
  core, follows no uploaded links, enforces path/size/member limits.

## Primary mitigations (mapped to implementation)

| Threat | Control |
|--------|---------|
| SQL injection | prepared statements only (`Connection`) |
| XSS | `e()`/`e_attr()`/`e_url()`; rich-text sanitize (planned, maintained lib) |
| Session theft/replay | rotation, idle+absolute expiry, DB revocation, secure cookies |
| MFA replay | last-counter check + burned recovery codes |
| Brute force | bounded DB rate limits, finite error text |
| CSRF | per-session tokens, 419 hard fail |
| Privilege escalation | server-side capability checks, owner-only owner management, atomic last-owner guard |
| Malicious package | manifest+integrity validation pre-load, staged extraction, quarantine, lifecycle journal |
| Host-header abuse | validated configured URL; trusted-proxies explicit |
| Secrets in artifacts | config.php gitignored, deny rules, redacted audit/support bundles |
| Update tampering | signed metadata + hashes + expiry + rollback floor (M4) |
| Backup exfiltration | encrypted archives, private storage, separate recovery credentials (M4) |

## Explicit non-claims

- PHP fatals/OOM inside a package are not always catchable — quarantine
  covers boot exceptions, not engine-level failures (recovery mode exists
  for that).
- Visitor traffic cannot reliably drive scheduling — cron is required
  for guarantees; the browser fallback is explicitly bounded.
