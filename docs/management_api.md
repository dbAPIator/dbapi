# Management API

The **Management API** is dbAPI’s **sole control plane**. Use it to create, configure, validate, activate, and retire **data APIs** — the JSON:API endpoints that expose your database (`/v1/apis/{apiId}/data/...`).

> **Note:** The legacy Admin API (`/admin/apis`) has been **removed**. Old paths return `410 Gone` with migration hints. Use `/mgmt/v1/apis` only. See [DEPRECATED_ADMIN_API.md](../src/public/DEPRECATED_ADMIN_API.md).

The Management API uses **plain JSON** (not JSON:API). Request bodies for write operations are validated against the OpenAPI specification.

| | Management API | Data API |
|---|----------------|----------|
| **Purpose** | Operate API definitions | CRUD on database rows |
| **Base path** | `/mgmt/v1` | `/v1/apis/{apiId}/data` |
| **Format** | Plain JSON | [JSON:API](https://jsonapi.org/) |

---

## Base URL

Replace `{base}` with your installation URL (no trailing slash).

| Environment | Example `{base}` |
|-------------|------------------|
| Local Apache | `http://localhost/dbapi/src` |
| Docker / custom | Your public URL to `index.php` |

All examples below use `http://localhost/dbapi/src`.

**Discover service:** `GET {base}/` returns JSON with management and data path hints.

**OpenAPI & Swagger:**

- Spec: [`src/public/management-openapi.yaml`](../src/public/management-openapi.yaml) (JSON: [`management-openapi.json`](../src/public/management-openapi.json))
- UI: `{base}/swagger.html?url=management-openapi.yaml`

---

## Authentication

Two levels of API keys:

| Scope | Header | Used for | Config source |
|-------|--------|----------|---------------|
| **Instance** | `X-Management-Key` | Create/list/delete APIs, break-glass on any API | `config_api_secret` in [`dbapiator.php`](../src/application/config/dbapiator.php) |
| **Per-API** | `X-Api-Config-Key` | Update connection, schema, policies, hooks, lifecycle | `admin_config.php` per API (under `configs_dir`) |

Per-API key aliases: `x-api-config-key`, `x-api-key`, `X-Api-Key` (query supported for both key types where noted above).

Legacy aliases still accepted for the **instance** key: `X-Admin-API-Key`, `X-Api-Key`, `x-api-key` (query).

**Per-API secret** is returned **once** when you create a draft API (or after rotate). Store it securely; it is not shown again on GET.

For endpoints marked “instance or per-API”, either key works: the instance key has full access; the per-API key is scoped to that API’s config directory and IP rules in `admin_config.php`.

```bash
# Rotate per-API key (global or current per-API key)
curl -X POST "{base}/mgmt/v1/apis/my-api/management-credentials:rotate" \
  -H "X-Management-Key: YOUR_INSTANCE_SECRET"
```

Instance IP restrictions apply via `config_api_ips_acls`. Per-API config updates also check IP rules in that API’s `admin_config.php`.

---

## API identity (`apiId`)

- **`apiId`** — Path segment and directory name under `configs_dir` (e.g. `demo`). Same id is used in the Data API: `/v1/apis/demo/data/...`. Must match `^[a-zA-Z0-9_\-]+$`.
- **`id`** — Stable UUID in `meta.php` (metadata only).

Renaming: `PATCH /mgmt/v1/apis/{apiId}` with body field `name` set to a new id renames the config directory when the new name is free.

---

## Lifecycle and status

| Status | Management API | Data API |
|--------|----------------|----------|
| `draft` | Full configuration | **Not serving** (no data routes) |
| `active` | Configuration + lifecycle | **Serving** |
| `inactive` | Configuration retained | **Not serving** (409 `API not active`) |

Typical flow:

```text
POST /mgmt/v1/apis          → draft
PUT  .../connection         → configure DB
POST .../connection:test    → verify connectivity
POST .../schema:introspect  → snapshot schema
POST .../schema:rebuild     → write structure.php + openapi.json
PUT  .../policies/*         → network + auth
POST ...:validate             → preflight checks
POST ...:activate             → active (data API live)
```

**Validate** (`POST /mgmt/v1/apis/{apiId}:validate`) returns:

```json
{
  "ready": true,
  "checks": [
    { "id": "connection.configured", "status": "ok", "message": "..." },
    { "id": "connection.tested", "status": "ok", "message": "..." },
    { "id": "schema.structure", "status": "ok", "message": "..." },
    { "id": "policies.configNetwork", "status": "ok", "message": "..." },
    { "id": "policies.dataNetwork", "status": "ok", "message": "..." },
    { "id": "policies.auth", "status": "ok", "message": "..." },
    { "id": "hooks.redis", "status": "ok", "message": "..." },
    { "id": "meta.name", "status": "ok", "message": "..." }
  ]
}
```

Required check ids: `connection.configured`, `connection.tested`, `schema.structure`, `schema.openapi`, `policies.configNetwork`, `policies.dataNetwork`, `policies.auth`, `meta.name`. `hooks.redis` is `warn` if hooks exist but `REDIS_HOST` is unset. `schema.openapi` is `warn` when `openapi.json` is older than `structure.php` (run `schema:rebuild`).

**Activate** requires `ready: true` (all required checks `ok`; warnings allowed). Returns the API resource object (same shape as `GET /mgmt/v1/apis/{apiId}`). On failure returns `409` with `error.details.validation`. Idempotent if already active.

**Deactivate** sets `inactive` without deleting files; returns the API resource object. **Delete** removes `configs_dir/{apiId}/` (`204`); requires instance key; use `?force=true` if the API is still `active`.

---

## Response format

### Success

- **List** (`GET /mgmt/v1/apis`): `{ "items": [ Api, ... ], "pagination": { "limit", "offset", "total" } }`
- **Create** (`POST /mgmt/v1/apis`): `201` + `Location`; body `{ "api": Api, "managementCredential": { "secret", "header", "note" } }`
- **Get / patch / activate / deactivate**: body is the **Api** object (metadata + `status` + summary fields), not wrapped in `{ "api": ... }`
- **Delete**: `204` empty body
- **Connection test**: `200` with `{ "status": "ok"|"fail", "at", "latencyMs", "message" }` (HTTP stays 200 on failure; check `status`)
- **Schema introspect**: `{ "introspectedAt", "entityCount" }`
- **Schema rebuild**: `{ "rebuiltAt", "entityCount", "warnings": [ ... ] }`
- **Schema effective / introspected (generated on the fly)**: `{ "entities": { ... } }` — entity map from DB + `patch.php`
- **Schema introspected (snapshot file)**: JSON saved at introspect time (entity map at top level when read from `schema_introspected.json`)

### Errors

```json
{
  "error": {
    "code": 3002,
    "message": "API not found",
    "details": { "apiId": "missing" }
  }
}
```

Numeric `code` values come from [`errorscatalog.php`](../src/application/config/errorscatalog.php).

### Api resource object

Returned by list items, `GET/PATCH .../apis/{apiId}`, and lifecycle activate/deactivate. Fields from `meta.php` plus summaries:

| Field | Description |
|-------|-------------|
| `id`, `name`, `description`, `contact`, `createdAt`, `updatedAt` | Metadata |
| `status` | `draft`, `active`, or `inactive` |
| `connection.configured` | Whether `connection.php` has a database |
| `connection.lastTest` | `{ status, at, message }` after `connection:test` |
| `policies.configNetworkConfigured` | `admin_config.php` exists |
| `policies.dataNetworkConfigured` | `data_api_acls.php` exists |
| `schema.introspectedAt` | Last `schema:introspect` time |
| `schema.effectiveVersion` | Set when `structure.php` exists |

`GET .../connection` returns the wire format (`driver`, `host`, `port`, …) with `password` masked. `GET .../policies/auth` returns the **on-disk** shape (`loginQuery`, masked `jwt_key`), not necessarily the same as the PUT body.

---

## Quick start (stepped)

Set your instance secret (default in dev: `myverysecuresecret`).

### 1. Create draft API

```bash
curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis" \
  -H "Content-Type: application/json" \
  -H "X-Management-Key: myverysecuresecret" \
  -d '{"name":"demo","description":"My API"}'
```

Response includes `api` and `managementCredential.secret` — save the secret.

### 2. Connection

```bash
curl -sS -X PUT "http://localhost/dbapi/src/mgmt/v1/apis/demo/connection" \
  -H "Content-Type: application/json" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET" \
  -d '{
    "driver": "mysql",
    "host": "127.0.0.1",
    "port": 3306,
    "database": "dbapi_test",
    "username": "dbapi",
    "password": "dbapi"
  }'

curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis/demo/connection:test" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"
```

Expect `"status": "ok"` on success. A failed test still returns HTTP `200` with `"status": "fail"` and `message` set.

Connection body uses `driver` `mysql` (mysqli). Fields: `host`, `port`, `database`, `username`, `password`.

### 3. Schema

```bash
curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis/demo/schema:introspect" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"

# Optional: inspect snapshot or effective schema before rebuild
curl -sS "http://localhost/dbapi/src/mgmt/v1/apis/demo/schema/introspected" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"

curl -sS "http://localhost/dbapi/src/mgmt/v1/apis/demo/schema/effective" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"

curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis/demo/schema:rebuild" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"
```

Optional: `GET/PUT/PATCH .../schema/overrides` to adjust `patch.php` (contents merged on rebuild).

`schema:rebuild` preserves existing **relationship names** (stable public API) and returns `warnings` (e.g. `ORPHAN_RELATION`, `QUALIFIED_RELATION_NAME`, `CONFLICT_RELATION_NAME`) when the database and prior schema diverge. Warnings are also stored in `meta.php` under `schema.lastWarnings`.

Each rebuild also writes **`openapi.json`** (validated, atomic write). The data API serves it at `GET /apis/{apiId}/swagger` (no runtime regeneration).

After changing only `patch.php`, refresh the spec without a full DB rebuild:

```bash
curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis/demo/schema:regenerate-openapi" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"

curl -sS "http://localhost/dbapi/src/mgmt/v1/apis/demo/schema/openapi" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"
```

See [OpenAPI pipeline](openapi_pipeline.md). Bulk refresh: `php scripts/generate_openapi_specs.php [configs_dir] [base_url]`.

### 4. Policies

```bash
# Data API auth: no login (guest read for GET — see data-network for stricter rules)
curl -sS -X PUT "http://localhost/dbapi/src/mgmt/v1/apis/demo/policies/auth" \
  -H "Content-Type: application/json" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET" \
  -d '{"mode":"none"}'

# Allow data API clients by IP (example: allow all — tighten in production)
curl -sS -X PUT "http://localhost/dbapi/src/mgmt/v1/apis/demo/policies/data-network" \
  -H "Content-Type: application/json" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET" \
  -d '{"defaultAction":"deny","rules":[{"cidr":"0.0.0.0/0","action":"allow"}]}'
```

### 5. Validate and activate

```bash
curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis/demo:validate" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"

curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis/demo:activate" \
  -H "X-Api-Config-Key: YOUR_PER_API_SECRET"
```

### 6. Use the Data API

```bash
curl -sS "http://localhost/dbapi/src/v1/apis/demo/data/customers"
```

Response is JSON:API (`data`, `meta`, …). See [Using the API](using_the_api.md).

---

## Quick-create (CI / migration)

One request provisions a draft API, saves connection, tests it, builds `structure.php` from the live database (`DBWalk::parse` + `patch.php` merge), validates, and activates. It does **not** run `schema:introspect` or `schema:rebuild` (no `schema_introspected.json`, no relationship-name preservation warnings). For production APIs with existing clients, prefer the stepped flow (introspect → optional overrides → rebuild → activate).

```bash
curl -sS -X POST "http://localhost/dbapi/src/mgmt/v1/apis?provision=immediate" \
  -H "Content-Type: application/json" \
  -H "X-Management-Key: myverysecuresecret" \
  -d '{
    "name": "demo",
    "connection": {
      "driver": "mysql",
      "host": "127.0.0.1",
      "port": 3306,
      "database": "dbapi_test",
      "username": "dbapi",
      "password": "dbapi"
    }
  }'
```

Alternative: header `Prefer: immediate-provision`.

On failure, the API may remain in `draft` with validation details in the response (`422`).

---

## Legacy endpoint (deprecated)

`POST {base}/apis` still works for backward compatibility. It maps to quick-create and returns deprecation headers (`Deprecation`, `Link`, `Sunset`).

Legacy connection shape (`hostname` instead of `host`) is accepted:

```json
{
  "name": "demo",
  "connection": {
    "hostname": "127.0.0.1",
    "username": "dbapi",
    "password": "dbapi",
    "database": "dbapi_test"
  }
}
```

Prefer `/mgmt/v1/apis` for new integrations.

---

## Endpoint reference

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/mgmt/v1/apis` | Instance | List APIs (`limit`, `offset`) |
| POST | `/mgmt/v1/apis` | Instance | Create draft (`?provision=immediate` for quick-create) |
| GET | `/mgmt/v1/apis/{apiId}` | Instance or per-API | Get API metadata |
| PATCH | `/mgmt/v1/apis/{apiId}` | Instance or per-API | Update name, description, contact |
| DELETE | `/mgmt/v1/apis/{apiId}` | Instance | Delete API (`?force=true` if active) |
| POST | `/mgmt/v1/apis/{apiId}/management-credentials:rotate` | Instance or per-API | Rotate per-API secret |
| GET | `/mgmt/v1/apis/{apiId}/connection` | Instance or per-API | Get connection (password masked) |
| PUT | `/mgmt/v1/apis/{apiId}/connection` | Instance or per-API | Set connection |
| POST | `/mgmt/v1/apis/{apiId}/connection:test` | Instance or per-API | Test DB connectivity |
| GET | `/mgmt/v1/apis/{apiId}/policies/config-network` | Instance or per-API | Config API IP policy |
| PUT | `/mgmt/v1/apis/{apiId}/policies/config-network` | Instance or per-API | Set config API IP policy |
| GET | `/mgmt/v1/apis/{apiId}/policies/data-network` | Instance or per-API | Data API IP policy |
| PUT | `/mgmt/v1/apis/{apiId}/policies/data-network` | Instance or per-API | Set data API IP policy |
| GET | `/mgmt/v1/apis/{apiId}/policies/auth` | Instance or per-API | Auth policy |
| PUT | `/mgmt/v1/apis/{apiId}/policies/auth` | Instance or per-API | Set auth policy (`none`, `dbAuth`) |
| POST | `/mgmt/v1/apis/{apiId}/schema:introspect` | Instance or per-API | Introspect DB; writes `schema_introspected.json` |
| GET | `/mgmt/v1/apis/{apiId}/schema/introspected` | Instance or per-API | Read introspection snapshot (or generate if missing) |
| GET | `/mgmt/v1/apis/{apiId}/schema/effective` | Instance or per-API | Effective schema preview (DB + `patch.php`, not written to disk) |
| GET | `/mgmt/v1/apis/{apiId}/schema/overrides` | Instance or per-API | Get `patch.php` overrides |
| PUT | `/mgmt/v1/apis/{apiId}/schema/overrides` | Instance or per-API | Replace overrides |
| PATCH | `/mgmt/v1/apis/{apiId}/schema/overrides` | Instance or per-API | Merge-patch overrides |
| POST | `/mgmt/v1/apis/{apiId}/schema:rebuild` | Instance or per-API | Build `structure.php`; response includes `warnings` |
| GET | `/mgmt/v1/apis/{apiId}/hooks` | Instance or per-API | All entity hooks |
| PUT | `/mgmt/v1/apis/{apiId}/hooks` | Instance or per-API | Replace all hooks (map) |
| PUT | `/mgmt/v1/apis/{apiId}/hooks/{entityName}` | Instance or per-API | Upsert hooks for one table |
| DELETE | `/mgmt/v1/apis/{apiId}/hooks/{entityName}` | Instance or per-API | Remove entity hooks |
| POST | `/mgmt/v1/apis/{apiId}:validate` | Instance or per-API | Preflight checks |
| POST | `/mgmt/v1/apis/{apiId}:activate` | Instance or per-API | Activate data API |
| POST | `/mgmt/v1/apis/{apiId}:deactivate` | Instance or per-API | Deactivate data API |

Colon actions (`:validate`, `:activate`, `connection:test`, `schema:introspect`) are literal path segments.

---

## On-disk configuration

Each API is stored under `configs_dir/{apiId}/` (see [`dbapiator.php`](../src/application/config/dbapiator.php)):

| File | Management resource |
|------|---------------------|
| `meta.php` | API metadata (`id`, `name`, timestamps, …) |
| `status.php` | `draft` \| `active` \| `inactive` |
| `connection.php` | Connection (mysqli-style internally) |
| `structure.php` | Effective schema (Data API) |
| `patch.php` | Schema overrides |
| `admin_config.php` | Per-API secret + config-network ACLs |
| `data_api_acls.php` | Data API IP + path rules |
| `authentication.php` | Data API auth (`mode`, `loginQuery`, JWT key, …) |

Hooks are stored **inside** `structure.php` per entity (`hooks` key on each resource).

---

## Auth policy (`policies/auth`)

| `mode` | Behavior |
|--------|----------|
| `none` | No JWT required; `allowGuest` enabled for the Data API |
| `dbAuth` | Login via SQL / procedure; JWT issued using `jwt_key` on disk |

Example `dbAuth` body (request; either nested `dbAuth` or flat `login` / `loginQuery` is accepted):

```json
{
  "mode": "dbAuth",
  "dbAuth": {
    "login": { "sql": "SELECT id AS user_id FROM app_users WHERE username = ? AND password = ?" },
    "validity": 3600
  }
}
```

On disk (`authentication.php`): `mode`, `loginQuery`, `validity`, `jwt_key` (masked on GET). `mode: none` stores `{ "mode": "none", "allowGuest": true }`.

---

## Network policies

**config-network** — Who may call Management operations for this API (stored in `admin_config.php`).

**data-network** — IP rules for clients calling the Data API (`data_api_acls.php` → `IP`). API shape: `{ "defaultAction": "allow"|"deny", "rules": [ { "cidr", "action" } ] }` (stored as `ip` on disk).

Default on draft create: allow caller IP, deny `0.0.0.0/0` for config and data IP ACLs; data **path** rules allow `GET`/`OPTIONS` and deny other methods. On **activate**, path rules are written only if `path` was empty. Override IP rules with `PUT .../policies/data-network` or `config-network`.

---

## Hooks

Webhooks are configured per **entity** (table/view name from schema):

```json
{
  "create": [
    { "url": "https://example.com/hooks/order", "method": "POST", "headers": {}, "body": null }
  ],
  "update": [],
  "delete": []
}
```

Delivery uses Redis Streams when `REDIS_HOST` is configured.

---

## Server configuration

Environment variables (optional):

| Variable | Purpose |
|----------|---------|
| `CONFIG_API_SECRET` | Instance `X-Management-Key` value |
| `CONFIG_API_IPS_ACLS` | JSON array of IP ACL rules for instance |
| `CONFIGS_DIR` | API config root directory |
| `REDIS_HOST`, `REDIS_PORT`, … | Hooks / streams |

---

## Testing

**Test database:** load [`src/tests/dbapi_test.sql`](../src/tests/dbapi_test.sql), then use [`src/tests/connection.json`](../src/tests/connection.json).

**Automated tests (PHPUnit + Guzzle):**

```bash
cd src && composer install
./vendor/bin/phpunit tests/TestManagementAPI.php
```

Config: copy [`src/tests/test.env.example`](../src/tests/test.env.example) to `src/tests/test.env` (loaded by `tests/bootstrap.php`).

Detailed scenarios: [management_api_test_plan.md](management_api_test_plan.md).

**Migrate existing APIs** (add `meta.php` / `status.php`):

```bash
php scripts/migrate_management_api.php /path/to/configs_dir
```

---

## Related documentation

- [Using the Data API](using_the_api.md)
- [Management API test plan](management_api_test_plan.md)
- [README — Getting started](../src/README.md)
