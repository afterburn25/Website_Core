# Core branding contract

Core owns two different branding layers:

1. **Site identity** — administrators may select a logo, favicon, default
   social image, primary/accent colors, and a corner radius from Appearance.
   Compatible themes consume these values but never own the underlying data.
2. **Platform attribution** — every public HTML response receives the fixed
   Core footer immediately before `</body>`:

   - Website Design & Development by **Cajun Web Pro LLC**
   - Want a website like this for your business? **Let’s build yours.**

Both linked phrases point to `https://cajunwebpro.com`. The attribution is
implemented in `Core\Branding\Attribution`, outside theme and module settings,
and is intentionally not editable. Admin, JSON, file, redirect, and other
non-public/non-HTML responses are not modified.
