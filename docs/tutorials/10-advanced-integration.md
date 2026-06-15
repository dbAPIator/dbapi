# Tutorial 10 — Advanced integration

Multi-API hosting, webhooks, client architecture, CI provisioning, and operational troubleshooting.

**Prerequisites:** [Schema customization](09-schema-customization.md)

---

## Multi-API on one server

Run dev, staging, and production APIs — or separate products — as distinct **`apiId`** values on one dbAPI installation.

| Concern | Single-mode | Multi-API |
|---------|-------------|-----------|
| Env | `DEPLOYMENT_MODE=single` | `DEPLOYMENT_MODE=multi` (default) |
| Data URL | `/v1/data/{resource}` | `/v1/apis/{apiId}/data/{resource}` |
| Management id | `default` (fixed) | Per API |
| Provisioning | Auto from `DB_*` env | `POST /mgmt/v1/apis` |

Docker production example (external database):

```bash
docker run -d --name dbapi -p 8888:80 \
  -e CONFIGS_DIR=/app/apis \
  -e CONFIG_API_SECRET='change-me' \
  -v dbapi-configs:/app/apis \
  ghcr.io/dbapiator/dbapi:1.0.1
```

Then create each API via Management API. Persist **`CONFIGS_DIR`** on a volume.

Full reference: [Docker deployment guide](../docker_deployment.md).

---

## CI/CD quick-create

Provision a throwaway API in pipelines:

```bash
curl -sS -X POST "$DBAPI_URL/mgmt/v1/apis?provision=immediate" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $CONFIG_API_SECRET" \
  -d "{
    \"name\": \"ci-${BUILD_ID}\",
    \"connection\": {
      \"driver\": \"mysql\",
      \"host\": \"$DB_HOST\",
      \"port\": 3306,
      \"database\": \"$DB_NAME\",
      \"username\": \"$DB_USER\",
      \"password\": \"$DB_PASSWORD\"
    }
  }"
```

Run integration tests against `/v1/apis/ci-${BUILD_ID}/data/...`, then delete:

```bash
curl -sS -X DELETE "$DBAPI_URL/mgmt/v1/apis/ci-${BUILD_ID}?force=true" \
  -H "X-Management-Key: $CONFIG_API_SECRET"
```

For long-lived APIs with mobile or partner clients, use the stepped flow (introspect → rebuild) to preserve relationship names.

---

## Webhooks (Redis Streams)

dbAPI can publish write events to **Redis Streams** for async dispatch.

Configure per entity via Management API:

```bash
curl -sS -X PUT "$BASE/mgmt/v1/apis/default/hooks/orders" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "create": [
      {
        "url": "https://your-worker.example/hooks/order-created",
        "method": "POST",
        "headers": { "X-Hook-Secret": "shared-secret" }
      }
    ],
    "update": [],
    "delete": []
  }'
```

Requires Redis in the environment:

```env
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_STREAM=dbapi_webhooks
```

`:validate` warns if hooks exist but Redis is unset. A separate consumer reads the stream and calls your URLs.

---

## PHP resource hooks

For logic inside the dbAPI process, add hook files under the API config directory (e.g. `before.insert.php`, `after.insert.php` per entity). These run synchronously around CRUD operations — use for validation, denormalization, or audit fields.

Webhooks = async via Redis; PHP hooks = sync in-process. Pick based on latency and failure-isolation needs.

---

## Client application architecture

```text
┌─────────────┐     JWT        ┌──────────────┐     JSON:API    ┌───────┐
│  Browser /  │ ──────────────▶│  Your BFF or │ ───────────────▶│ dbAPI │
│  Mobile     │                │  API gateway │                 │       │
└─────────────┘                └──────────────┘                 └───┬───┘
       │                               │                            │
       │  Never expose                 │  Management key              │ MySQL
       │  management keys                │  only here (ops)             ▼
       └───────────────────────────────┴──────────────────────────┐ ┌────────┐
                                                                  │ │  DB    │
                                                                  │ └────────┘
```

