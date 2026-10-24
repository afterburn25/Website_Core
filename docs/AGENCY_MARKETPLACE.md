# Agency dashboard and marketplace

## Register a client site

1. On the client site, create an API key with only `health.read`.
2. On the agency site, open **Agency → Client sites** and enter the canonical
   HTTPS base URL and one-time key.
3. Use **Check now** to retrieve authenticated identity and version data.

Tokens are recoverable only because health checks must send them, so they are
encrypted with sodium and the local `security.app_key`. Moving the database
without its matching config intentionally makes those credentials unreadable.

## Catalog contract

A catalog is a signed JSON object with `product: website-core-catalog`,
`format: 1`, `packages`, `core_updates`, and `signature`. Each package/update
entry requires a semantic version, public HTTPS `download_url`, and SHA-256.
Core updates also declare `stable|beta` channel and `security|feature`
severity. The signature covers canonical JSON for the complete object without
the `signature` field, using the same Ed25519 scheme as package manifests.

The catalog establishes discovery metadata, not executable trust by itself.
Package Manager or CoreUpdater still verifies the downloaded archive before
code can be installed or applied.
