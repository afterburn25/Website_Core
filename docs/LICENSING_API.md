# Licensing API

Website Core supports a licensing/DRM model for bundled products (core +
industry module). The customer-facing licensing service (e.g., cajunwebpro.com)
provides the license validation API and bundle building.

## Bundle model

Customers purchase "Restaurant Website" (or other industry module). The
licensing service generates a bundled ZIP:

- `site/` — Website Core
- `site/modules/industries/industry-{name}/` — industry module with
  `license_key` embedded in `addon.json`

## License validation API

Website Core calls `POST {license.api_url}/validate.php` during installation
and periodically thereafter — for the default base this resolves to
`POST https://cajunwebpro.com/api/validate.php`. The clean `/api/validate`
URL is also served on the licensing side via Apache rewrite, but the `.php`
route is canonical and works without mod_rewrite.

**Configuration:** `license.api_url` in `config.php` — a **base** URL
(e.g., `https://cajunwebpro.com/api`); the service appends `/validate.php`.
The installer sets this to the default; change it if your licensing service
runs on a different URL. During install, the `WC_LICENSE_API_URL`
environment variable overrides the default for both install-time
validation and the generated config — used by tests pointing at a local
mock endpoint.

**Request:**
```json
{
  "license_key": "ABCD-1234-EFGH",
  "domain": "example.com"
}
```

**Response (authorized):**
```json
HTTP 200
{"authorized": true}
```

**Response (denied — explicit, takes the site offline):**
```json
HTTP 200
{"authorized": false, "error": "License key not found or domain not registered"}
```

**Response (transient — never deactivates):** any non-200 status
(429 rate-limited, 400 malformed request, 5xx server error). Only a
`200 + authorized:false` verdict is a real denial; every other outcome —
non-200, malformed JSON, unreachable host — is inconclusive and the
site retries on the next scheduled validation.

## License key binding

- Each purchase gets a unique `license_key`
- The key is embedded in the module's `addon.json`
- The installer stores the key in the `license` table
- Module `install`/`enable` operations check the key — mismatched modules
  are refused. `disable()` deliberately skips the check so an invalid or
  deactivated license can never trap an administrator out of recovery.

## Domain binding

- Customer registers domain(s) in the licensing service portal
- A license allows at most **2 active domains** — public + test instances
  on the same server; `www.example.com` and `example.com` are the same site
- Periodic validation reports the domain the install actually serves —
  derived from the canonical `app.url` in `config.php`, which an admin
  must update when genuinely moving the site. `HTTP_HOST` is deliberately
  not trusted: preview URLs, IP access and internal hostnames must never
  produce a false denial. The stored `license_domain` is the fallback and
  is refreshed to the last domain actually validated.
- Moving the site (or pointing `app.url` at an unregistered domain)
  therefore makes the install self-deny at its next validation; the old
  domain stops being reported once config changes.
- Changing a domain deactivates the old site; the new site validates on next
  check

## Periodic validation

`LicenseValidationJob` re-validates the license via cron — daily while the
license is valid, and **hourly while deactivated** so a corrected domain
registration restores the site quickly.

- **Explicit denial** (`authorized: false` — deactivated license or
  unregistered domain): the site marks `license_deactivated = 1` and public
  pages return `503 Site unavailable`. `/admin` stays reachable so the owner
  can fix the domain or contact support.
- **Inconclusive failure** (API unreachable, timeout, malformed response):
  the attempt is logged but the site keeps running — transient outages must
  not take customer sites offline.
- **Reactivation**: a subsequent successful validation clears
  `license_deactivated` automatically — re-registering the domain restores
  the site on the next scheduled check.

## Database schema

```sql
CREATE TABLE {prefix}license (
  id INT AUTO_INCREMENT PRIMARY KEY,
  license_key VARCHAR(255) UNIQUE NOT NULL,
  license_domain VARCHAR(255) NOT NULL,
  license_valid TINYINT(1),
  license_validated_at TIMESTAMP NULL,
  license_deactivated TINYINT(1) DEFAULT 0,
  license_error TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Build command

```bash
php tools/build-packages.php bundle <industry> <license_key> [outdir]
```

Example:
```bash
php tools/build-packages.php bundle restaurant ABCD-1234-EFGH dist/
```

This creates `Website-Core-restaurant-0.1.0-BUNDLE.zip` containing the core
and the restaurant module with `license_key` embedded in `addon.json`.
