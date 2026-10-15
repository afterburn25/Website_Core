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

Website Core calls `POST {license_api_url}` during installation and periodically
thereafter.

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

**Configuration:** `license.api_url` in `config.php` (e.g.,
`https://cajunwebpro.com/api/validate`)

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

`LicenseValidationJob` runs daily via cron to re-validate the license. If it
fails, the site shows a warning to admins but continues to work for public
visitors.

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
