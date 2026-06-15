# Tutorial 9 — Schema customization

Tailor the generated API surface: hide tables, rename relationships, refresh OpenAPI, and expose stored procedures.

**Prerequisites:** [Security policies](08-security-policies.md)  
**Next:** [Advanced integration](10-advanced-integration.md)

---

## Generated vs effective schema

```text
MySQL information_schema
        │
        ▼
  schema:introspect  →  schema_introspected.json (snapshot)
        │
        ▼
  patch.php overrides (your edits)
        │
        ▼
  schema:rebuild  →  structure.php + openapi.json
```

**`structure.php`** is what the data plane serves. **`openapi.json`** is the client contract.

```bash
export BASE=http://localhost:8888
export MGMT_KEY=myverysecuresecret
export API=default
```

---

## Inspect schemas

**Snapshot** (last introspect):

```bash
curl -sS "$BASE/mgmt/v1/apis/$API/schema/introspected" \
  -H "X-Management-Key: $MGMT_KEY" | jq 'keys'
```

**Effective preview** (live DB + overrides, not written to disk):

```bash
curl -sS "$BASE/mgmt/v1/apis/$API/schema/effective" \
  -H "X-Management-Key: $MGMT_KEY" | jq '.entities | keys'
```

**Current overrides:**

```bash
curl -sS "$BASE/mgmt/v1/apis/$API/schema/overrides" \
  -H "X-Management-Key: $MGMT_KEY" | jq .
```

---

## Hide a table from the API

Exclude internal tables from the public surface:

```bash
curl -sS -X PATCH "$BASE/mgmt/v1/apis/$API/schema/overrides" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "app_users": {
      "expose": false
    }
  }'
```

Rebuild to apply:

```bash
curl -sS -X POST "$BASE/mgmt/v1/apis/$API/schema:rebuild" \
  -H "X-Management-Key: $MGMT_KEY" | jq '{ entityCount, warnings }'
```

Confirm `app_users` no longer appears in OpenAPI. **Restore** by removing the override and rebuilding when done.

---

## Rename a relationship

Relationship names are stable across rebuilds, but you can rename for clarity:

```bash
curl -sS -X PATCH "$BASE/mgmt/v1/apis/$API/schema/overrides" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "customers": {
      "relations": {
        "account_manager_id": {
          "name": "account_manager"
        }
      }
    }
  }'
```

After rebuild, clients use `include=account_manager` instead of `account_manager_id`. Coordinate with API consumers — this is a breaking change unless versioned.

---

## Rebuild warnings

Rebuild returns warnings when DB and prior schema diverge:

| Warning | Meaning |
|---------|---------|
| `ORPHAN_RELATION` | FK removed from DB; old relationship kept for compatibility |
| `QUALIFIED_RELATION_NAME` | Ambiguous inbound name qualified |
| `CONFLICT_RELATION_NAME` | Name collision resolved |

Clean up `patch.php` when you intentionally remove relationships.

---

## Regenerate OpenAPI only

After editing overrides without DB changes:

```bash
curl -sS -X POST "$BASE/mgmt/v1/apis/$API/schema:regenerate-openapi" \
  -H "X-Management-Key: $MGMT_KEY"

curl -sS "$BASE/mgmt/v1/apis/$API/schema/openapi" \
  -H "X-Management-Key: $MGMT_KEY" | jq '.info.title'
```

Validation runs on `:validate` — stale `openapi.json` may produce a **warn** on `schema.openapi`.

---

## Views

Views like **`v_order_totals_by_day`** appear as read-only resources automatically on rebuild. They support list, filter, sort, and export. Without a primary key, GET by id returns **404**.

Use views to expose safe, pre-joined reporting shapes without duplicating logic in app code.

---

## Stored procedures

The demo includes **`sp_validate_login`**. Call procedures via:

```bash
curl -sS -X POST "$BASE/v1/data/__call__/sp_validate_login" \
  -H 'Content-Type: application/json' \
  -d '{ "params": ["testuser", "testpass"] }' | jq .
```

Parameter order and types come from OpenAPI — always check the spec after rebuild.

Procedures must exist in the database before rebuild to appear in the API surface.

---

## When to introspect vs rebuild

| Action | When |
|--------|------|
| `schema:introspect` | After DDL changes in MySQL; captures raw snapshot |
| `schema:rebuild` | Apply snapshot + overrides → `structure.php` |
| `schema:regenerate-openapi` | Overrides changed, DB unchanged |

Production workflow:

1. Deploy migration to MySQL
2. `schema:introspect`
3. Review `schema/effective`
4. Adjust `patch.php` if needed
5. `schema:rebuild`
6. `:validate` → redeploy clients if OpenAPI changed

---

## What you learned

- `patch.php` overrides merge on rebuild without hand-editing `structure.php`.
- Hide tables, rename relationships, and tune field ACLs through overrides.
- Views and procedures extend the API beyond raw tables.
- OpenAPI regenerates on rebuild; refresh standalone when only overrides change.

---

## Exercises

1. List all entity names from `schema/effective`.
2. Call `sp_validate_login` with wrong password and inspect the response.
3. Hide `filter_cases`, rebuild, confirm it is gone from `/v1/data/filter_cases`, then restore.

---

## Next step

[Advanced integration](10-advanced-integration.md) — multi-API, webhooks, production patterns.
