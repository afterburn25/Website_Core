# Theme API

Themes are presentation-only packages (`"type": "theme"`, manifest schema v1)
containing `theme.json`, `templates/`, `assets/`, and optional module view
overrides. They own layout and design — never business rules or records.
Switching themes must never mutate site data.

## Manifest

```json
{
  "schema": 1, "id": "acme/coastal", "type": "theme",
  "name": "Coastal", "version": "1.0.0", "license": "UNLICENSED-INTERNAL",
  "requires": {"core": ">=1.0.0", "api": 1},
  "tokens": { "font": "...", "color-accent": "#..." },
  "assets": {"css": ["assets/css/site.css"], "js": []},
  "templates": ["layout", "page", "404"]
}
```

## Resolution

- `theme:<name>` resolves `<active>/templates/<name>.php` → falls back to
  `base` theme templates. A missing/incompatible override renders the
  module's or base theme's view with no fatal.
- Module view overrides live at `templates/modules/<pkg-id>/<view>.php`.
- Assets are served via `/_a/themes/<theme-id>/...` only.

## Contracts

- **Tokens** — typography/colors/spacing/radius declared in the manifest;
  scoped CSS/JS, no global restyle of unrelated module markup.
- **Accessibility** — WCAG 2.2 AA target: focus-visible styles,
  `prefers-reduced-motion`, landmark structure, no autoplay/loading screens
  blocking content.
- **No executable hooks** — themes have no `provider`/`entry`. Templates
  are trusted PHP authored by the theme developer; ordinary editors can
  never upload/execute PHP or arbitrary JS through the theme system.
- **Child/override** — site owners override via a child theme directory
  (future: `templates/overrides/`), never by editing the installed parent.
- **Data safety** — theme activation/deactivation writes only
  `theme.active` setting; business records and settings are preserved.
