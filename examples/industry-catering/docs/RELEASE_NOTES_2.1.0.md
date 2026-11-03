# Catering 2.1.0 Release Notes

Catering 2.1.0 is a complete commercial design refinement of the ten-theme
library for Website Core 2.6.2 and later.

## Theme photography

- Each of the ten themes now ships a dedicated hero, feature, and accent
  photograph: thirty distinct WebP assets with no cross-theme reuse.
- Shared public sections resolve the active theme's images instead of falling
  back to the original four generic photographs.
- Theme cards were regenerated from the real new image packs so the Appearance
  preview accurately represents the activated design.

## Navigation and interaction

- All themes use a compact 72-pixel sticky navigation bar with a blurred
  75%-opaque surface (25% transparency).
- Menu labels are larger, centered, and display an unmistakable active-page
  treatment.
- Selecting the exact current destination scrolls to the top instead of
  needlessly reloading the page.
- Real page changes show immediate progress feedback; mobile menus close with
  Escape or an outside click.

## Design refinement

Heritage Editorial, Modern Culinary, Garden Celebration, Grand Wedding,
Corporate Table, Rustic Gathering, Bayou Night, Market Kitchen, Social Feast,
and Minimal Luxe each retain a separate composition and receive individual
hero rhythm, section hierarchy, card treatment, responsive behavior, and
footer polish.

## Upgrade

Install `industry-catering-2.1.0.zip` through Core's Module Manager. No database
migration is required. Existing content, navigation, palette selection, media
overrides, inquiries, events, and operational data are preserved. If files
were copied manually, use **Modules → Catering Website → Finalize update**.
