# Integrations API

## Authentication

Create keys at **Admin → Integrations**. The plain `wcp_…` bearer credential
is shown once and only its SHA-256 hash is stored. Send it as:

```http
Authorization: Bearer wcp_REDACTED
```

Endpoints are under `/api/v1` and also work through the no-rewrite fallback
`/index.php?p=/api/v1/...`.

| Endpoint | Scope | Purpose |
|---|---|---|
| `GET /api/v1/health` | `health.read` | Site identity, core/API and industry versions |
| `GET /api/v1/pages` | `content.read` | Cursor-paginated page summaries |
| `GET /api/v1/pages/{id}` | `content.read` | One page document |

API responses are JSON and `private, no-store`. Revocation is immediate.

## Webhooks

Webhook endpoints must be public HTTPS destinations. Lifecycle events are
queued through the durable job runner. The exact JSON body is signed with the
one-time signing secret:

```text
X-WC-Signature: sha256=<hex HMAC-SHA256 of raw body>
```

Never parse and re-encode before verification. Delivery history and HTTP
status are visible under Integrations; failed jobs follow normal queue retry
and backoff behavior.
