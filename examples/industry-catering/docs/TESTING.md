# Catering Module Testing

## 2.1.0 commercial theme refinement — 2026-10-26

- Validated the exact module source against the read-only Website Core 2.6.2
  fixture and Landscaping 1.0.0 shared-contract fixture.
- All 62 PHP files passed syntax checks. JavaScript syntax, Python build-script
  compilation, JSON parsing, and whitespace validation passed.
- Menu-planner acceptance passed 55 checks; controller/database integration
  passed 125; Core platform integration passed 35; cross-module shared
  contracts passed 18; release readiness passed 105; and the packaged
  upgrade/install/disable/enable/uninstall/reinstall lifecycle passed 27.
- Every theme activated through Core and rendered one header, one navigation
  disclosure, one main landmark, unique IDs, useful image alternative text,
  and its own hero, feature, and accent photography URLs. Forty rendered-theme
  structure assertions passed.
- All thirty theme photographs and all ten 600×400 chooser thumbnails were
  reviewed as contact sheets after final WebP optimization. The thirty source
  photographs retain unique hashes and production dimensions.
- The exact archive has 126 files and no nested ZIP. Its final size and
  SHA-256 are recorded in `releases/LATEST.json`. A direct upgrade from the
  exact 2.0.2 archive preserved customer catalog edits and inquiry history.
- A fresh local Chromium executable was unavailable, so responsive browser
  screenshot acceptance remains open and is not claimed here.
- The source/archive tree was published at
  `ef1bf8ce9139c0b1c81e3a6f81b255648a16b9f9`; the acceptance commit is
  `eba9e4052bf55821064730a2c9b82a2e296cd426`. Module CI run
  `40257434131` passed. Catering acceptance run `40257433957` passed PHP
  8.4/8.5, SQLite, native MySQL 8.4, and native MariaDB 11.4.

## 2.0.2 administration route maintenance — 2026-10-26

- Added a blocking assertion for eight distinct Catering administration icon
  tokens on Website Core 2.6.2.
- Added package checks for ten distinct 600×400 WebP theme thumbnails and
  direct Core activation of every layout/palette choice.
- Expanded the GET route matrix to every static public page, all eight service
  pages, all six Catering administration destinations, and event detail.
- Local and GitHub results are recorded only after the exact 2.0.2 archive is
  built; no acceptance is claimed in advance.

## 2.0.1 Core 2.6.1 compatibility release — 2026-10-25

- Exact read-only Core fixture: merged `main` commit
  `99d13cf09488ef4990345ce229c60b334b6ca6d2` (Website Core 2.6.1).
- Blocking dependency acceptance now verifies the dedicated Geo service-area
  route and navigation plus City, State / region, and Range from city (miles)
  controls. It rejects a date-range form in that shared configuration view.
- Core 2.6 service contracts remain backward compatible: Catering continues
  to use shared geocoding, radius decisions, public maps, audited overrides,
  theme collections, quote documents/reminders, and checkout validation.
- No Catering migration was added. Packaged upgrade acceptance must preserve
  existing records and settings from the historical baselines and 2.0.0.
- Local acceptance passed against the exact Core 2.6.1 merge: 61 PHP files
  passed syntax checks; 55 menu-planner, 113 controller/database, 35 platform,
  18 cross-module, and 29 release-readiness checks passed. Three packaged
  upgrade/lifecycle runs passed 27 checks each from rebuilt 0.1.23, valid
  0.1.21, and exact 0.4.0 baselines. JavaScript syntax, module validation, and
  whitespace validation passed. The final archive digest is recorded in
  `releases/LATEST.json`; the package contains no nested ZIP.
- Exact archive: 1,749,850 bytes; SHA-256
  `4daa1e1f6fd5c531a298e4ade63bc2078343c85db74a67aada569a6829433774`;
  84 files; no nested ZIP. The exact source/archive tree was published at
  `8755485a2bdde6f0ddd158048c3125c3866cfe5a`.
