# Catering Module Session Handoff

## Current continuation — 2.1.0 candidate (2026-10-26)

Catering 2.1.0 completes the requested commercial design refinement without
changing Website Core or creating a combined installer. All ten layouts now
use a compact sticky 75%-opaque navigation surface, larger centered labels,
an explicit active-page state, accessible mobile dismissal, and page-change
feedback. Each layout retains its own composition and now has a dedicated
hero, feature, and accent pack; thirty photographs and ten chooser thumbnails
are visually distinct.

The exact local archive is `industry-catering-2.1.0.zip`; its final size and
SHA-256 are recorded in `releases/LATEST.json`. It contains 126 files and no
nested ZIP. Local Core 2.6.2, SQLite, package, shared-contract, syntax, and
rendered-structure checks pass. The exact tree was published at
`ef1bf8ce9139c0b1c81e3a6f81b255648a16b9f9`; Module CI `40257434131` and
Catering acceptance `40257433957` passed on acceptance commit
`eba9e4052bf55821064730a2c9b82a2e296cd426`. Responsive browser screenshot
acceptance remains pending and must not be claimed until completed.

## 2.0.1 Core 2.6.1 compatibility release — 2026-10-25

Website Core 2.6.1 is merged on `main` at
`99d13cf09488ef4990345ce229c60b334b6ca6d2`. It keeps Module API 1 and schema
`2026_10_25_0032` but replaces the ambiguous Geo/analytics path with a
dedicated **Website → Geo service area** screen containing City, State /
region, optional Country, and Range from city (miles). Catering 2.0.1 requires
Core `>=2.6.1,<3.0.0`, pins acceptance to that merge, and adds a blocking
contract check for the route and fields.

No Catering business logic or migration changed. The module still registers
the server-side geocoder and consumes Core's verified center/radius for the
public map, quote preview/submission, outside-address error, manual-review
fallback, and eligible checkout. This remains a standalone Catering package;
no Core files or combined installer are included.

Local acceptance passed against the exact Core merge: all 61 PHP files, 55
planner checks, 113 SQLite integration checks, the Core dependency contract,
35 platform checks, 18 cross-module checks, 29 release-readiness checks, and
three 27-check packaged upgrade/lifecycle baselines. The exact 84-file ZIP is
1,749,850 bytes with SHA-256
`4daa1e1f6fd5c531a298e4ade63bc2078343c85db74a67aada569a6829433774`
and no nested ZIP. The source/archive tree is published at
`8755485a2bdde6f0ddd158048c3125c3866cfe5a`; Module CI `40249108939` and all
four PHP 8.4/8.5, MySQL 8.4, and MariaDB 11.4 jobs in Catering acceptance
`40249108960` passed.

## 2.0.0 Core 2.6 integration release — 2026-10-25

The module now requires Website Core `>=2.6.0,<3.0.0` and adopts the published
Core service-area, module-theme-collection, Media-field, structured-data,
quote-document/reminder, and atomic checkout-validation contracts. No Core
implementation or combined installer is included here.

Catering registers a bounded server-side Nominatim adapter while Core owns
provider selection, normalized caching, stored service-area coordinates,
audited address overrides, and accessible map output. Quote preview/submission
and eligible Core checkout use the same inside/outside/unknown decision. The
required confirmed-outside field error is exact; unknown results remain manual
review. Postal zones continue to be an additional authoritative module rule.

Ten unique layout templates, forty Core contrast-checked palettes, and three
Core Media slots per layout are registered. Public pages follow Core's
published choice; authenticated `?theme_preview=1` follows the saved draft.
Core handles draft/publish/rollback and Media usage. Core 2.6 retains only the
draft, published, and previous selections—not arbitrary saved state for every
inactive layout—so the corresponding roadmap item remains honestly open and
the module does not create a duplicate theme-state store.

Quote-only remains the default. Mixed/direct modes synchronize only explicitly
eligible visible per-guest packages to Core Commerce. The final validator
checks package, quantity/guest range, date, lead time, blackout, capacity,
time, fulfillment, postal zone, minimum order, service radius, and pricing
complexity. Manual/variable cases return to quote or review; Core owns cart,
accounts, orders, payments, and immutable validation evidence.

