# Tutorial 7 — Provisioning an API

Use the Management API to create, configure, validate, and activate a data API. This is the operator workflow for multi-API hosting and custom deployments.

**Prerequisites:** [Authentication](06-authentication.md)  
**Next:** [Security policies](08-security-policies.md)

---

## Lifecycle overview

```text
POST /mgmt/v1/apis          → draft
PUT  .../connection         → database credentials
POST .../connection:test    → verify connectivity
POST .../schema:introspect  → snapshot from information_schema
POST .../schema:rebuild     → structure.php + openapi.json
PUT  .../policies/*         → network + auth
POST ...:validate           → readiness report
POST ...:activate           → data plane live
```

Until **activate**, data routes return **409 API not active**.

---

## Environment

This tutorial uses **multi-API mode** paths. With the local Compose stack (single-mode), you can still run most commands against `default`, or spin up multi-API by changing `DEPLOYMENT_MODE` — for learning, we create a second API named **`tutorial`** against the same `myapp` database.

```bash
export BASE=http://localhost:8888
export MGMT_KEY=myverysecuresecret
```

---

## Option A — Stepped flow (recommended for production)

### 1. Create draft API

```bash
curl -sS -X POST "$BASE/mgmt/v1/apis" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{"name":"tutorial","description":"Tutorial API"}' | tee /tmp/create-api.json | jq .
```

Save the per-API secret from `managementCredential.secret`:

```bash
export API_KEY=$(jq -r '.managementCredential.secret' /tmp/create-api.json)
```

This secret is shown **once**. Store it in your secrets manager.

### 2. Configure connection

```bash
curl -sS -X PUT "$BASE/mgmt/v1/apis/tutorial/connection" \
  -H 'Content-Type: application/json' \
  -H "X-Api-Config-Key: $API_KEY" \
  -d '{
    "driver": "mysql",
    "host": "mysql",
    "port": 3306,
    "database": "myapp",
    "username": "user",
    "password": "password"
  }'
```

Use `host: mysql` inside Docker Compose; use `127.0.0.1` from the host when dbAPI runs on bare Apache.

### 3. Test connection

```bash
curl -sS -X POST "$BASE/mgmt/v1/apis/tutorial/connection:test" \
  -H "X-Api-Config-Key: $API_KEY" | jq .
```

Expect `"status": "ok"`. Failures still return HTTP 200 with `"status": "fail"` — check the JSON body.

### 4. Introspect and rebuild schema

```bash
curl -sS -X POST "$BASE/mgmt/v1/apis/tutorial/schema:introspect" \
  -H "X-Api-Config-Key: $API_KEY" | jq .

curl -sS -X POST "$BASE/mgmt/v1/apis/tutorial/schema:rebuild" \
  -H "X-Api-Config-Key: $API_KEY" | jq '{ entityCount, warnings }'
```

Rebuild writes `structure.php` and **`openapi.json`**. Review `warnings` after schema changes.

### 5. Set policies

Allow data access (tighten in production):

```bash
curl -sS -X PUT "$BASE/mgmt/v1/apis/tutorial/policies/data-network" \
  -H 'Content-Type: application/json' \
  -H "X-Api-Config-Key: $API_KEY" \
  -d '{"defaultAction":"deny","rules":[{"cidr":"0.0.0.0/0","action":"allow"}]}'

curl -sS -X PUT "$BASE/mgmt/v1/apis/tutorial/policies/auth" \
  -H 'Content-Type: application/json' \
  -H "X-Api-Config-Key: $API_KEY" \
  -d '{"mode":"none"}'
```

### 6. Validate and activate

```bash
curl -sS -X POST "$BASE/mgmt/v1/apis/tutorial:validate" \
  -H "X-Api-Config-Key: $API_KEY" | jq .

curl -sS -X POST "$BASE/mgmt/v1/apis/tutorial:activate" \
  -H "X-Api-Config-Key: $API_KEY" | jq '{ name, status }'
```

All required checks must be `ok`. Warnings (e.g. stale OpenAPI) may still allow activation.

### 7. Use the data plane

```bash
curl -sS "$BASE/v1/apis/tutorial/data/customers?page[limit]=2" | jq .
```

Swagger: `http://localhost:8888/swagger.html?url=apis/tutorial/swagger`

---

## Option B — Quick-create (dev / CI)

One request creates, connects, builds schema, validates, and activates:

```bash
curl -sS -X POST "$BASE/mgmt/v1/apis?provision=immediate" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "name": "quickdemo",
    "connection": {
      "driver": "mysql",
      "host": "mysql",
      "port": 3306,
      "database": "myapp",
      "username": "user",
      "password": "password"
    }
  }' | jq .
```

Quick-create skips introspect/rebuild warnings about relationship name preservation. For APIs with existing clients, prefer the stepped flow.

---

## Deactivate and delete

```bash
# Stop serving data; keep config files
curl -sS -X POST "$BASE/mgmt/v1/apis/tutorial:deactivate" \
  -H "X-Management-Key: $MGMT_KEY" | jq .status

# Remove API directory (force if still active)
curl -sS -X DELETE "$BASE/mgmt/v1/apis/tutorial?force=true" \
  -H "X-Management-Key: $MGMT_KEY" -w '\nHTTP %{http_code}\n'
```

---

## Two key types

| Header | Scope |
|--------|-------|
| `X-Management-Key` | Instance — create/delete any API |
| `X-Api-Config-Key` | Per-API — connection, schema, policies |

Never expose either key in frontend code.

---

## On-disk layout

Each API lives under `configs_dir/{apiId}/`:

| File | Purpose |
|------|---------|
| `connection.php` | Database credentials |
| `structure.php` | Effective schema for data plane |
| `patch.php` | Schema overrides |
| `authentication.php` | Auth policy |
| `data_api_acls.php` | IP and path rules |
| `openapi.json` | Generated API contract |

---

## What you learned

- Draft → configure → validate → activate is the safe production path.
- Quick-create suits dev and CI when relationship stability is not critical.
- Multi-API data URLs use `/v1/apis/{apiId}/data/...`.
- Per-API secrets are returned once at creation.

---

## Exercises

1. Run `:validate` on the `default` API and list any warnings.
2. Create `tutorial` (or use quick-create), fetch one resource, then deactivate it and confirm **409**.
3. Open the generated OpenAPI spec and find the `orders` resource definition.

---

## Next step

[Security policies](08-security-policies.md) — IP ACLs, path rules, and scoped access.
