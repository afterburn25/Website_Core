# Compatibility

## Supported matrix (target)

| Layer | Supported | Evidence |
|-------|-----------|----------|
| PHP | 8.4, 8.5 | CI matrix on ubuntu-latest |
| MySQL | 8.4 | CI service job (native suite) |
| MariaDB | 11.4 | CI service job (native suite) |
| Web server | Apache 2.4 mod_rewrite; LiteSpeed; no-rewrite fallback (`?p=`, PATH_INFO) | .htaccess shipped; fallback verified on the extracted artifact via `php -S` with NO router file — GET `/`, `?p=/admin/login`, `/index.php/admin/login`, `?p=/sitemap.xml` 200; POST `?p=/admin/login` 302; POST `/index.php/inquiry` enforces CSRF (419); unknown slugs 404; LiteSpeed evidence pending real host — see `docs/REAL_HOST_TESTING.md` for the host-specific checklist |
| Extensions | required: pdo_mysql mbstring openssl json fileinfo session ctype; recommended: gd intl zip curl | installer checks |

## Notes

- **sqlite** driver exists for local development/unit tests only and is
  never a substitute for the native MySQL/MariaDB acceptance suite.
- **Case sensitivity**: CI runs on Linux; paths and class names are
  case-correct (the autoloader lowercases the first relative segment for
  the `admin/controllers` layout convention).
- **Hosts**: ordinary shared hosting — no root, daemons, Node, Redis, or
  mandatory SSH/Composer on the customer box. Host-specific behavior is
  claimed only with evidence; the matrix expands as host runs land.
- **Versions**: semantic versioning; core version, extension API major
  (`WC_API_VERSION`), theme API, package versions, and schema versions are
  independent numbers. Patch/minor compatibility is tested; unlimited
  backward compatibility is not promised.
