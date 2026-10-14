# Security Policy

## Reporting

Report vulnerabilities privately via GitHub Security Advisories on this
repository, or contact the maintainer listed there. Do not open public
issues for undisclosed vulnerabilities. The repository is public — never
commit customer data, credentials, or signing material.

## Baseline

Mapped to the current OWASP ASVS stable release and WCAG 2.2 AA for
shared interfaces (see THREAT_MODEL and the M6 acceptance matrix). Passing
tests/tool scores are evidence, not certification.

## Current controls (implemented)

- Passwords: `password_hash`/`password_verify`/`password_needs_rehash`.
- MFA: RFC 6238 TOTP, last-counter replay protection, hashed single-use
  recovery codes.
- Sessions: hardened cookies (HttpOnly, SameSite=Lax, Secure auto),
  rotation on login, idle + absolute expiry, DB-backed revocation.
- CSRF: session-bound tokens on all mutations; 419 on failure.
- AuthZ: server-side capability checks per route; last-owner atomic guard.
- Input: prepared statements only; output via `e()`/`e_attr()`/`e_url()`.
- Rate limiting: bounded DB buckets (login, MFA, generic).
- Audit: durable, redacting sensitive keys.
- Transport/security headers: nosniff, SAMEORIGIN framing, referrer and
  permissions policies.
- Installer: claim model (seeded token or bound-session), generated
  secrets, self-disable + permanent lock.
- Package safety: manifest validation before any code load, quarantine on
  boot failure, integrity hashes supported in schema.
- Assets: namespaced resolver, allowlisted MIME, realpath confinement,
  SVG excluded until a reviewed sanitizer ships.

## Dependency policy

Maintained libraries for crypto/sanitization/SMTP as those features land
(see COMMERCIAL_READINESS for the SBOM). Versions locked via
composer.lock; `composer audit` runs in CI.
