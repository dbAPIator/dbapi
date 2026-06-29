# Tutorial 4 — Relationships

Foreign keys in your database become **relationships** in JSON:API. This tutorial shows how to read, include, and filter across them.

**Prerequisites:** [Reading and querying](03-reading-and-querying.md)  
**Next:** [Writing data](05-writing-data.md)

---

## How relationship names work

Relationship names are part of the **public API contract**. They appear in URLs, the `include` parameter, and `relationships` objects.

| Direction | Default name | Example |
|-----------|--------------|---------|
| **Outbound** (many-to-one, FK on this table) | FK **column name** | `account_manager_id` on `customers` |
| **Inbound** (one-to-many, child table) | Child **table name** | `orders` under `customers` |

Check OpenAPI or a probe GET — do not guess pluralization.

The demo schema:

```text
customers ──account_manager_id──▶ users
customers ◀──orders── orders ──order_lines──▶ order_lines ──▶ products
```

---

## Relationship identifiers in responses

List customers and inspect `relationships` without loading full child rows:

```bash
curl -sS 'http://localhost:8888/v1/data/customers/1' | jq '.data.relationships'
```

You see lightweight `{ "id", "type" }` references. Full child attributes require `include` or a nested URL.

---

## Include related records

Load related data in one round trip with **`include`**:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers/1' \
  --data-urlencode 'include=orders,account_manager_id' | jq '{ customer: .data.attributes.name, included_types: [.included[].type] | unique }'
```

Included resources appear in the top-level **`included`** array. Match them to `relationships` by `type` + `id`.

Multiple relationships:

```bash
curl -sS -G 'http://localhost:8888/v1/data/orders/1' \
  --data-urlencode 'include=order_lines' | jq .
```

Nested includes are depth-limited (default max depth: 5).

---

## Read a relationship URL

Traverse a relationship as a sub-resource:

```bash
curl -sS 'http://localhost:8888/v1/data/customers/1/orders' | jq .
```

Same query parameters work here — filter, sort, and paginate the child collection:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers/1/orders' \
  --data-urlencode 'filter[orders]=status=placed' \
  --data-urlencode 'sort[orders]=-ordered_at'
```

Outbound FK as a single related record:

```bash
curl -sS 'http://localhost:8888/v1/data/customers/1/account_manager_id' | jq .
```

---

## Filter on relationships

Find parents that have matching children using **`filter[{parent}/{child}]`**:

Customers who have at least one **placed** order:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers' \
  --data-urlencode 'filter[customers/orders]=status=placed' | jq '.data[].attributes.name'
```

This compiles to an EXISTS-style SQL condition — far more efficient than loading all customers client-side.

---

## Pagination with includes

When using `include`, paginate the primary resource and optionally cap related rows:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers' \
  --data-urlencode 'include=orders' \
  --data-urlencode 'page[customers][offset]=0' \
  --data-urlencode 'page[customers][limit]=2' \
  --data-urlencode 'page[orders][limit]=3'
```

Per-resource sort when including:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers' \
  --data-urlencode 'include=orders' \
  --data-urlencode 'sort[customers]=name' \
  --data-urlencode 'sort[orders]=-ordered_at'
```

---

## Multi-hop example

Orders with line items and products (check OpenAPI for exact include paths):

```bash
curl -sS -G 'http://localhost:8888/v1/data/orders/1' \
  --data-urlencode 'include=order_lines' | jq .
```

For deeper graphs, follow relationship URLs step by step:

```bash
curl -sS 'http://localhost:8888/v1/data/customers/1/orders/1/order_lines' | jq .
```

This third-level path verifies the full chain (order `1` belongs to customer `1`) before returning line items. You can also use `include` for multi-hop reads in one request — see your OpenAPI spec for exact include paths.

For deeper graphs beyond three path segments (e.g. shipments → supplier), follow relationship URLs or expand `include` per your OpenAPI spec.

---

## Stable names across schema rebuilds

When you rebuild the schema after database changes, dbAPI **preserves existing relationship names** so clients do not break. To rename a relationship for clarity, use schema overrides (`patch.php`) — see [Tutorial 9](09-schema-customization.md).

---

## What you learned

- Outbound FKs use the column name; inbound children use the table name.
- `include` loads full related records into `included`.
- Relationship URLs (`/customers/1/orders`) support the same query params as top-level resources.
- Third-level URLs (`/customers/1/orders/1/order_lines`) traverse two relationship hops with path validation.
- `filter[parent/child]=...` filters parents by child conditions.

---

## Exercises

1. Fetch customer 1 with `include=orders` and list each order's `status`.
2. Find customers in `GB` who have any order with `status=draft`.
3. GET `/v1/data/orders/2/order_lines` and verify line quantities.

---

## Next step

[Writing data](05-writing-data.md) — nested creates, bulk operations, and upsert.
