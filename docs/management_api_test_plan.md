# Management API — test plan

User guide: **[management_api.md](management_api.md)**

Base URL: `http://localhost/dbapi/src` (or `BASE_URL`)

Auth:
- Instance: `X-Management-Key` (aliases: `X-Admin-API-Key`, `X-Api-Key`, `x-api-key` query)
- Per-API: `X-Api-Config-Key` (aliases: `x-api-config-key`, `x-api-key`; returned once on create)

Test database (`dbapi_test` — control plane only; see [data_plane_test_plan.md](data_plane_test_plan.md) for `dbapi_dataplane`):


| Setting  | Value        |
|----------|--------------|
| Host     | 127.0.0.1    |
| Port     | 3306         |
| User     | dbapi        |
| Password | dbapi        |
| Database | dbapi_test   |

Connection JSON: [`src/tests/connection.json`](../src/tests/connection.json)

OpenAPI: `src/public/management-openapi.yaml`

## Happy path (stepped)

1. `POST /mgmt/v1/apis` — create draft (defaults: auth none, permissive network), save credential
2. `PUT /mgmt/v1/apis/{apiId}/connection` — set DB connection
3. `POST /mgmt/v1/apis/{apiId}/connection:test` — expect `status: ok`
4. `POST /mgmt/v1/apis/{apiId}/schema:sync?activate=true` — sync schema and activate
5. `GET /v1/apis/{apiId}/data/...` — data API serves
6. `POST /mgmt/v1/apis/{apiId}:deactivate` — data API blocked (409)
7. `DELETE /mgmt/v1/apis/{apiId}?force=true`

Optional: `POST ...:validate` before activate (dry-run). Stepped schema: `schema:introspect` → overrides → `schema:rebuild` → `POST ...:activate`.

## Quick-create

`POST /mgmt/v1/apis?provision=immediate` with `name` + `connection`

## Legacy shim

`POST /apis` with legacy body (`hostname` in connection) — deprecation headers present

## Run automated tests

```bash
cd src
composer install
./vendor/bin/phpunit tests/TestManagementAPI.php
```

Uses `src/tests/connection.json` by default. Override via `src/tests/test.env` (loaded by `tests/bootstrap.php`; see `test.env.example`).
