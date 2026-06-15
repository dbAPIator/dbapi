# Tutorial 3 — Reading and querying

Learn to narrow, order, and page through large result sets — server-side, not in your app.

**Prerequisites:** [JSON:API basics](02-json-api-basics.md)  
**Next:** [Relationships](04-relationships.md)

---

## Why server-side querying matters

dbAPI compiles filter expressions to SQL. For tables with thousands of rows, always filter and paginate on the server. Defaults and hard limits protect the instance (typical max page size: 1000).

---

## Filtering

Use the **`filter[{resource}]`** query parameter with a compact expression language.

### Operators

| Operator | Meaning | Example |
|----------|---------|---------|
| `=` | equal | `status=open` |
| `!=` | not equal | `is_active!=0` |
| `>` `>=` `<` `<=` | comparisons | `score>=40` |
| `=~` | starts with | `label=~alpha` |
| `~=` | ends with | `note~=.pdf` |
| `~=~` | contains | `note~=~comma` |
| `><` | one of (semicolon-separated) | `country><US;DE` |

Prefix `!` negates: `status!=closed`.

### Combining conditions

- **`,`** — AND (higher precedence)
- **`||`** — OR (lower precedence)
- **`()`** — grouping

Escape `,`, `||`, and `)` in values with `\`.

### Examples on `filter_cases`

Rows with score at least 40:

```bash
curl -sS -G 'http://localhost:8888/v1/data/filter_cases' \
  --data-urlencode 'filter[filter_cases]=score>=40' | jq '.data | length'
```

Label starts with `alpha` **and** status is open:

```bash
curl -sS -G 'http://localhost:8888/v1/data/filter_cases' \
  --data-urlencode 'filter[filter_cases]=label=~alpha,status=open' | jq '.data[].attributes.label'
```

Country is US **or** DE, and row is active:

```bash
curl -sS -G 'http://localhost:8888/v1/data/filter_cases' \
  --data-urlencode 'filter[filter_cases]=(country=US||country=DE),is_active=1'
```

Status in a list:

```bash
curl -sS -G 'http://localhost:8888/v1/data/filter_cases' \
  --data-urlencode 'filter[filter_cases]=status><open;pending'
```

### Filter on real business data

Active products only:

```bash
curl -sS -G 'http://localhost:8888/v1/data/products' \
  --data-urlencode 'filter[products]=is_active=1' | jq '.data[].attributes.name'
```

Customers in Germany:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers' \
  --data-urlencode 'filter[customers]=country_code=DE'
```

---

## Sorting

Use **`sort[{resource}]`** with a comma-separated field list. Prefix **`-`** for descending.

```bash
curl -sS -G 'http://localhost:8888/v1/data/filter_cases' \
  --data-urlencode 'sort[filter_cases]=score,-id' \
  --data-urlencode 'page[limit]=5' | jq '.data[].attributes | {label, score}'
```

Sort customers by name:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers' \
  --data-urlencode 'sort[customers]=name'
```

---

## Pagination

| Parameter | Meaning |
|-----------|---------|
| `page[offset]` | Skip N rows (default 0) |
| `page[limit]` | Page size |

```bash
# Page 2, 5 per page (offset 5)
curl -sS -G 'http://localhost:8888/v1/data/filter_cases' \
  --data-urlencode 'page[offset]=5' \
  --data-urlencode 'page[limit]=5' \
  --data-urlencode 'sort[filter_cases]=id' | jq '{ offset: .meta.offset, total: .meta.total, labels: [.data[].attributes.label] }'
```

Use **`meta.total`** to build UI page controls. Do not assume `data.length` equals total rows.

---

## Sparse fieldsets

Return only the columns you need with **`fields[{resource}]`**:

```bash
curl -sS -G 'http://localhost:8888/v1/data/customers' \
  --data-urlencode 'fields[customers]=name,email' | jq '.data[0]'
```

Smaller payloads mean faster responses and simpler client code.

---

## Putting it together

A realistic list query for an admin table:

```bash
curl -sS -G 'http://localhost:8888/v1/data/filter_cases' \
  --data-urlencode 'filter[filter_cases]=is_active=1,score>=10' \
  --data-urlencode 'sort[filter_cases]=-score,label' \
  --data-urlencode 'fields[filter_cases]=label,score,status' \
  --data-urlencode 'page[offset]=0' \
  --data-urlencode 'page[limit]=10'
```

---

## Guardrails

| Limit | Typical default |
|-------|-----------------|
| Max `page[limit]` | 1000 |
| Filter expression length | 4096 characters |
| Request timeout | 60 seconds |

If you hit **400** or **408**, simplify the filter or reduce the page size.

---

## What you learned

- Filters use `filter[{resource}]` with a rich operator vocabulary.
- Combine with `sort`, `page[offset]`, `page[limit]`, and `fields` for efficient reads.
- Always use `meta.total` for pagination UI.

---

## Exercises

1. Find all `filter_cases` where `note` contains `page` (hint: `~=~`).
2. Page through `filter_cases` sorted by `id` in batches of 3 until you have seen all 20 rows.
3. List customer names and emails only for `country_code=US`.

---

## Next step

[Relationships](04-relationships.md) — load related records and filter across tables.
