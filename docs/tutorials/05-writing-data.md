# Tutorial 5 — Writing data

Beyond single-row CRUD: nested creates, bulk operations, duplicate handling, and export.

**Prerequisites:** [Relationships](04-relationships.md)  
**Next:** [Authentication](06-authentication.md)

---

## Nested create

Create a parent and children in one POST using **`relationships`**:

```bash
curl -sS -X POST http://localhost:8888/v1/data/orders \
  -H 'Content-Type: application/json' \
  -d '{
    "data": {
      "type": "orders",
      "attributes": {
        "customer_id": 1,
        "status": "draft"
      },
      "relationships": {
        "order_lines": {
          "data": [
            {
              "type": "order_lines",
              "attributes": {
                "product_id": 1,
                "quantity": 2,
                "unit_price": 9.99
              }
            },
            {
              "type": "order_lines",
              "attributes": {
                "product_id": 2,
                "quantity": 1,
                "unit_price": 19.50
              }
            }
          ]
        }
      }
    }
  }' | jq .
```

Inbound relationships (one-to-many) use an **array** in `relationships.{name}.data`. Each child needs `type` and `attributes`.

Verify with include:

```bash
curl -sS -G 'http://localhost:8888/v1/data/orders/ORDER_ID' \
  --data-urlencode 'include=order_lines'
```

---

## Bulk insert

POST an array in **`data`** (default limit: 100 records per request):

```bash
curl -sS -X POST http://localhost:8888/v1/data/notes \
  -H 'Content-Type: application/json' \
  -d '{
    "data": [
      {
        "type": "notes",
        "attributes": { "customer_id": 1, "body": "Bulk note A", "priority": 0 }
      },
      {
        "type": "notes",
        "attributes": { "customer_id": 2, "body": "Bulk note B", "priority": 1 }
      }
    ]
  }' | jq .
```

---

## Bulk update

PATCH the collection URL with an array of objects, each with `type`, `id`, and `attributes` (default limit: 50):

```bash
curl -sS -X PATCH http://localhost:8888/v1/data/notes \
  -H 'Content-Type: application/json' \
  -d '{
    "data": [
      { "type": "notes", "id": "1", "attributes": { "priority": 5 } },
      { "type": "notes", "id": "2", "attributes": { "priority": 5 } }
    ]
  }' | jq .
```

Collection-level PATCH without ids or a filter → **400**.

---

## Bulk delete

DELETE the collection with a **`filter`** — never delete an entire table by accident without one:

```bash
curl -sS -X DELETE -G 'http://localhost:8888/v1/data/notes' \
  --data-urlencode 'filter[notes]=body~=~tutorial 2' -w '\nHTTP %{http_code}\n'
```

Delete by explicit id list using the one-of operator:

```bash
curl -sS -X DELETE -G 'http://localhost:8888/v1/data/notes' \
  --data-urlencode 'filter[notes]=id><10;11;12'
```

---

## Upsert with `onduplicate`

Control behavior when a unique key conflicts on POST:

| Value | Behavior |
|-------|----------|
| `error` | Default — return conflict |
| `ignore` | Skip duplicate rows |
| `update` | Update listed fields on conflict |

Insert or ignore duplicate SKU:

```bash
curl -sS -X POST 'http://localhost:8888/v1/data/products?onduplicate=ignore' \
  -H 'Content-Type: application/json' \
  -d '{
    "data": {
      "type": "products",
      "attributes": {
        "sku": "SKU-001",
        "name": "Should be ignored",
        "price": 0.01,
        "is_active": 1
      }
    }
  }'
```

Insert or update name and price:

```bash
curl -sS -X POST 'http://localhost:8888/v1/data/products?onduplicate=update&update=name,price' \
  -H 'Content-Type: application/json' \
  -d '{
    "data": {
      "type": "products",
      "attributes": {
        "sku": "SKU-NEW",
        "name": "New Widget",
        "price": 14.99,
        "is_active": 1
      }
    }
  }'
```

---

## CSV export

Add **`format=csv`** to a GET request:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers' \
  --data-urlencode 'fields[customers]=name,email,country_code' \
  --data-urlencode 'format=csv' -o customers.csv

head customers.csv
```

Useful for spreadsheets, BI tools, and one-off reports. Filters and field selection apply to the export.

Outbound (1:1) **`include`** relations are flattened into extra columns prefixed with the relationship name (`customer_id.name`). Inbound (1:n) includes are ignored for CSV/XLS because they cannot map to a single row. Limit related columns with path-keyed sparse fieldsets (`fields[{parent}/{relation}]`):

```bash
curl -sS -G 'http://localhost:8888/v1/data/orders' \
  --data-urlencode 'include=customer_id' \
  --data-urlencode 'fields[orders]=id,status,total,customer_id' \
  --data-urlencode 'fields[orders/customer_id]=name,email' \
  --data-urlencode 'format=csv' -o orders.csv
```

---

## Create via relationship URL

You can POST to a nested relationship endpoint:

```bash
curl -sS -X POST http://localhost:8888/v1/data/customers/1/notes \
  -H 'Content-Type: application/json' \
  -d '{
    "data": {
      "type": "notes",
      "attributes": {
        "body": "Note via relationship URL",
        "priority": 0
      }
    }
  }' | jq .
```

`customer_id` is inferred from the parent id in the URL.

---

## What you learned

- Nested creates attach children in `relationships.{child_table}.data`.
- Bulk insert, update, and filter-based delete have per-request limits.
- `onduplicate` enables idempotent imports and upsert flows.
- `format=csv` exports filtered, field-selected data; outbound `include` relations become extra columns.

---

## Exercises

1. Create an order with one line item for product 1 (qty 3).
2. Bulk-update two notes to `priority=0`.
3. Export active products to CSV.

---

## Next step

[Authentication](06-authentication.md) — JWT login and protected requests.