Core-backed proposal/BEO snapshots, final-headcount reminders, tasting capacity
and lead-time rules, Core Media fields for public proof, and package-scoped
structured data are wired. The exact 83-file ZIP is 1,746,734 bytes with
SHA-256 `98be7e2da035107c51ffbade8b4bfbedb7d5f9a23cac94a682c8ef74e6c70ed6`
and no nested ZIP. It is published at final commit
`66b9c4605458833a2414fc5de47feae8daac4780`; Module CI `40246534104` and all
four jobs in Catering acceptance `40246534087` passed against Core source
`f511e5f42ea82f62dcce0f5153609da3ca8d45aa`. No combined installer or
live-host deployment was produced.

## 1.5.0 commercial operations candidate — 2026-10-25

The module now contains the module-owned work from the 1.1, 1.2, 1.4 and 1.5
commercial milestones: accepted Core quotes synchronize into one confirmed
event; tentative events preserve the same questionnaire/menu/operations
snapshots; event planning covers final count, timeline, tasks, staffing,
equipment, delivery, tastings and revision acknowledgement; production covers
ingredients, recipes, waste, stations/dates, labels, purchasing and event cost
comparisons; Core content types supply venue partners, case studies and
verified reviews; eight focused service pages are routed publicly.

The exact 1.5.0 ZIP is 1,710,238 bytes with SHA-256
`e99f9a6c753600260d853b434209ad75b7cfeeaef8d78e94287b069fb1d6c3f0`;
it contains 65 files and no nested ZIP. The published source/archive cleanup
commit is `c97bfb17d7bc19448f70d9e3b2e431223dde5085`. Module CI
`40238129164` and all four jobs in Catering acceptance `40238129179` passed.
Acceptance includes 55 planner, 113 SQLite integration, 21
release-readiness, 18 cross-module isolation, and 27 packaged lifecycle checks,
all PHP syntax, and the pinned Core inquiry detail/notes contract.

Three shared boundaries remain: CORE-REUSE-9 for the city/state + mileage map
and server address distance result, CORE-REUSE-10 for the ten-layout theme
collection and per-theme palette/media state, and CORE-REUSE-11 for atomic
module validation during Core checkout. CORE-REUSE-11 was recorded in Website
Core roadmap commit `0215410d4a6676350cf4f297497dccf9dba7c92a` after source
inspection confirmed Core 2.3 has generic commerce but no catering eligibility
hook. Direct ordering remains disabled; do not ship an unsafe generic store.

## Post-1.0 commercial roadmap decision — 2026-10-25

The owner approved the next commercial catering milestones: a confirmed-event
workspace, production/fulfillment tools, Core-backed proposals/contracts and
customer completion, costing/reporting, sales-growth content, and optional
direct ordering through Core commerce/accounts. The module roadmap now stages
that work as 1.1.0 through 2.0.0; no post-1.0 behavior or release archive has
been claimed yet.

Service-area mapping is explicitly a shared Core design. Administrators will
enter a city/state center and mileage radius in the Core-owned business
profile; Core will geocode/cache the center and supply the accessible radius
map component to Catering and other modules. The dependency is recorded as
`CORE-REUSE-9`; the address-validation requirement is in Website Core roadmap
commit `da86fc19551d191d4dc55572d42fef4cb92aa7a8`. Catering's public Service
Area page will adopt that contract when available and will continue to use its
postal delivery zones for authoritative quote validation. Quote preview and
submission will send the structured event/delivery address to Core for a
server-side inside/outside/unknown result. Confirmed outside addresses receive
an accessible error and keep their form values; ambiguous addresses or provider
failures go to correction/manual review rather than being mislabeled. Do not
add a module-private map provider, public geocoding request, or exposed
provider key.

The owner also requires a ten-theme Catering collection. Each theme must use a
different layout and different default imagery, not a shared layout recolored
ten times. Each theme will offer at least four selectable, accessible color
palettes, with its palette and Core Media replacements preserved while other
themes are active. Core must own the chooser, desktop/mobile preview,
draft/publish/rollback state, palette controls and theme-media persistence so
other industry modules can reuse the same design. That dependency is recorded
as `CORE-REUSE-10` in Website Core roadmap commit
`1ef7c5545e40bbd993be298fd1368519a2d0a216`; Catering's ten-layout theme pack
is staged as 1.6.0 and is not implemented or included in the 1.0.0 ZIP.

## Current 1.0.0 candidate — 2026-10-25

Catering 1.0.0 adds module-owned operational request screening: service-style
guest limits and lead times, blackout dates, a request-capacity ceiling,
pickup/delivery windows, delivery zones/fees/food minimums, and optional fixed
or per-guest service lines. The public planner, no-JavaScript preview, final
submission, stored operations snapshot, Core inquiry summary, and queued mail
all use server-authoritative values. It remains quote-only and does not create
a booking, cart, account, invoice, or payment system.

