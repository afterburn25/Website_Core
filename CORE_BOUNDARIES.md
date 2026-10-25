# Core Boundaries

The universal core is deliberately narrow. Reuse across industries is **not**
sufficient reason to promote a business feature into core. This file is the
enforcement record; CI asserts core-only boot/install and dependency
direction (packages → core API, never core → package internals).

## Core MUST provide

- Installation/configuration and environment detection (one-page, key-free).
- Administrator identities, permissions (owner/admin/editor + extensible
  capabilities), sessions, recovery, and TOTP MFA.
- Basic pages, content sections, menus, previews, revision recovery.
- Basic media library and protected storage services.
- Theme rendering, design tokens, assets, localization infrastructure.
- Basic metadata, redirects, sitemap, indexing controls.
- Package registration, compatibility, installation, activation, updates,
  dependency management, data-preserving removal.
- Shared routing, validation, database/migration, events, cache, jobs,
  system-notification, audit, health, backup, and recovery infrastructure.
- An opt-in commerce foundation (catalog, cart, checkout, inventory, and
  orders) whose screens and routes exist only when an enabled industry
  module explicitly declares the matching Core features. Industry modules
  own product taxonomy, merchandising defaults, and fulfillment rules.
- Opt-in public customer identity, recovery, profiles, and order history,
  activated only by an enabled module. Administrator identity remains a
  separate security realm.
- One shared business profile and opt-in, package-scoped inquiry/quotation
  records. Core owns the generic pipeline, assignment, notes, item totals,
  expiry, and acceptance audit; modules own industry questions, estimation
  formulas, pricing rules, scheduling, and fulfillment.
- Reusable module admin-shell and record-editor contracts. Modules retain
  persistence callbacks and business validation; Core supplies consistent
  permissions, CSRF, filtering, pagination, errors, and accessible controls.
- Seed-once module content, Core Media selection/usage tracking, and bounded
  package Schema.org contributions. Core owns editing, replacement, focal
  metadata, safe JSON-LD encoding, and administrator changes after seeding.

## Core MUST NOT contain

processor SDKs, gateway-specific payment logic, invoices, automatic tax calculation,
subscriptions, booking, full sales CRM/automation, client
portals, memberships, blogs, advanced forms/galleries, newsletters,
third-party surveillance analytics, webmail, hosting resale, 20i, domains, VPS, SSH, agency fleet
tools, or industry-specific tables and workflows.

## Dependency direction

- Industry module → owns industry records/rules; may DEPEND on add-ons.
- Optional add-on → reusable business functionality.
- Theme → presentation only; never authoritative business rules or records.
- Add-ons/themes/industries interact with core ONLY through the documented
  `ModuleContext` API (`docs/MODULE_API.md`); no cross-module table access,
  no core-internal coupling, no edits to core routers or switch statements.
- Customer-specific code lives in supported extensions/themes — never core
  edits.

## Judgment calls (kept explicitly core)

- Transactional system mail + SMTP config: core (account recovery).
- Generic job/outbox infrastructure: core. Renewal/billing engines: not.
- Password-reset delivery: core. Mailbox/newsletter: not.
- Safe outbound HTTP + inbound route/webhook boundary: core. Provider
  contracts/SDKs: packages.
- Generic commerce records and workflows: core. A module opts into each
  surface with `commerce()` or `coreFeature()`; undeclared features remain
  unreachable and absent from navigation. Gateway integrations, product
  meaning, tax rules, and industry fulfillment logic remain packages.
- Generic inquiry intake and itemized quotes: core. Lead scoring, campaigns,
  industry estimators, proposal templates, contracts, e-signature, and sales
  automation remain packages or external integrations.
