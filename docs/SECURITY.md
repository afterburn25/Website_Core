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

Administrator session and MFA policy controls:

- Each login creates a hashed, server-side session record with IP, user agent,
  creation time, and a throttled last-seen timestamp.
- Administrators can identify the current device, revoke another device, or
  sign out every other device from My Profile. Password resets revoke all sessions.
- A settings manager may require MFA only after protecting their own account.
  Unprotected administrators are then restricted to My Profile until enrollment;
  MFA cannot be disabled while the policy is active.
- HTTPS responses receive HSTS. Admin responses are private/no-store and all
  responses include framing, MIME-sniffing, referrer, permissions, opener, and
  cross-domain-policy headers.

- Passwords: `password_hash`/`password_verify`/`password_needs_rehash`.
- Self-service reset: emailed link carries a single-use token (sha256
  stored, 30-minute expiry); completing a reset revokes all sessions.
  Request and consume endpoints are rate-limited; unknown accounts get
  an identical response (no enumeration).
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
- Installer: claim model (seeded token or bound-session), required SSL,
  generated secrets, exact allow-listed setup-file deletion/retirement, and a
  fail-closed styled cleanup gate with recheck controls. Cleanup repeats on
  later site loads only when a structurally valid generated config plus the
  completed-install marker prove the install succeeded; database tables or a
  marker alone never authorize automatic file deletion.
- Licensing/DRM (bundled products): license keys are 128-bit random,
  embedded in the module manifest at bundle build, stored in the
  `license` table, and validated over HTTPS (peer + hostname verified,
  10s timeout). Only `HTTP 200 + authorized:false` is a definitive
  denial — non-200 statuses, malformed bodies and transport failures are
  inconclusive and preserve the stored verdict so a transient outage can
  never take a site offline. Denial gates public routes to 503 while
  `/admin` stays reachable; module source/keys are not web-accessible
  (rewrite + deny rules); `disable()`/uninstall/purge are exempt so
  recovery can never be trapped.

## Accessibility

All interactive elements in the admin interface and themes support keyboard
navigation and screen readers:

- `aria-label` attributes on navigation regions (admin sidebar, site nav).
- Form inputs have associated `for` labels.
- Image thumbnails have descriptive `alt` text.
- `focus-visible` outlines on all interactive elements (links, buttons, inputs).
- `prefers-reduced-motion` media query disables animations/transitions for users
  who request reduced motion (admin layout, base theme, noir theme, terra theme).
- Semantic HTML: `<nav>`, `<header>`, `<main>`, `<h1>` headings.
- Responsive layout with mobile breakpoints (admin sidebar stacks, themes adapt).
- Package safety: manifest validation before any code load, quarantine on
  boot failure, integrity hashes supported in schema.
- Assets: namespaced resolver, allowlisted MIME, realpath confinement,
  SVG excluded until a reviewed sanitizer ships.

## Dependency policy

Maintained libraries for crypto/sanitization/SMTP as those features land
(see COMMERCIAL_READINESS for the SBOM). Versions locked via
composer.lock; `composer audit` runs in CI.