- [Module CI run 40249108939](https://github.com/afterburn25/Core_Mods/actions/runs/40249108939)
  passed. [Catering acceptance run 40249108960](https://github.com/afterburn25/Core_Mods/actions/runs/40249108960)
  passed PHP 8.4/8.5, SQLite, native MySQL 8.4, and native MariaDB 11.4.

## 2.0.0 Core 2.6 integration release — 2026-10-25

- Pinned read-only Core service fixture:
  `f511e5f42ea82f62dcce0f5153609da3ca8d45aa` (Core 2.6 publication source;
  the equivalent local release/evidence checkout is `e7efa507f4e8692b041bc7fa5f4d58d88fcc37c5`).
- PHP syntax passed for all 61 module PHP files.
- Menu planner passed 55 checks; controller/database integration passed 113;
  Core dependency contracts passed; Core 2.6 platform integration passed 35;
  cross-module isolation passed 18; release readiness passed 28.
- Platform coverage includes shared city/state/radius configuration, generated
  public map, exact outside-address error, manual-review-safe address behavior,
  eligible package synchronization, preliminary/final checkout validation, ten
  registered layouts, four palettes each, draft/publish rendering of every
  layout, immutable quote
  documents/reminders, and structured data.
- Exact archive: 1,746,734 bytes; SHA-256
  `98be7e2da035107c51ffbade8b4bfbedb7d5f9a23cac94a682c8ef74e6c70ed6`;
  83 files; no nested ZIP. Three packaged SQLite upgrade/lifecycle runs passed
  27 checks each from rebuilt 0.1.23 source, valid 0.1.21, and exact 0.4.0.
- Final remote source/archive commit:
  `66b9c4605458833a2414fc5de47feae8daac4780`. Its Git tree matches the tested
  local release tree exactly. The bounded transfer run `40246474010` passed;
  all transfer chunks and its temporary workflow are absent from the final tree.
- [Module CI run 40246534104](https://github.com/afterburn25/Core_Mods/actions/runs/40246534104)
  passed. [Catering acceptance run 40246534087](https://github.com/afterburn25/Core_Mods/actions/runs/40246534087)
  passed PHP 8.4/8.5, SQLite, native MySQL 8.4, and native MariaDB 11.4.
- No combined Core installer or live-host deployment was built or performed.

## 1.5.0 commercial operations candidate — 2026-10-25

- Pinned local Core 2.3 fixture:
  `861d1b3e3c8def4ed4bb0b9e29614ad4290663a5`.
- PHP syntax passed for every module PHP file.
- Menu planner passed 55 checks; controller/database integration passed 113
  checks; release-readiness passed 21 checks; cross-module isolation passed 18
  checks; Core inquiry detail/notes contract passed.
- Coverage includes migration idempotence, structured delivery addresses,
  tasting creation/scheduling, tentative-event promotion, accepted-quote
  confirmation without duplication, headcount/timeline changes, tasks,
  staffing, equipment returns, recipe scaling, preparation station/date,
  label notes, purchasing totals, estimated/actual event costs, accepted-quote
  revenue/margins, Core content types, and focused public service routes.
- Exact archive: 1,710,238 bytes; SHA-256
  `e99f9a6c753600260d853b434209ad75b7cfeeaef8d78e94287b069fb1d6c3f0`;
  65 files; no nested ZIP. A direct packaged 1.0.0 → 1.5.0 upgrade plus disable/enable,
  uninstall-without-purge, retained-data reinstall, and fresh install passed 27
  SQLite lifecycle checks.
- The bounded release assembly run `40238026047` verified the exact archive
  digest, committed it, and removed every temporary chunk/workflow. Module CI
  `40238129164` passed. Catering acceptance `40238129179` passed PHP 8.4/8.5,
  SQLite, native MySQL 8.4, and native MariaDB 11.4.

## 1.0.0 production-baseline candidate — 2026-10-25

- Added isolated operations-planner checks for service guest ranges, lead time,
  blackout dates, capacity, fulfillment windows, delivery zones/fees/minimums,
  fixed/per-guest extras, exact cents, and unknown-selection rejection.
- Expanded controller/database coverage for structured settings validation,
  public fulfillment rendering, authoritative operations snapshots, combined
  inquiry/mail summaries, and out-of-zone rejection.
- Expanded packaged lifecycle coverage to direct 0.4.0 upgrade and retained
  catalog/inquiry data after uninstall-without-purge and reinstall.
- Added a release-readiness static suite and a pinned, read-only Landscaping
  1.0.0 Module API contract/isolation suite.
- Exact archive: 1,679,901 bytes; SHA-256
  `c254d5966b0f6e1664e8297adbe5e6c17b851133d7d07526963aa0f45fd1c29d`;
  57 files; no nested ZIP. JavaScript syntax and whitespace checks passed.
- Module CI `40223586738` passed.
- Catering acceptance `40223586850` passed all four jobs on PHP 8.4/8.5,
  SQLite, MySQL 8.4, and MariaDB 11.4. Each PHP job passed 55 planner,
  78 controller/database, 18 cross-module contract, 17 release-readiness,
  and three 25-check packaged lifecycle runs. Each native job passed 78
  controller/database and three 25-check packaged lifecycle runs.
- Lifecycle baselines were rebuilt 0.1.23 source, valid published 0.1.21, and
  exact published 0.4.0. The first 1.0 acceptance exposed numeric postal-code
  key coercion; preserving configured postal codes as strings fixed the valid
  delivery-zone match before the final passing run.
- No Core source, combined installer, synchronized Core snapshot, or
  shared-host deployment was changed or performed.

## 0.4.0 seasonal catalog candidate — 2026-10-25

- Migration coverage repeats the availability/group migration and verifies
  both reusable-collection tables.
- Controller coverage creates a collection, validates membership, links a
  package, rejects an out-of-collection dish, and excludes expired dishes and
  packages from planning.
- Planner unit coverage independently verifies linked-collection membership.
- Final archive: 1,667,506 bytes; SHA-256
  `00ba3bfc2e70286567b0ae198dd36f30587dc6b5202f76de9a5679080f8f67e0`;
  53 files; no nested ZIP.
- Module CI `40221582234` passed. The first acceptance run `40221440854`
  exposed a workflow-only filename error after all module integration checks
  passed; the corrected Catering acceptance run `40221582233` passed PHP
  8.4/8.5, SQLite, MySQL 8.4, MariaDB 11.4, and direct 0.3.0 upgrade.

## 0.3.0 configurable quote-planner release — 2026-10-25

- Controller coverage configures event types, service styles, budget ranges,
  visibility and required flags through the protected Catering settings route.
- Public rendering checks prove hidden questions are absent and configured
  choices/required attributes appear.
- Submission coverage proves required configured questions are enforced and
  disabled fields are not accepted into module inquiries.
- Exact archive: 1,660,656 bytes; SHA-256
  `6a35828d89d377792277df4f59177c4019586dce1573957293452ac1cb79b565`.
- Module CI `40220230528` and all four Catering acceptance jobs in
  `40220230572` passed, including PHP 8.4/8.5, SQLite, MySQL 8.4, MariaDB
  11.4, clean install/reinstall, and packaged upgrades.

## 0.2.1 Core 2.3 catalog-management release — 2026-10-25

Pinned Core fixture: `861d1b3e3c8def4ed4bb0b9e29614ad4290663a5`
(Core 2.3.0).

- Direct callback coverage exercises search, pagination-ready lists, partial
  ordering, bulk visibility/featured actions, record ownership validation,
  quick creation/deletion and preservation of advanced pricing fields.
- Package lifecycle coverage includes the exact published 0.2.0 ZIP in
  addition to the historical 0.1.21 and rebuilt 0.1.23 baselines.
- `core-dependencies.php` is now blocking and must pass inquiry detail, note
  attribution and package-isolation checks against the repaired Core service.
- The first published candidate exposed a clean-install lifecycle bug:
  resource registration queried Catering categories before migrations.
  Acceptance run `40218665693` failed and is retained as diagnostic evidence.
- Registration now defers category-option loading until the table exists while
  still registering both resources before migration.
- Final archive: 1,656,882 bytes; SHA-256
  `1155bf0ca31487375295248555b3c12dad8494f1a679996b190a52cba98a196f`;
  51 files; no nested ZIP.
- Module CI run `40219209542` passed.
- Catering acceptance run `40219209508` passed on PHP 8.4/8.5, SQLite,
  MySQL 8.4 and MariaDB 11.4, including direct 0.2.0 upgrade, clean
  install/reinstall and the blocking Core inquiry detail/notes contract.
- No combined Core bundle or shared-host deployment was performed.

## GitHub publication and CI — 2026-10-25

The reconciled 0.2.0 candidate is published at
`f3d20fe26a794513956cb8e6b4a7bb67388a7906` on `module/catering`.
The original archive is unchanged: 1,650,842 bytes, SHA-256
`d760e0e76f6996904bde04f9e94bd3b6763fc539c811219c9b5f2c6df4c2a26f`.
It contains no nested ZIP; only this release ZIP remains in `releases/`.
Source and archive Git tree matched the recovered local candidate exactly.
Bounded connector transfers assembled the exact archive on GitHub; the
connector completed the branch update after the Actions token could not
change workflow files. Temporary transfer files and the publication workflow
are absent from the published candidate.

- [Module CI run 40216516473](https://github.com/afterburn25/Core_Mods/actions/runs/40216516473): passed repository/release validation and PHP lint.
- [Catering acceptance run 40216516597](https://github.com/afterburn25/Core_Mods/actions/runs/40216516597): all four jobs passed (PHP 8.4, PHP 8.5, MySQL 8.4, MariaDB 11.4).
- Each PHP job passed 44 pricing checks, 45 controller/database checks, and two 21-check packaged lifecycle runs (rebuilt historical 0.1.23 source and valid published 0.1.21).
- Each native database job passed 45 controller/database checks and both 21-check lifecycle runs.
- The separate, explicitly non-blocking Core diagnostic FAILED with `no such column: a.name`. Its job conclusion is green because the dependency diagnostic uses `continue-on-error`; this is not a passing Core detail/notes contract.

Production acceptance remains blocked by CORE-REUSE-7A. No Core implementation,
combined installer, synchronized Core bundle, or live-host deployment changed.
The candidate archive retains its pre-CI documentation snapshot. This
repository-only publication record and `releases/LATEST.json` record the later
CI results without changing the exact tested archive. `verified_core` remains
null until the blocked shared inquiry detail/notes acceptance passes.

## Reconciled 0.2.0 acceptance — 2026-10-25

Read-only published Core 2.2.1 fixture:
`12f082936b0b2c9dae4f54f345e4d059c625f2e9`. Local PHP 8.3.6/SQLite.

- Pricing: 44 checks passed.
- Module controllers/database: 45 checks passed, including new quote capture,
  Core inquiry linkage, preserved menu summary, repeated-link deduplication,
  exactly one queued notification, permission/CSRF checks and escaped views.
- Packaged lifecycle: 21 checks passed upgrading from valid local 0.1.25.
- Core detail contract: FAILED. InquiryService::find() selects a.name from
  admins; SQLite reports no such column. Reproduced against 2.2.0 and inspected
  in 2.2.1. This failure is CORE-REUSE-7A on the active Core roadmap, not hidden
  by the module persistence tests. Production release remains blocked.
- Chromium browser checks passed on the reconciled candidate: desktop menu
  estimate/submission, admin label edits, dietary filtering, 390px layout and
  no-JavaScript preview.
- GitHub PHP 8.4/8.5 and native MySQL/MariaDB acceptance passed; see the publication record above.
- Combined Core bundle and live-host deployment remain owner-deferred.

## Earlier unreconciled planner checks — 2026-10-25

Local runtime: PHP 8.3.6 with PDO/SQLite/mbstring/zip; production PHP 8.4/8.5
and MySQL/MariaDB acceptance is queued in Catering acceptance CI. Core test
fixture: read-only Website_Core `a119e4281c85449471a124f14e5b10f674410023`
(version 2.1.0). No Core source or combined installer was changed.

- `tests/planner.php`: 44 checks passed, covering exact cents, tray rounding,
  minimum billing, fixed pricing, guest discounts, package ranges and choices,
  manual/incomplete estimates, client tampering and malformed inputs.
- `tests/integration.php`: 42 checks passed on SQLite using real Core services,
  covering migrations, preserved edits, permissions, CSRF, audit, validation,
  discounts, hidden categories, no-store estimates, preview without saves,
  inquiry snapshots, queued notification content and rate limiting.
- `tests/package.php`: 21 checks passed for each upgrade baseline, including
  fresh installation of the exact current ZIP, upgrade with edited menu data
  and inquiry history, navigation preservation, disable/enable, uninstall and
  data-preserving reinstall without duplicate starter items.
- Upgrade baselines: valid published 0.1.21 ZIP; historical-source 0.1.23 fixture
  rebuilt from commit `de3df977223fb8305bda91384447dae4843b5256`. The original
  0.1.22/0.1.23 ZIPs are truncated and fail ZIP integrity/digest checks.
- Chromium 153 browser checks passed: desktop menu selection and estimate,
  inquiry submission, admin dietary/allergen editing, dietary filtering,
  390px layout without horizontal overflow and no-JavaScript server preview.
- Module PHP syntax and JavaScript syntax checks passed locally.

The module-specific workflow uses a pinned read-only Core fixture, exercises
source extracted from the current ZIP on PHP 8.4/8.5, and runs controller and
packaged lifecycle checks on MySQL 8.4 and MariaDB 11.4. It never synchronizes
or produces a combined Core bundle.

Still pending: GitHub CI results, oldest-supported Core-specific acceptance,
and real shared-host module upload/upgrade. No production deployment occurred.

## Historical verification record


## 2026-10-25 — Core 2.2 adoption candidate

- Module version advanced to `0.1.25` with Core `>=2.2.0,<3.0.0`.
- Static review confirms the provider declares `businessProfile()` and
  `leadManagement()` and no longer registers a duplicate lead navigation item,
  dashboard widget, or search provider.
- Migration `catering_0004_core_sales_workspace` adds the nullable Core link
  portably and does not alter or remove existing questionnaire fields.
- New and legacy questionnaires use Core `InquiryService`; the module retains
  its detailed source row and queues exactly one existing notification email.
- Public identity values now come from Core `BusinessProfile`; operational
  Catering defaults remain module-owned.
- Local PHP is unavailable in this workspace. Module CI and packaged Core 2.2
  database/browser acceptance remain required and are not claimed as passed.
- Release archive integrity passed with no nested ZIP. Size: 1,610,452 bytes.
  SHA-256:
  `8c0b6fe3474e459fe32ff6f9fec8adb93a71c019b527d9366da414bf9427b729`.

## 2026-10-24 — Home search metadata repair candidate

- Module version advanced to `0.1.24` with Core `>=2.0.5,<3.0.0`.
- Added the portable `catering_0003_home_search_metadata` migration with
  guarded updates that preserve customized titles and existing metadata.
- Module validator and synchronized Core test results must be recorded here
  after the 0.1.24 release artifact is built and CI completes.
- Release archive size: 1,609,810 bytes. SHA-256:
  `f3e04792e5e495b0513dd9a91d2eb18cf753d93ff1ea029d2a7feae5904379f0`.

## 2026-10-24 — Shared-host upload compatibility release candidate

- Module version advanced to `0.1.23` with Core `>=2.0.5,<3.0.0`.
- Live inspection confirmed the attempted 0.1.22 upload created no package
  stage, upgrade, or migration journal entry; the installed package remained
  0.1.21 and continued rendering its original database rows.
- All ten photographs remain visually approved at 1000×667 while their total
  payload was reduced from 1,380,568 to 965,314 bytes.
- The release archive must remain comfortably below 2 MB and pass the same
  manifest, digest, ZIP-integrity, migration, and synchronized Core gates.
- Final archive size: 1,608,151 bytes. SHA-256:
  `99b90f88db88cfc8913cfe56b8289e358191681927e274d38dce9a893985b379`.
- Core_Mods release commit `fe37c950addd1aa9fb839e3bb6a627b86b0ea92b`
  is synchronized to Website Core commit
  `307d32a8e7ee3aaa49ec37f16a3a4f348c175346`.
- Website Core CI run `40064437493` (run 431) passed all six jobs, including
  PHP 8.4/8.5 lint and tests, the explicit 2 MB package ceiling regression,
  release artifact contracts, MySQL 8.4, MariaDB 11.4, and installers.

## 2026-10-24 — Cajun menu and dish photography release candidate

- Module version advanced to `0.1.22` with Core `>=2.0.5,<3.0.0`.
- Ten original 1200×800 WebP dish photographs were visually reviewed for
  subject accuracy, consistent lighting, usable crop, and absence of text.
- The upgrade migration adds `catering_menu_items.image` on SQLite, MySQL, and
  MariaDB, replaces only recognized demo slugs, and inserts Blackened Redfish
  only when absent.
- Public views render semantic dish images with dimensions, alt text, and lazy
  loading; administration renders thumbnails and validates bundled selections.
- Local JSON, asset-dimension, repository, and whitespace checks pass. Local
  PHP execution is unavailable, so GitHub CI remains the PHP release gate.
- Deterministic release ZIP SHA-256:
  `f5bf20d24b01213d7c913b666e444302505e7254166c1df7c75eac73262d645a`.
- Synchronized Website Core commit
  `bccb4d6e2be5a59a5780cafb453efc46aeaa568a` passed CI run
  `40063594623` (run 429): PHP 8.4/8.5 lint, unit and package suites, Core-only
  boot, release artifact contracts, MySQL 8.4, MariaDB 11.4, installers, and
  keyless Catering bundle acceptance.
- Core_Mods accepted the 0.1.22 release at commit
  `e931f7e06822e85a96263e326e3847cbeb9db9df`; GitHub did not create a Module
  CI run for that repository update.

## 2026-10-24 — Transparent footer brand release candidate

- Module version advanced to `0.1.21` with Core `>=2.0.5,<3.0.0`.
- The source CATERING CO. PNG retains its alpha channel and exact artwork.
- The footer stylesheet no longer applies an opaque frame behind the logo.
- Social rendering remains location-filtered after Core's move from
  per-profile placement to one shared placement control.
- Local PHP execution is unavailable; module and synchronized Core CI remain
  the authoritative release gates.

## 2026-10-24 — Social placement release candidate

- Module version advanced to `0.1.20` with Core `>=2.0.5,<3.0.0`.
- Core owns profile URLs and per-platform header/footer/both placement.
- Catering renders header icons after Request a Quote and footer icons before
  copyright, with HTTPS validation, accessible labels, and opener isolation.
- Package coverage asserts blank omission and dual-placement rendering.
- Deterministic release ZIP SHA-256:
  `1bf40847b7ef6f260546d4030a694f7bc655a3d410a1ac023b4eefe44d96a3a8`.

## 2026-10-24 — Unified dashboard release candidate

- Module version advanced to `0.1.19` with Core `>=2.0.5,<3.0.0`.
- The module no longer registers `/admin/catering` or a Dashboard navigation
  item, and the retired dashboard view is absent from the package.
- The Core overview still evaluates the capability-filtered `new-inquiries`
  widget and links it to `/admin/catering/inquiries`.
- Website Core package coverage asserts both sides of that ownership boundary.
- Deterministic release ZIP SHA-256:
  `0dfdc928f988202269896510554e5268961e7c69ed0624a11251e27882cb9ff0`.

## 2026-10-24 — Footer navigation and copyright release candidate

- Module version advanced to `0.1.18` with Core `>=2.0.5,<3.0.0`.
- Provider declares `footer` independently from `primary`; the public layout
  renders the current Core tree and honors nesting and configured targets.
- The public footer renders Core's token-expanded plain-text copyright and
  remains compatible with the separately injected immutable platform credit.
- Website Core coverage checks location isolation, module default import,
  installed rendering, token expansion, Appearance input, and portable export.
- Deterministic release ZIP SHA-256:
  `e311c02e416fc5776ba7b9faf4a8016bfe77173364e5d68039d64589a2f4d8d5`.
- Publication and CI evidence are recorded after both branch runs pass.

## 2026-10-24 — Branding integration release candidate

- Module version advanced to `0.1.17` with Core `>=2.0.5,<3.0.0`.
- The transparent CATERING CO. logo is present in the package manifest and
  declared with `brandAssets()`; public and module-admin layouts resolve Core's
  current logo/favicon through `brandAssetUrl()`.
- Website Core regression coverage checks one-time copying, source
  preservation, path confinement, the permanent clear marker, direct
  Appearance uploads, shared admin wiring, and installed-package rendering.
- Repository rules were advanced to contract 1.1 so every future module uses
  the same default-then-Core-ownership branding rule.
- Local PHP execution is unavailable in the current workspace. Do not mark the
  release verified until module and synchronized Core GitHub CI pass.

## 2026-10-24 — Navigation integration

- Module version advanced to `0.1.16`.
- Empty primary menus are initialized with the six existing catering links;
  existing customized menus are preserved and an intentionally cleared menu is
  not repopulated after the one-time marker is stored.
- The module declares defaults and reads the live tree exclusively through the
  Core 2.0.1 Module API navigation contract.
- Repository validation confirms Module Rules contract version 1.0 is present.
- Module branch validation covers manifest/release agreement, release digest,
  required records, and PHP syntax.
- Core_Mods release commit `13190474e6d786854742b06e23e0691c131dda5f`
  passed Module CI run `40044852033` (run 8): repository/release validation,
  SHA-256 agreement, and complete module PHP syntax lint.
- The synchronized Website Core commit
  `49c124d0292efeb6b7e6965cd93259cc1234e580` passed CI run `40044526443`
  (run 397): PHP 8.4/8.5 lint/unit/package suites, Core-only boot, release
  artifact contracts, and native MySQL 8.4/MariaDB 11.4 installer and bundle
  acceptance.
- Shared Module Rules main commit `020cfa03fc868bdd92ac7a914f1ad8817f35dba9`
  passed Module CI run `40044729463` (run 7).

## 2026-10-23 — Website Core 2.0 combined-bundle verification

- Core branch: `devin/website-core`
- Core commit: `c6b52d434b89bf997eb363367e0a78d78cc56a3d`
- GitHub Actions run: `40042097426` (run number 393)
- Result: passed

Successful jobs:

- Core-only boot check
- Release artifacts build and contract check
- PHP 8.4 lint, unit, and package tests
- PHP 8.5 lint, unit, and package tests
- MySQL 8.4 native schema, installer, licensed bundle, and keyless bundle tests
- MariaDB 11.4 native schema, installer, licensed bundle, and keyless bundle tests

The generated `Website-Core-catering-2.0.0-TEST-BUNDLE.zip` passed ZIP integrity
verification and contained the complete catering source and assets.

## Module-branch validation

The `module/catering` branch CI validates:

- Stable semantic version in `addon.json`
- Required development documentation
- Latest release version and SHA-256 agreement
- PHP syntax across module source, migrations, and views

## Remaining test work

- Add isolated module database/controller tests on SQLite and native MySQL.
- Add browser-level public-page and admin interaction tests.
- Add upgrade coverage from older catering schema versions.

