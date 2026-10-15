# Licensing API

Website Core supports a licensing/DRM model for bundled products (core +
industry module). The customer-facing licensing service (e.g., cajunwebpro.com)
provides the license validation API and bundle building.

## Bundle model

Customers purchase "Restaurant Website" (or other industry module). The
licensing service generates a bundled ZIP:

- `site/` — Website Core
- `modules/industry-{name}/` — industry module with `license_key` embedded in
  `addon.json`

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
{"authorized": true}
```

**Response (unauthorized):**
```json
{"authorized": false, "error": "License key not found or domain not registered"}
```

## License key binding

- Each purchase gets a unique `license_key`
- The key is embedded in the module's `addon.json`
- The installer stores the key in the `license` table
- Module operations check the key — mismatched modules are refused

## Domain binding

- Customer registers domain(s) in the licensing service portal
- Same server (IP) can host multiple instances (public + test)
- Changing a domain deactivates the old site; the new site validates on next
  check

## Periodic validation

`LicenseValidationJob` runs daily via cron to re-validate the license.

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