The candidate adds an operator guide, release notes, a release-readiness suite,
expanded lifecycle data-preservation checks, and a pinned read-only shared
Module API/isolation check against Landscaping 1.0.0 commit
`be009de567286b16ea60e4188b6e7eb34f482b83`. No Landscaping source, Website
Core source, synchronized snapshot, or combined installer changed. The exact
archive is 1,679,901 bytes with SHA-256
`c254d5966b0f6e1664e8297adbe5e6c17b851133d7d07526963aa0f45fd1c29d`;
it contains 57 files and no nested ZIP. The exact tested tree is published at
`bf68f07df24289de791ef9e6076eace20b583d11`. Module CI `40223586738` and all
four jobs in Catering acceptance `40223586850` passed.

## 0.4.0 release — 2026-10-25

Migration `catering_0006_catalog_availability` adds optional availability
windows plus reusable menu collections and their package relationship.
Public queries and estimates exclude out-of-window records. Linked packages
accept only current collection members, with JavaScript guidance backed by
the same server validation. The protected collection workspace cleans up
memberships and package links without changing inquiry snapshots. Structured
data stays deferred to CORE-REUSE-3. The archive is 1,667,506 bytes with
SHA-256 `00ba3bfc2e70286567b0ae198dd36f30587dc6b5202f76de9a5679080f8f67e0`.
The final branch commit is `4d4f7e7b427fd07438c64dfcf31a38337f69e147`;
Module CI `40221582234` and Catering acceptance `40221582233` passed.
The missing package-scoped schema contribution contract is recorded in the
Core roadmap at documentation commit
`30349cff3bfa971cbed359ead041850bc58d14e7`; no Core implementation changed.

## 0.3.0 release — 2026-10-25

Catering settings now owns only the event-specific questionnaire choices:
event types, service styles, budget bands, and visibility/required flags for
optional planning questions. Public rendering and server validation read the
same configuration; disabled inputs are discarded and historic questionnaires
remain unchanged. Generic forms, exports, reminders, notification templates,
spam operations, attachments and generated documents remain Core-owned. The
exact archive/tree is at commit `5a72464f046343291ad7a7b4b4b4d9466ce803db`.
Module CI `40220230528` and Catering acceptance `40220230572` passed. The ZIP
is 1,660,656 bytes with SHA-256
`6a35828d89d377792277df4f59177c4019586dce1573957293452ac1cb79b565`.

## Current 0.2.1 release — 2026-10-25

Catering now requires Core 2.3 and adopts its shared resource workspace for
dish/package quick edits, search, pagination, keyboard-accessible visible-row
ordering and bulk show/hide/feature controls. The detailed module editors still
own every advanced catering field. The acceptance workflow pins Core 2.3.0
commit `861d1b3e3c8def4ed4bb0b9e29614ad4290663a5`, tests direct upgrade from
the exact published 0.2.0 candidate, and makes the repaired inquiry detail and
notes contract blocking. No Core source or combined installer is changed.

The final source/archive tree is published at branch commit
`291379d1af53e50623809cec989d15f790c4e7f4`. Module CI run `40219209542`
and Catering acceptance run `40219209508` passed. Acceptance covers PHP
8.4/8.5, SQLite, MySQL 8.4, MariaDB 11.4, direct 0.2.0 upgrade, clean
install/reinstall, and the repaired Core inquiry detail/notes contract. The
release ZIP is 1,656,882 bytes with SHA-256
`1155bf0ca31487375295248555b3c12dad8494f1a679996b190a52cba98a196f`.

## Publication completed — 2026-10-25

