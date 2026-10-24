# Core branding contract

Core owns two different branding layers:

1. **Site identity** — administrators may directly upload or select a logo and
   favicon, select a default social image, and configure primary/accent colors
   and corner radius from Appearance. They may also write a plain-text business
   copyright notice using `{year}` and `{site_name}` tokens. The same logo,
   favicon, and copyright are consumed by the public theme and compatible
   industry layouts; the logo and favicon also appear in administration.
   Compatible themes and modules consume these values but never own the live
   settings.
2. **Platform attribution** — every public HTML response receives the fixed
   Core footer immediately before `</body>`:

   - Website Design & Development by **Cajun Web Pro LLC**
   - Want a website like this for your business? **Let’s build yours.**

Both linked phrases point to `https://cajunwebpro.com`. The attribution is
implemented in `Core\Branding\Attribution`, outside theme and module settings,
and is intentionally not editable. Admin, JSON, file, redirect, and other
non-public/non-HTML responses are not modified.

The editable business copyright and immutable platform attribution are
deliberately separate. Clearing the business copyright restores Core's
automatic `© {year} {site_name}. All rights reserved.` notice and cannot remove
the Cajun Web Pro credit.

## Industry-module defaults

Module API v1 permits an industry module to call `brandAssets()` with
package-relative `logo`, `favicon`, and/or `social` image paths. On first boot,
Core validates and copies only missing defaults into its Media storage. It then
records a permanent package seed marker and becomes the sole owner of the live
selections. Existing administrator selections are never overwritten, and a
selection intentionally cleared later is not repopulated by a module boot or
upgrade. Module layouts use `brandAssetUrl()` to render the current Core value.
