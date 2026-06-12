# dbAPI

**Turn your MySQL or MariaDB schema into a production-grade [JSON:API](https://jsonapi.org/) REST layer** — without writing CRUD routes for every table.

dbAPI introspects your database, generates configuration and OpenAPI, and serves governed HTTP access: filtering, relationships, field-level permissions, JWT auth, and lifecycle management. One installation can host many independent data APIs, each pointing at its own database.

```text
  Apps & integrations                dbAPI                         Your database
 ┌──────────────────┐    ┌─────────────────────────────┐    ┌─────────────────┐
 │ SPA, mobile,     │    │ Control plane  /mgmt/v1     │    │ MySQL / MariaDB │
 │ scripts, BI      │───▶│  create · configure ·       │───▶│ tables · views  │
 │ partner APIs     │    │  validate · activate        │    │ FKs · procedures│
 └──────────────────┘    │ Data plane  /v1/apis/…/data │    └─────────────────┘
                         │  JSON:API CRUD + policies   │              ▲
                         └──────────────┬──────────────┘              │
                                        │ per-API config              │
                                        ▼ (introspection)             │
                         ┌──────────────────────────────┐             │
                         │ dbconfigs/{apiId}/           │─────────────┘
                         │ structure · policies · hooks │
                         └──────────────────────────────┘
```

---

## Who it is for

dbAPI is for **teams that already build on MySQL or MariaDB** and need a governed HTTP API without maintaining CRUD code for every table. It works especially well when important rules live in the database (triggers, stored procedures, views) and clients need a stable, documented REST surface.

| Audience | Typical need |
|----------|----------------|
| **Developers & founders** | Ship internal tools, admin panels, or small line-of-business apps quickly; keep transactional logic in SQL instead of duplicating it in application controllers. |
| **Agencies & consultancies** | Deliver repeatable JSON:API + OpenAPI projects on client-owned databases, with per-project policies and docs. |
| **IT & operations teams** | Modernize access to an existing MySQL estate (self-hosted or on-prem) without adopting a full vendor ERP or a cloud-only BaaS. |
| **Products with many databases** | Host several independent APIs (`apiId`) on one installation — dev/staging/prod, multiple products, or per-tenant schemas. |

**Less suited if you need:** a hosted Postgres platform with built-in auth and realtime (Supabase-style), GraphQL as the primary protocol, or a framework where all business logic stays in application code only.

**Supported databases:** MySQL and MariaDB (mysqli). Other engines are not supported yet.

---

## Use cases

### REST layer over an existing schema

Point dbAPI at a database, introspect, set policies, activate — then consume tables and views as [JSON:API](https://jsonapi.org/) resources with filtering, relationships, field-level permissions, and JWT auth. Schema changes go through rebuild; clients stay aligned via generated OpenAPI.

**Consumers:** SPAs, mobile apps, partner integrations, scripts, reporting tools.

### Line-of-business and workflow applications

Back-office systems where the database is the system of record: ERP-style modules, approval flows, document lifecycle, inventory, or membership admin. dbAPI provides HTTP and documentation; invariants and workflows can stay in SQL.

### Extra API surface alongside an existing app

Add governed read/write access for integrations or new UIs without rewriting your monolith — same database, separate `apiId`, independent network and auth policies.

### Controlled rollout via the Management API

Create APIs in `draft`, test connections, introspect and rebuild schema, configure policies, run validation, then **activate** when ready. Deactivate without losing config. Fits operators and CI/CD pipelines that should not expose data endpoints until checks pass.

### Multi-API on one server

Run dev, staging, and production APIs — or separate products and tenants — as distinct `apiId` directories under `dbconfigs/`, each with its own connection, policies, and OpenAPI spec.

dbAPI is a **standalone HTTP service** (not embedded middleware). It is **not** a replacement for a full BaaS (auth platform, realtime, hosted database), a low-code admin UI, or a general microservices framework.

---

## Why dbAPI

Building a REST API on top of an existing database usually means endless boilerplate: one controller per table, hand-maintained OpenAPI, ad hoc filter syntax, and security bolted on later. Schema changes break clients or require another deploy.

dbAPI flips that model:

- **Schema is the source of truth** — introspect from `information_schema`, rebuild when the database evolves, preserve overrides in `patch.php`.
- **Policy before data** — APIs stay in `draft` until connection, schema, and policies pass validation; only then does the data plane go live.
- **Standards-shaped responses** — [JSON:API](https://jsonapi.org/) documents with relationships, sparse fieldsets, and consistent errors.
- **Operator-friendly control plane** — plain JSON Management API with OpenAPI validation, separate from consumer-facing data routes.

---

## Two planes, one product

| | Control plane | Data plane |
|---|---------------|------------|
| **Who** | Operators, CI/CD, admins | Applications and end users |
| **Path** | `/mgmt/v1/apis/...` | `/v1/apis/{apiId}/data/...` |
| **Format** | Plain JSON | JSON:API |
| **Purpose** | Define *whether* and *how* an API exists | Read and write *rows* |

**Control plane** — Create an API (`apiId`), attach a connection, introspect and rebuild schema, set network and auth policies, register webhooks, run preflight checks, then **activate**. Until activation, data routes do not serve traffic. Deactivate or delete without losing your override files (unless you remove the config directory).

**Data plane** — Once `active`, clients call familiar REST shapes: list and fetch resources, create and update records, traverse foreign keys, filter and paginate. Legacy paths under `/apis/...` still work; new integrations should use `/v1/apis/...`.

Full references: [Management API](docs/management_api.md) · [Using the data API](docs/using_the_api.md)

---

## Data plane — what you can do

### Automatic API surface

Every exposed table or view becomes a **resource type**. Foreign keys become **relationships** with stable names (preserved across schema rebuilds unless you override them). Each active API gets a cached **`openapi.json`** and works with the bundled **Swagger UI**.

```http
GET  /v1/apis/{apiId}/data/customers
GET  /v1/apis/{apiId}/data/customers/42
GET  /v1/apis/{apiId}/data/customers/42/orders
POST /v1/apis/{apiId}/data/customers
```

### Read with power

- **Filtering** — compact expression language (`=`, `>`, `>=`, contains, one-of, AND/OR, parentheses) compiled to safe SQL.
- **Sort** — `sort=name,-created_at` (per-resource when using `include`).
- **Pagination** — `page[offset]` and `page[limit]` with instance-level caps.
- **Sparse fieldsets** — `fields[customers]=name,email` to trim payloads.
- **Includes** — load related records in one round trip (`include=orders,account_manager_id`), depth-limited.
- **Filter on relationships** — e.g. customers that have orders matching a condition.
- **Views** — read-only resources; list and filter work; responses omit `id` when there is no primary key.
- **Export** — optional CSV or XLS on reads for spreadsheets and reporting.

### Write with control

- **Create / update / delete** — single records or bulk operations where supported.
- **Nested creates** — attach related records in one JSON:API payload (configurable recursion depth).
- **Duplicate handling** — `onduplicate=error|ignore|update` with field lists for upsert-style flows.
- **Filter-based updates and deletes** — bulk changes on matching rows (with guardrails).

### Security on every request

- **IP ACLs** for data endpoints (and separate rules for management/config traffic).
- **Path-based authorization** — restrict which URLs and HTTP methods callers may use.
- **JWT authentication** — issue tokens after a configurable SQL login query against the same database; optional guest/read modes.
- **Per-table and per-field ACLs** — control read, insert, update, delete, sort, and search per column.
- **Inactive APIs return 409** — configuration can continue while data traffic is blocked.

### Safety guardrails

Defaults are tunable via environment variables and `dbapiator.php` (and per-API query timeouts in `connection.php`):

| Guardrail | Typical default |
|-----------|-----------------|
| Page size | 100 default, 1000 max |
| Filter expression size / AST complexity | 4096 chars, depth 20, 100 nodes |
| Bulk insert / update per request | 100 / 50 records |
| Request and query timeout | 60 seconds |
| Nested `include` depth | 5 levels |

Every response can carry **`X-Request-Id`** for correlation; errors include `meta.request_id` when applicable.

---

## Control plane — how operators work

Typical activation flow:

```text
POST /mgmt/v1/apis                    → draft (save per-API credential once)
PUT  .../connection                   → database credentials
POST .../connection:test              → verify connectivity
POST .../schema:introspect            → snapshot from information_schema
POST .../schema:rebuild               → structure.php + openapi.json
PUT  .../policies/auth                → none, or dbAuth + JWT
PUT  .../policies/data-network        → IP rules for data clients
POST ...:validate                     → readiness report (required checks)
POST ...:activate                     → data plane live
```

**Management capabilities include:**

- **API lifecycle** — draft, active, inactive; clone, rename, delete (`force` when still active).
- **Schema tools** — introspect, effective schema view, overrides via `patch.php`, rebuild with warnings.
- **Policies** — config-network (who may manage this API), data-network (who may call data routes), auth (mode and login query).
- **Hooks** — per-entity webhook targets for create/update/delete (delivered via **Redis Streams** when Redis is configured).
- **Credential rotation** — per-API config keys without recreating the API.
- **Quick-create** — `POST /mgmt/v1/apis?provision=immediate` with connection in one step when appropriate.

**Authentication:** instance key (`X-Management-Key`) for full admin access; per-API key (`X-Api-Config-Key`) scoped to one API’s configuration directory. Request bodies are validated against the Management OpenAPI spec.

---

## Integration and extension

- **Webhooks** — publish write events to Redis Streams for async dispatchers or downstream pipelines.
- **Resource hooks** — PHP hook files under the API config (e.g. `before.insert.php`, `after.insert.php`) for custom logic.
- **Schema overrides** — rename resources, hide tables, tune relationship names without forking generated structure by hand.
- **OpenAPI everywhere** — management spec at `src/public/management-openapi.yaml`; per-API spec generated on rebuild and served from disk (no regeneration per request).
- **Multi-API hosting** — dev, staging, and tenant-specific APIs as separate `apiId` directories under `dbconfigs/`.

---

## Quick start

### Docker (fastest)

**From source (local dev):**

```bash
docker compose up -d
```

**From GitHub Container Registry** (published on each release tag, e.g. `v1.0.0`):

```bash
docker pull ghcr.io/dbapiator/dbapi:latest
# or pin a version: ghcr.io/dbapiator/dbapi:1.0.0
```

Run with your own MySQL/MariaDB and Redis — mount a writable `dbconfigs` volume and set env vars (`CONFIGS_DIR`, `CONFIG_API_SECRET`, `DB_*`, `REDIS_*`, etc.). For single-API mode add `DEPLOYMENT_MODE=single` and the `DB_*` connection settings (see [`docker-compose.yml`](docker-compose.yml)).

| Service | URL |
|---------|-----|
| dbAPI | http://localhost:8888/ |
| Adminer | http://localhost:8889/ |
| MariaDB | `localhost:3306` (database `myapp`) |

Docker Compose runs dbAPI in **single deployment mode** (`DEPLOYMENT_MODE=single`). On first start the container waits for MySQL, auto-provisions the `default` API from `DB_*` environment variables, and serves data at `/v1/data/...`.

MariaDB is seeded with the full data-plane test schema on **first** database init (`docker/mysql-init/`). To re-run the seed, remove the MySQL data volume first: `rm -rf .docker_data/mysql && docker compose up -d`.

```bash
# Service discovery
curl -sS http://localhost:8888/

# Data plane
curl -sS http://localhost:8888/v1/data/customers

# OpenAPI spec + Swagger UI
curl -sS http://localhost:8888/v1/swagger
open 'http://localhost:8888/swagger.html?url=v1/swagger'
```

Management API (configure policies, rebuild schema, etc.) uses the fixed id **`default`**:

```bash
curl -sS http://localhost:8888/mgmt/v1/apis/default \
  -H 'X-Management-Key: myverysecuresecret'
```

Instance secret: `CONFIG_API_SECRET` in `docker-compose.yml` (default `myverysecuresecret`) → header **`X-Management-Key`**.

For **multi-API hosting** (dev/staging/prod on one install), omit `DEPLOYMENT_MODE=single` and use the standard flow:

```bash
curl -sS -X POST 'http://localhost:8888/mgmt/v1/apis?provision=immediate' \
  -H 'Content-Type: application/json' \
  -H 'X-Management-Key: myverysecuresecret' \
  -d '{"name":"demo","connection":{"driver":"mysql","host":"mysql","port":3306,"database":"myapp","username":"user","password":"password"}}'
```

Data endpoints: `http://localhost:8888/v1/apis/demo/data/{resource}`

### Local Apache / PHP

**Requirements:** PHP 7.4+, `mysqli`, `json`, `mbstring`, `curl`.

1. Point the web root at [`src/`](src/) (e.g. `http://localhost/dbapi/src`).
2. Ensure [`dbconfigs/`](dbconfigs/) is writable by the web server user.
3. Configure [`src/application/config/dbapiator.php`](src/application/config/dbapiator.php) — `configs_dir`, secrets, paging limits (or use env vars).

**Swagger UI:** `{base}/swagger.html?url=management-openapi.yaml` · per-API: `{base}/swagger.html?url=apis/{apiId}/swagger`

---

## Repository layout

| Path | Purpose |
|------|---------|
| [`src/`](src/) | PHP application (document root) |
| [`dbconfigs/`](dbconfigs/) | Generated per-API configuration |
| [`docs/`](docs/) | Guides and test plans |
| [`docker-compose.yml`](docker-compose.yml) | Local dev stack |
| [`tests/`](tests/) | Schemathesis harness (optional) |

Application entry point: [`src/index.php`](src/index.php).

---

## Tests

Integration tests (PHPUnit + Guzzle) run against a live instance:

```bash
# Unit tests only (fast, no server/DB)
cd src && composer install && composer test:unit

# Multi-API mode (local Apache)
composer test:dataplane-setup   # from src/ — loads DB + copies test env
cd src && composer test:integration -- --filter TestDataPlaneAPI

# Single-mode Docker
docker compose up -d
cp src/tests/test.env.single.example src/tests/test.env
cd src && ./vendor/bin/phpunit tests/TestSingleModeDataPlaneAPI.php
```

| Suite | Focus |
|-------|--------|
| `composer test:unit` | Filter parser, OpenAPI, schema sync checks (~14 tests) |
| `TestManagementAPI` | Control plane lifecycle |
| `TestDataPlaneAPI` | Full data-plane coverage (multi-API, ~55 tests) |
| `TestSingleModeDataPlaneAPI` | Same scenarios via Docker `/v1/data/...` |

Details: [management_api_test_plan.md](docs/management_api_test_plan.md) · [data_plane_test_plan.md](docs/data_plane_test_plan.md)

---

## Documentation

- [Management API](docs/management_api.md) — control plane reference
- [Using the API](docs/using_the_api.md) — filters, pagination, relationships, writes
- [OpenAPI pipeline](docs/openapi_pipeline.md) — how specs are generated and validated

---

## License

MIT — see [LICENSE.md](LICENSE.md).