The recovered 0.2.0 candidate is now on GitHub at
`f3d20fe26a794513956cb8e6b4a7bb67388a7906`. Module CI run `40216516473`
and Catering acceptance run `40216516597` passed, including PHP 8.4/8.5 and
native MySQL 8.4/MariaDB 11.4. See [Testing](TESTING.md#github-publication-and-ci--2026-10-25)
for exact check counts and archive verification. The original candidate ZIP
and checksum are preserved; these publication notes supplement its pre-CI
documentation. Temporary publication files are absent from the branch.

CORE-REUSE-7A is fixed in the pinned Core 2.3 fixture and its diagnostic is
blocking and passing. Real shared-host upload/upgrade remains outstanding.
Continue only module work; do not combine with Core without an explicit owner
request.

## Authoritative locations

- Repository: `afterburn25/Core_Mods`
- Branch: `module/catering`
- Folder: `modules/industry-catering/`
- Current version: `2.1.0`
- Manifest ID: `cajunwebpro/catering`

Do not develop the catering module directly on `main`, and do not create a
second top-level folder for it. New catering work belongs on this branch and in
this folder. Website Core's `examples/industry-catering/` directory is a tested
release snapshot used to build combined installers; it is not the development
source of truth after this separation.

This branch follows repository `MOD_RULES.md` contract version 1.1. Future
sessions must apply those shared rules before module-specific roadmap choices.

## Owner direction — 2026-10-25

Catering work is module-only while the owner develops Website Core separately.
Do not change Website Core implementation, synchronize its catering snapshot,
merge the latest Core, or build a combined installer until the owner explicitly asks to combine
this module with the latest Core. This direction supersedes inherited automatic
Core synchronization and combined-bundle CI requirements for catering work.
Continue module ZIP packaging, honest compatibility declarations, module
validation, and documentation. Record deferred integration tests without
claiming that a newer Core has been tested. See `../MODULE_RULES.md`.

The owner also authorizes Core roadmap updates when catering work identifies
required Core changes. Add or update the dependency in
`afterburn25/Website_Core` → active implementation branch
(`devin/website-core` as verified 2026-10-25) → `docs/ROADMAP.md`, checking for an
existing entry first. Include the shared API/service needed, the catering use
case, and acceptance criteria. Cross-link it in the module roadmap/handoff and
mark dependent module work blocked or deferred until Core implements it.
This documentation exception does not authorize Core implementation changes
or a combined build. The shared-service planning review is now recorded under CORE-REUSE-1
through CORE-REUSE-8 in that roadmap; these are planning entries, not
implemented dependencies. Use existing Core services first. Shared API,
record editor, module content/media and forms integration gaps must be
resolved before catering work relies on them. Core implementation remains
separate and no combined installer has been built.

## Current baseline — reconciled 0.2.0 candidate

This continuation recovered two independent local work sets missing from the
remote branch: the 0.2.0 menu planner and 0.1.24/0.1.25 metadata/shared-service
adoption. Both are preserved in this candidate. The preceding local checkouts
were left intact. The module remains separate from Core implementation and
no combined installer or live deployment was produced.

The menu planner provides configurable package choices, per-guest/tray/fixed
food pricing, minimum quantities, guest-count discounts, dietary/allergen
labels, server-side validation and an immutable inquiry menu snapshot.
Estimates exclude taxes, staffing, rentals, delivery and service charges;
manual/unconfigured pricing requires confirmation. Existing recipe text is
preserved and structured dietary labels are never guessed.

Core 2.2 business profile, inquiry/quotation capability and shared admin
renderer are retained. The duplicate module inbox remains retired. Detailed
questionnaires and menu snapshots stay module-owned; the Core inquiry receives
a readable snapshot summary. Linking uses one transaction with a MySQL row
lock to prevent parallel boot requests creating duplicate sales records.
A recovered quote-submission TypeError was corrected by converting the
inserted questionnaire ID to the integer required by the linkage method.

Migration 0003 remains the existing home metadata repair, 0004 remains Core
inquiry linkage, and the previously unpublished planner migration is 0005.
The older 0002 migration preserves edited demo dishes/categories. Never rename
an already published migration ID. Core >=2.2.0,<3.0.0 / Module API 1 is required.
The acceptance workflow pins published Core 2.2.1 commit
`12f082936b0b2c9dae4f54f345e4d059c625f2e9` as a read-only fixture.

### Open Core dependency

CORE-REUSE-7A is recorded in the active Core roadmap (documentation commit
`411fae6b73f055ac592dcd4c90ba0ed30509d2ee`). Core InquiryService::find()
references a nonexistent admins.name field instead of the canonical username,
so the shared inquiry detail screen fails. It reproduces on SQLite in Core
2.2.0 and 2.2.1. Module persistence/linking checks pass, but this does not prove
a working Core inbox detail UI. Do not release this as production-ready until
Core fixes that dependency and the end-to-end detail/notes flow is retested.
Do not patch Core implementation or create a replacement inbox here.

### Package and verification rules

Build with modules/industry-catering/tools/build-release.py, retain only the
current ZIP, and recheck the exact archive after changing packaged files.
addon.json stays at the ZIP root, with no nested ZIP. The historical 0.1.22
and 0.1.23 ZIP blobs are truncated; use the valid 0.1.21 ZIP or the explicitly
rebuilt pinned 0.1.23 source fixture, never claim the corrupt archives installed.
Module-only lifecycle testing is permitted; combined packaging is owner-deferred.

## Historical baseline — 0.1.25

Version 0.1.25 carries the 0.1.22 Cajun menu upgrade in a substantially smaller
shared-host-safe archive. It replaces the generic demo menu with ten Cajun dishes and ten
matching original WebP photographs. Migration `catering_0002_cajun_menu_images`
adds the image field portably and upgrades only recognized starter records, so
unrelated administrator-created dishes remain intact. The public homepage,
menu overview, category detail pages, and menu administration all render the
new photography. Migration `catering_0003_home_search_metadata` also completes
blank search metadata on the Core starter home record and replaces only the
untouched short Welcome title. Core 2.2.0 is now the compatible baseline.

The module contains its provider, catering controller, five database
migrations, public views, administration views, responsive CSS/JS, fourteen
WebP images, and the transparent generic CATERING CO. logo. The module declares its approved header links through Core's
`navigationMenu()` API. Core imports that declaration once, owns the editable
copy, and returns the current nested tree through `navigationTree()`, so an
administrator can customize or intentionally empty it without defaults
returning. Website Core 2.2 supplies shared navigation, users/roles, business
identity, inquiries, quotations, content, media, integrations, security,
operations, backups, marketplace, and agency services. Version 0.1.17 declares
the starter logo with `brandAssets()`; Core
copies it once and module layouts resolve the live Core logo/favicon through
`brandAssetUrl()`.

## Verification evidence

Core_Mods release commit `13190474e6d786854742b06e23e0691c131dda5f`
passed Module CI run 8 (`40044852033`). The synchronized Website Core commit
`49c124d0292efeb6b7e6965cd93259cc1234e580` passed all six jobs in Core CI run
397 (`40044526443`), including PHP 8.4/8.5, MySQL 8.4, MariaDB 11.4, installer,
Core-only boot, and release artifact contract coverage. The release ZIP digest
is `b182dbd11edbdff03cc52a2bc9ffd83fe5f7b1c125588e6149cf6f5d69eb11cc`.
That is the historical 0.1.16 baseline. The 0.1.21 release ZIP digest is
`4c300c583f658b6ee76c1545c875cfe31404b97894cfaaba27a359b93b2a4d1f`;
both CI runs remain pending because local PHP execution is unavailable in the
current workspace.

The 0.1.22 release ZIP digest is
`f5bf20d24b01213d7c913b666e444302505e7254166c1df7c75eac73262d645a`.
Core_Mods release commit `e931f7e06822e85a96263e326e3847cbeb9db9df`
is synchronized to Website Core commit
`bccb4d6e2be5a59a5780cafb453efc46aeaa568a`. Website Core CI run
`40063594623` (run 429) passed all six jobs. The Core_Mods repository did not
create a Module CI run for the branch update.

The shared-host-sized 0.1.23 release is 1,608,151 bytes with SHA-256
`99b90f88db88cfc8913cfe56b8289e358191681927e274d38dce9a893985b379`.
Its release commit is `fe37c950addd1aa9fb839e3bb6a627b86b0ea92b`,
the synchronized Core commit is
`307d32a8e7ee3aaa49ec37f16a3a4f348c175346`, and Website Core CI run
`40064437493` (run 431) passed all six jobs.

The 0.1.24 candidate is 1,609,810 bytes with SHA-256
`f3e04792e5e495b0513dd9a91d2eb18cf753d93ff1ea029d2a7feae5904379f0`.
Its module and synchronized Core CI evidence is pending.

Historical candidate 0.1.25 was the shared-service adoption baseline. It requires Core 2.2,
declares `businessProfile()` and `leadManagement()`, retires the duplicate
Catering lead workspace, and links preserved detailed questionnaires to Core
through `InquiryService`. Migration `catering_0004_core_sales_workspace` adds
only the nullable linkage column. The Core 2.2 database matrix and live-host
migration evidence are pending. The module-only ZIP is 1,610,452 bytes with
SHA-256 `8c0b6fe3474e459fe32ff6f9fec8adb93a71c019b527d9366da414bf9427b729`;
its `verified_core` field remains intentionally null until acceptance passes.

## Next safe task

Continue the module-owned 0.3.0 lead-configuration and operations scope. Reuse
the Core inquiry workspace and keep shared follow-up, export, notification,
attachment, content, media and forms work linked to its existing Core roadmap
dependencies instead of duplicating those engines. Shared-host upload/upgrade
remains unverified. No combined Core installer is authorized.
