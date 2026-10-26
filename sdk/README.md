# Website Core SDK

Build compatible industry/add-on/theme packages against the supported
ModuleContext API only — never core internals.

- `schema/addon.schema.json` — manifest schema v1 (validated at runtime by
  `Core\Modules\Manifest`; keep this file and the validator in sync).
- `scaffold.php` — `php sdk/scaffold.php <industry|addon|theme> <publisher/name> <dest>`
  generates a working manifest/provider, shared admin contribution, migration,
  fallback view, contract test and package README.
- `package.php` — builds one directly installable root-level module ZIP and
  rejects nested ZIP files.
- Compatibility kit: install your package directory under
  `site/modules/{industries,addons}/` or `site/themes/` on a dev install —
  the registry reports every manifest/compatibility/dependency error, and
  `tests/Package/` shows lifecycle fixtures to mirror.

## Package rules

1. Own your tables (`<package>_<table>`); never touch another package's.
2. Declare `requires`/`dependencies`/`conflicts` honestly — the registry
   enforces them.
3. Vendor PHP library deps inside your ZIP at build time; no runtime
   Composer on customer hosts.
4. Declare real `integrity.files` sha256 hashes before distribution.
5. Idempotent job handlers; at-least-once delivery.
6. Templates ship functional fallback views; themes may override them.
