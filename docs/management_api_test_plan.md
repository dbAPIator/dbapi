# Management API — test plan

User guide: **[management_api.md](management_api.md)**

Base URL: `http://localhost/dbapi/src` (or `BASE_URL`)

Auth:
- Instance: `X-Management-Key` (aliases: `X-Admin-API-Key`, `X-Api-Key`, `x-api-key` query)
- Per-API: `X-Api-Config-Key` (aliases: `x-api-config-key`, `x-api-key`; returned once on create)

Test database (`dbapi_test`):

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

1. `POST /mgmt/v1/apis` — create draft, save credential
2. `PUT /mgmt/v1/apis/{apiId}/connection` — set DB connection
3. `POST /mgmt/v1/apis/{apiId}/connection:test` — expect `status: ok`
4. `POST /mgmt/v1/apis/{apiId}/schema:introspect` — optional `GET .../schema/introspected` or `.../schema/effective`
5. `POST /mgmt/v1/apis/{apiId}/schema:rebuild` — check `warnings` in response
6. `PUT /mgmt/v1/apis/{apiId}/policies/auth` — `{ "mode": "none" }`
7. `POST /mgmt/v1/apis/{apiId}:validate` — `ready: true`
8. `POST /mgmt/v1/apis/{apiId}:activate` — `status: active`
9. `GET /v1/apis/{apiId}/data/...` — data API serves
10. `POST /mgmt/v1/apis/{apiId}:deactivate` — data API blocked (409)
11. `DELETE /mgmt/v1/apis/{apiId}?force=true`

## Quick-create

`POST /mgmt/v1/apis?provision=immediate` with `name` + `connection`

## Legacy shim

`POST /apis` with legacy body (`hostname` in connection) — deprecation headers present

## Run automated tests

### Bash e2e (stepped happy path)

```bash
chmod +x src/test_management_api.sh
./src/test_management_api.sh
```

Uses `src/tests/connection.json` by default. Override via `src/tests/test.env` (see `test.env.example`).

### PHPUnit

```bash
cd src
composer install
./vendor/bin/phpunit tests/TestManagementAPI.php
```
