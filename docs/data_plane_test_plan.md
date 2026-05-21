# Data plane — test plan

User guide: **[using_the_api.md](using_the_api.md)**  
Control plane setup: **[management_api_test_plan.md](management_api_test_plan.md)**

Base URL: `http://localhost/dbapi/src` (or `BASE_URL`)

The data plane is the JSON:API surface at `/v1/apis/{apiId}/data/...`. Tests provision a throwaway API via the Management API (same flow as control-plane tests), then exercise read/write behavior against a dedicated MySQL database.

## Test database (`dbapi_dataplane`)

Load once before running tests:

```bash
mysql -u root -p < src/tests/dbapi_dataplane.sql
```

| Setting  | Value              |
|----------|--------------------|
| Host     | 127.0.0.1          |
| Port     | 3306               |
| User     | dbapi              |
| Password | dbapi              |
| Database | **dbapi_dataplane**|

Connection JSON: [`src/tests/connection.json`](../src/tests/connection.json) — set `"database": "dbapi_dataplane"` (see `connection.dataplane.example.json`).

### What the schema covers

| Area | Tables / objects | Used for |
|------|------------------|----------|
| Commerce + FKs | `customers`, `products`, `orders`, `order_lines` | CRUD, relationships, `include`, RESTRICT/CASCADE |
| Filter fixtures | `filter_cases` (fixed IDs 1–6) | `=`, `>`, `><`, `||`, `()`, sort |
| Types | `catalog_items` (JSON, decimal, nullable dates) | Sparse fields, type coercion |
| Text / nulls | `notes` | Optional FK, commas in values |
| Extra graph | `suppliers`, `shipments` | Additional inbound relations |
| Read-only | `v_order_totals_by_day` | View GET; POST must fail |
| Auth (optional) | `app_users`, `v_app_login`, `sp_validate_login` | Future auth-mode tests |

The lighter **`dbapi_test`** database remains for Management API e2e only; use **`dbapi_dataplane`** for full data-plane coverage.

## Scenarios (automated)

### Read

- List collection (`GET .../data/customers`) — JSON:API `data` + `meta`
- Get by id — 200 with attributes
- Unknown resource — 404
- Missing id — 404
- Sparse fieldset (`fields=name,email`)
- Read-only view — 200 list

### Filters & sort

- Equality on `filter_cases`
- Greater-than + descending sort
- One-of (`><`)
- OR grouping with parentheses
- No match — empty `data`
- Malformed filter — 400/422

### Pagination

- `page[limit]` + `page[offset]`

### Relationships

- `include=orders` on customer
- `GET .../customers/{id}/orders`

### Write (happy path)

- POST customer → PATCH name → DELETE

### Negative / constraints

- Duplicate unique (`email`) — 409
- `onduplicate=ignore` on `products.sku`
- Invalid FK on `orders.customer_id` — 4xx/5xx
- DELETE customer with orders (RESTRICT) — error
- Bulk PATCH without filter — 400
- Empty POST body — 400
- POST to view — error
- Correlation: `X-Request-Id` echoed

## Run automated tests

```bash
cd src
composer install
# ensure connection.json points at dbapi_dataplane
./vendor/bin/phpunit tests/TestDataPlaneAPI.php
```

Or run the full suite: `./vendor/bin/phpunit`

`src/tests/test.env` is loaded automatically by `tests/bootstrap.php` (see `test.env.dataplane.example`).

## Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `BASE_URL` | `http://localhost/dbapi/src/` | dbAPI base |
| `MGMT_KEY` | `myverysecuresecret` | `X-Management-Key` |
| `CONNECTION_JSON` | — | Inline connection JSON (overrides file) |

## Relationship to control-plane tests

| Suite | Database | Focus |
|-------|----------|--------|
| `TestManagementAPI` | `dbapi_test` | Lifecycle, policies, activate/deactivate |
| `TestDataPlaneAPI` | `dbapi_dataplane` | CRUD, filters, relationships, negatives |

Both suites create ephemeral APIs and delete them on completion.
