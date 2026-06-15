# Tutorial 1 — Getting started

In this tutorial you will run dbAPI locally, confirm it is healthy, and fetch your first records from the database.

**Time:** ~10 minutes  
**Next:** [JSON:API basics](02-json-api-basics.md)

---

## What you are building toward

dbAPI turns a MySQL or MariaDB schema into a [JSON:API](https://jsonapi.org/) REST layer. Your database tables become **resources**; foreign keys become **relationships**. There are two planes:

| Plane | Who uses it | Example path |
|-------|-------------|--------------|
| **Data plane** | Your app, scripts, integrations | `/v1/data/customers` |
| **Control plane** (Management API) | Operators, CI/CD | `/mgmt/v1/apis/default` |

This tutorial focuses on the **data plane** — reading rows that already exist in the demo database.

---

## Step 1 — Start the dev stack

From the repository root:

```bash
docker compose up -d
```

Wait until the dbAPI container is healthy, then open the service root:

```bash
curl -sS http://localhost:8888/
```

You should see JSON with path hints for management, data, auth, and swagger. The local stack runs in **single deployment mode**: one fixed API (`default`) auto-provisioned from environment variables.

---

## Step 2 — Explore with Swagger UI

Open the interactive API explorer in your browser:

```text
http://localhost:8888/swagger.html?url=v1/swagger
```

The OpenAPI spec is generated when the schema is built. Browse the **customers**, **orders**, **products**, and **order_lines** resources — these map directly to tables in the `myapp` database.

To fetch the raw spec:

```bash
curl -sS http://localhost:8888/v1/swagger | jq '.paths | keys[:5]'
```

**Tip:** Always check OpenAPI (or Swagger) before guessing field or relationship names. dbAPI derives names from your schema; they are not always obvious pluralizations.

---

## Step 3 — List customers

```bash
curl -sS 'http://localhost:8888/v1/data/customers?page[limit]=3' | jq .
```

A typical response:

```json
{
  "data": [
    {
      "id": "1",
      "type": "customers",
      "attributes": {
        "name": "Alice Example",
        "email": "alice@example.com",
        "country_code": "US",
        "account_manager_id": 1,
        "created_at": "..."
      },
      "relationships": {
        "orders": { "data": [ { "id": "1", "type": "orders" }, ... ] },
        "account_manager_id": { "data": { "id": "1", "type": "users" } }
      }
    }
  ],
  "meta": { "total": 3, "offset": 0 },
  "included": []
}
```

Key observations:

- **`type`** matches the table name (`customers`).
- **`id`** is the primary key as a string.
- Column values live in **`attributes`**, not at the root of the object.
- **`relationships`** lists linked records (foreign keys and child tables).
- **`meta.total`** tells you how many rows match (before pagination).

---

## Step 4 — Fetch one customer by id

```bash
curl -sS http://localhost:8888/v1/data/customers/1 | jq .
```

The shape is the same, but `data` is a single object instead of an array.

If you request an id that does not exist, you get a JSON:API error:

```json
{
  "errors": [{ "status": "404", "title": "...", "detail": "..." }],
  "meta": { "request_id": "..." }
}
```

---

## Step 5 — Request correlation

Every response includes an **`X-Request-Id`** header. Send your own for log correlation:

```bash
curl -sS -H 'X-Request-Id: tutorial-01-demo' \
  http://localhost:8888/v1/data/customers/1 -D - -o /dev/null | grep -i x-request-id
```

When something fails, include this id when asking for help or searching server logs.

---

## Step 6 — Peek at the Management API (optional)

Operators use the Management API to configure connection, schema, and policies. In single-mode Docker the API id is always **`default`**:

```bash
curl -sS http://localhost:8888/mgmt/v1/apis/default \
  -H 'X-Management-Key: myverysecuresecret' | jq '{ name, status, connection: .connection.configured }'
```

You should see `"status": "active"`. If the data plane returned **409 API not active**, the API has not been activated yet — see [Tutorial 7](07-provisioning-an-api.md).

**Do not** expose the management key in browser-side code. It belongs in server-side tooling and CI only.

---

## What you learned

- dbAPI exposes database tables as JSON:API resources at `/v1/data/{resource}`.
- Responses separate **`attributes`** (columns) from **`relationships`** (foreign keys and children).
- OpenAPI at `/v1/swagger` is the contract for field and relationship names.
- The Management API (`/mgmt/v1/...`) configures whether the data plane is live.

---

## Exercises

1. List all **products** and note which ones have `is_active: 0`.
2. Fetch customer id `2` and read the `orders` relationship identifiers without loading full order rows.
3. Request a non-existent id (`/v1/data/customers/99999`) and inspect the error payload.

---

## Next step

[JSON:API basics](02-json-api-basics.md) — create, update, and delete records.