**Rules:**

1. **Data plane only** in user-facing apps — not Management API.
2. **Discover** login methods; do not hard-code auth forms.
3. **Generate types** from `openapi.json` (openapi-typescript, orval, etc.).
4. **Filter server-side** — never download full tables for client filtering.
5. **Propagate `X-Request-Id`** through your logs.

Example env for a consumer project:

```env
DBAPI_BASE_URL=https://api.example.com
DBAPI_API_ID=demo
DBAPI_AUTH_MODE=jwt
```

Copy [AI integration guide](../ai_dbapi_guide.md) into consumer repos as `.cursor/rules/dbapi.md` or an `AGENTS.md` section.

---

## TypeScript client sketch

```typescript
import type { paths } from './dbapi-openapi'; // generated

const prefix = process.env.DBAPI_API_ID
  ? `${process.env.DBAPI_BASE_URL}/v1/apis/${process.env.DBAPI_API_ID}/data`
  : `${process.env.DBAPI_BASE_URL}/v1/data`;

export async function listCustomers(token: string, offset = 0) {
  const url = new URL(`${prefix}/customers`);
  url.searchParams.set('page[offset]', String(offset));
  url.searchParams.set('page[limit]', '20');
  url.searchParams.set('sort[customers]', 'name');

  const res = await fetch(url, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
      'X-Request-Id': crypto.randomUUID(),
    },
  });

  if (!res.ok) {
    const err = await res.json();
    throw new Error(err.errors?.[0]?.detail ?? res.statusText);
  }

  return res.json();
}
```

---

## Operational checklist

| Task | Action |
|------|--------|
| Schema migration deployed | `schema:introspect` → `schema:rebuild` → `:validate` |
| Rotate per-API secret | `POST .../management-credentials:rotate` |
| Emergency stop data traffic | `:deactivate` |
| Debug 409 everywhere | Check `status`; run `:activate` |
| Debug 404 on login | Include `{loginMethod}` in URL |
| Stale client contract | Compare client version with `openapi.json` `info.version` |
| Export for audit | `GET .../data/{resource}?format=csv` with filters |

---

## Anti-patterns to avoid

1. Direct SQL in the app when dbAPI is the integration layer.
2. Management keys in frontend bundles or mobile apps.
3. Legacy paths `/apis/...` in new code — use `/v1/apis/...`.
4. Flat JSON bodies on POST/PATCH (must be JSON:API).
5. Bulk DELETE without a filter.
6. Assuming field names — read OpenAPI first.

---

## Troubleshooting guide

| HTTP | Likely cause |
|------|----------------|
| **400** | Invalid JSON:API body, bad filter, missing bulk ids |
| **401/403** | Auth, IP ACL, or path rule |
| **404** | Wrong resource/id; invalid login credentials |
| **409** | API inactive; unique constraint |
| **408/413** | Timeout or payload too large — reduce batch size |
| **410** | Legacy `/admin/apis` — migrate to `/mgmt/v1/apis` |

Always note **`meta.request_id`** from error responses.

---

## What you learned

- Multi-API hosting scales one installation across environments and tenants.
- Webhooks + Redis decouple side effects; PHP hooks handle sync logic.
- Consumer apps should target the data plane with generated types and server-side queries.
- Operations revolve around introspect → rebuild → validate → activate.

---

## Where to go from here

| Topic | Document |
|-------|----------|
| Full data plane reference | [Using the API](../using_the_api.md) |
| Management endpoints | [Management API](../management_api.md) |
| Test scenarios | [Data plane test plan](../data_plane_test_plan.md) |
| OpenAPI generation | [OpenAPI pipeline](../openapi_pipeline.md) |
| Release process | [Releasing](../releasing.md) |

You have completed the tutorial track. Build something against the demo schema, then point dbAPI at your own database and repeat [Tutorial 7](07-provisioning-an-api.md).
