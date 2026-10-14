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

## Core MUST NOT contain

payments, processor SDKs, orders, invoices, taxes, subscriptions,
commerce/catalogs, booking, CRM, public customer registration, client
portals, memberships, blogs, advanced forms/galleries, newsletters,
analytics, webmail, hosting resale, 20i, domains, VPS, SSH, agency fleet
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
