# Package Spec — addon.json schema v1

Every package (industry, addon, theme) ships an `addon.json` at its root.
The runtime validator is `Core\Modules\Manifest`; the JSON schema mirror
lives at `sdk/schema/addon.schema.json`.

## Required fields

| Field | Type | Rule |
|-------|------|------|
| `schema` | int | must be `1` |
| `id` | string | `publisher/package`, lowercase `[a-z0-9_-]` segments, unique across all destinations |
| `type` | string | `industry` \| `addon` \| `theme` |
| `name` | string | human label |
| `version` | string | full semver `x.y.z` |
| `license` | string | SPDX-style identifier or documented proprietary id |

## Optional fields

| Field | Type | Meaning |
|-------|------|---------|
| `requires.core` | constraint | core version range (`>=1.0.0,<2.0.0`, `^1.2`, `~1.2`, `1.2.*`, exact) |
| `requires.api` | int | extension API major — must equal `WC_API_VERSION` |
| `requires.php` | constraint | PHP version range |
| `dependencies` | object | package-id → constraint; deterministic load order, missing/conflicting deps block enable |
| `conflicts` | object | package-id → constraint; satisfied constraint blocks install |
| `entry` | path | PHP file required once before provider instantiation |
| `provider` | class | FQCN with `register(ModuleContext $ctx)` |
| `psr4` | object | prefix → dir, registered at boot |
| `capabilities` | list | declared capability tokens (`area.action`) — documentation/policy, not sandboxing |
| `migrations` | path/list | module-owned migration source |
| `assets` | object | `css`/`js` lists served via `/_a/modules/<id>/...` |
| `integrity.files` | object | path → sha256 for post-extract verification |
| `templates` | list | theme: named template inventory |
| `tokens` | object | theme: design tokens (fonts/colors/radius/...) |
| `license_key` | string | Optional: license key binding this module to a site |

## Bundled products

Commercial distributions bundle the core + industry module in one ZIP:

- `site/` — Website Core
- `site/modules/industries/industry-{name}/` — module with `license_key`
  in `addon.json`

**Install flow:**
1. Installer detects the bundled module
   (`site/modules/industries/*/addon.json` carrying `license_key`) and
   reports it on the checks page
2. Reads `license_key` from the manifest
3. Validates `{key, domain}` against the licensing service
   (`{license.api_url}/validate.php`) — the domain must be registered to
   the license in the customer portal first
4. If valid → stores the license, registers the package, runs module
   migrations, marks it `enabled`
5. If invalid → shows the server's error inline, refuses to proceed

**Module binding:** Once a site is installed with a module, that module is
bound to the license key. `install`/`enable` operations on licensed
modules check the key match — mismatched keys are refused; `disable()`
is exempt for recovery.

**Domain binding:** The license key is associated with registered
domain(s) in the licensing service (max two active — public + test;
`www.`/apex variants count as one site). Periodic validation checks the
key + domain — daily while valid, hourly while deactivated; if the
domain changes, the old site deactivates on its next check (public
503, `/admin` reachable; Settings → "Validate now" re-checks on demand).

See `docs/LICENSING_API.md` for the full API contract.

## Rules enforced by the registry

1. Invalid manifests are reported with every violation; package never boots.
2. Duplicate IDs across `industries/`, `addons/`, `themes/` are rejected.
3. Dependencies must be present and satisfy constraints; conflicts that are
   satisfied block enablement.
4. Boot order is deterministic: dependencies before dependents (Kahn
   topological order); cycles are reported as errors.
5. A provider that throws during boot is quarantined (`state=failed`,
   journal row written); the rest of the site keeps running.
6. Themes never specify `provider`/`entry` executable hooks.

## Lifecycle states

`staged → validated → installed → enabled`, with `upgrading`, `failed`,
`disabled`, `removed`. Transitions are journaled in `package_journal`
(operation, result, detail, timestamp). Overlapping operations serialize;
interruption leaves a resumable `failed`/`upgrading` record — never a
silent half-state.
