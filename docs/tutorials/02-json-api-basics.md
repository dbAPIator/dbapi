# Tutorial 2 — JSON:API basics

This tutorial covers the request and response format for creating, updating, and deleting individual records.

**Prerequisites:** [Getting started](01-getting-started.md)  
**Next:** [Reading and querying](03-reading-and-querying.md)

---

## The JSON:API write shape

All write requests use `Content-Type: application/json` and wrap the payload in a **`data`** object:

```json
{
  "data": {
    "type": "resource_name",
    "attributes": { "column": "value" }
  }
}
```

Rules:

1. **`type`** must match the resource (table) name exactly.
2. On PATCH, include **`id`** in `data` and it must match the URL.
3. Put database columns in **`attributes`** — never at the root of `data`.
4. Relationship links use **`relationships`** (covered in [Tutorial 4](04-relationships.md) and [Tutorial 5](05-writing-data.md)).

---

## Create a record (POST)

Add a note linked to customer 1:

```bash
curl -sS -X POST http://localhost:8888/v1/data/notes \
  -H 'Content-Type: application/json' \
  -d '{
    "data": {
      "type": "notes",
      "attributes": {
        "customer_id": 1,
        "body": "Created from tutorial 2",
        "priority": 1
      }
    }
  }' | jq .
```

The response returns the new row with its generated **`id`**. Save that id for the next steps.

### Common mistakes

| Mistake | Result |
|---------|--------|
| Flat JSON (`{"body": "..."}` without `data`) | **400** invalid body |
| Wrong `type` | **400** or **409** |
| Missing required column (if NOT NULL, no default) | **400** / database error |

---

## Update a record (PATCH)

Partial updates — send only the fields you want to change:

```bash
curl -sS -X PATCH http://localhost:8888/v1/data/notes/NOTE_ID \
  -H 'Content-Type: application/json' \
  -d '{
    "data": {
      "type": "notes",
      "id": "NOTE_ID",
      "attributes": {
        "body": "Updated from tutorial 2",
        "priority": 2
      }
    }
  }' | jq .
```

Replace `NOTE_ID` with the id from your POST response.

The **`id` in the body must match the id in the URL**. Mismatches produce **400**.

---

## Delete a record (DELETE)

```bash
curl -sS -X DELETE http://localhost:8888/v1/data/notes/NOTE_ID -w '\nHTTP %{http_code}\n'
```

A successful delete typically returns **204 No Content** with an empty body.

---

## Read after write

Confirm the change (or confirm deletion returns 404):

```bash
curl -sS http://localhost:8888/v1/data/notes/NOTE_ID
```

---

## Working with views

The demo schema includes read-only views such as **`v_order_totals_by_day`**. You can list and filter them:

```bash
curl -sS 'http://localhost:8888/v1/data/v_order_totals_by_day?page[limit]=5' | jq .
```

Views **without a primary key** behave differently:

- **GET** collection — works
- **GET** by id — **404**
- **POST / PATCH / DELETE** — error

Treat views as reporting endpoints, not writable resources.

---

## Unique constraints

The `customers` table has a unique email. Try creating a duplicate:

```bash
curl -sS -X POST http://localhost:8888/v1/data/customers \
  -H 'Content-Type: application/json' \
  -d '{
    "data": {
      "type": "customers",
      "attributes": {
        "name": "Duplicate Test",
        "email": "alice@example.com",
        "country_code": "US"
      }
    }
  }' | jq .
```

You should get a **409** conflict with a JSON:API error describing the unique violation.

---

## Response anatomy (quick reference)

**List:**

```json
{
  "data": [ { "id": "...", "type": "...", "attributes": {}, "relationships": {} } ],
  "meta": { "total": 100, "offset": 0 },
  "included": []
}
```

**Single resource:** same, but `data` is one object.

**Error:**

```json
{
  "errors": [ { "status": "400", "title": "...", "detail": "..." } ],
  "meta": { "request_id": "..." }
}
```

---

## What you learned

- Writes always use the JSON:API envelope: `data.type` + `data.attributes`.
- POST creates, PATCH updates one row, DELETE removes one row.
- Views are read-only; PK-less views cannot be fetched by id.
- Unique constraints surface as **409** conflicts.

---

## Exercises

1. Create a new **supplier** (`suppliers` table has only `name`).
2. PATCH the supplier's name.
3. List `v_order_totals_by_day` and confirm you cannot POST to it.

---

## Next step

[Reading and querying](03-reading-and-querying.md) — filters, sort, and pagination.
