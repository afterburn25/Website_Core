# SEO and public-response performance

## Core SEO contract

- Page editors own title, description, canonical URL, noindex, and optional
  social image. The core branding default social image is used as fallback.
- The base theme emits Open Graph URL/type metadata and an escaped
  Schema.org `Organization` JSON-LD object from site identity settings.
- `sitemap.xml` includes only published, indexable, non-trashed pages and
  publishes last-modified dates. `robots.txt` links to it.
- Public 404 paths are aggregated by normalized hash rather than stored once
  per hit. The SEO admin screen shows frequency/referrer evidence and can
  create a validated permanent redirect to a live page.

## Response caching

Successful public HTML GET responses receive a body-derived strong ETag and
`Cache-Control: public, max-age=300, stale-while-revalidate=600`. Matching
`If-None-Match` requests receive an empty 304. Sitemap and robots responses
cache for 30 minutes. Administrator responses remain `private, no-store`.

Media variants are generated only when GD supports the source format, never
upscaled, bounded to 64–2400 pixels, cached in protected storage, and served
with immutable one-week caching. Cache cleanup is available under Operations.
