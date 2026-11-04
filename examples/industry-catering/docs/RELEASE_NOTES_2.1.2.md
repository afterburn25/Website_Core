# Catering Website module 2.1.2 — single page hero and compact navigation

Date: 2026-11-03 (candidate; not commercially accepted)

## Compatibility and boundaries

- Requires Website Core `>=2.6.2,<3.0.0`; Module API v1.
- No database migration, license changes, new API, payment activation, or changes to another module.
- Upgrade from an installed Catering 2.0.2 or 2.1.1 must preserve all menus, quotes, uploaded media, theme choices, social settings and navigation. Do not purge.
- Current staging installation is `https://demo.cajunwebpro.com/catering/` with Core 2.6.2 and Catering 2.0.2; maintenance mode is ON and protected backups are from 2026-10-25. **Do not overwrite the hosted site until a fresh independently restorable backup and owner-approved staging upgrade test exist.**

## Owner-requested visual changes

1. The selected theme **replaces** the page's original hero instead of adding a second hero above it.
2. Every themed page uses exactly one hero image and a page-specific primary heading and default photo. Home, Services and Menus keep their existing Core theme-media overrides; other routes have their own bundled defaults.
3. All ten themes use a shorter hero, typically 330–500px depending on viewport, rather than full screen. Multi-photo hero collages reduce to one photograph.
4. Header navigation is centered, **sticky**, and shorter (58px desktop; 56px mobile). Its shaded/blurred background is **75% opaque / 25% transparent** in all ten theme variants and the fallback layout.
5. Preserve accessible focus styling, mobile menu dismissal, Core-owned Header/Footer/Both social placement, and no duplicate H1 from hidden legacy hero content. Detail-page pricing and inquiry controls remain in body panels.

## Required acceptance

- Run PHP lint for all module files and `php modules/industry-catering/tests/release-readiness.php`.
- Run `php modules/industry-catering/tests/theme-hero-contract.php`: all principal routes have distinct default hero images, a title, and escaped dynamic text.
- Test every one of the ten theme variants on desktop, tablet, and mobile. Check sticky header through scroll, centered navigation, keyboard/focus/mobile toggle, hero heights, single photograph and page-specific imagery for Home, Menus, Services, Packages, About, Gallery, Contact and Quote.
- Build a single direct `industry-catering-2.1.2.zip` with no nested ZIP. Validate `addon.json`, SHA-256, and contents independently.
- Use a disposable Core 2.6.2 database and actual source archive to check module install, upgrade from 2.0.2, migrations, public/admin routes and content persistence before hosted deployment.
- Test the actual `/catering/` staging upgrade with a verified new backup and signed/trusted package policy; physical file copy may require **Administration → Modules → Finalize update**. Never disable signature validation just to accept a test archive.
- Keep production licensing, signed release, recoverability, and owner commercial acceptance open until proved on real hosting.

The earlier 2.1.0 and 2.1.1 archives and checksums are immutable historical releases, and must not be replaced or nested inside 2.1.2.
