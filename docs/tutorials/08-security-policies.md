# Tutorial 8 — Security policies

Layer IP rules, path-based authorization, table access modes, and field-level permissions to govern who can do what.

**Prerequisites:** [Provisioning an API](07-provisioning-an-api.md)  
**Next:** [Schema customization](09-schema-customization.md)

---

## Defense in depth

dbAPI evaluates several layers on each data-plane request:

```text
1. IP ACL (data_api_acls.php)
2. Path rules (pattern + HTTP method + optional JWT claim match)
3. Table access (public / private / scoped) — when path rules are empty
4. mandatoryFilter (server-side filter from JWT claims)
5. mandatoryAssign (force column values on INSERT)
6. Field-level ACL (read / insert / update / delete per column)
```

This tutorial configures the most common layers via Management API and schema overrides.

```bash
export BASE=http://localhost:8888
export MGMT_KEY=myverysecuresecret
export API=default
```

---

## Data network — IP ACL

Control which client IPs may reach the data plane:

```bash
curl -sS -X PUT "$BASE/mgmt/v1/apis/$API/policies/data-network" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "defaultAction": "deny",
    "rules": [
      { "cidr": "127.0.0.1/32", "action": "allow" },
      { "cidr": "172.16.0.0/12", "action": "allow" }
    ]
  }'
```

Shape: `defaultAction` + `rules[]` with `cidr` and `action` (`allow` | `deny`).

For local dev, allowing Docker bridge ranges (`172.16.0.0/12`) is common. In production, list explicit CIDRs — avoid `0.0.0.0/0` unless you understand the risk.

---

## Auth mode and default access

| `default_access_rule` | Meaning |
|-----------------------|---------|
| `public` | Anonymous GET allowed on tables marked `access: public` |
| `private` | JWT required unless a path rule explicitly allows the request |

With `mode: none`, the effective default is public. With `dbAuth`, it is usually private. See [Tutorial 6](06-authentication.md).

---

## Path rules

Path rules in `data_api_acls.php` match URL patterns and HTTP methods. They are evaluated **before** rejecting missing JWT.

Example — only admins may use mutating methods everywhere:

```json
{
  "path": [
    { "pattern": "/*", "methods": "GET,OPTIONS", "allow": true },
    { "pattern": "/*", "methods": "*", "allow": true, "when": { "role": "admin" } },
    { "pattern": "/*", "methods": "*", "allow": false }
  ]
}
```

Example — users may only access their own row (`{{userId}}` comes from JWT claims):

```json
{
  "pattern": "/users/{{userId}}/*",
  "methods": "*",
  "allow": true
}
```

Allow anonymous registration POST:

```json
{ "pattern": "/users", "methods": "POST", "allow": true }
```

Path rules are typically edited in `data_api_acls.php` or via future management endpoints — check [Management API](../management_api.md) for the current `PUT` shape on your version.

---

## Table access modes

In `structure.php` (via rebuild + `patch.php` overrides):

| `access` | Behavior |
|----------|----------|
| `public` | Anonymous read (GET) when `default_access_rule` is public |
| `private` | JWT required |
| `scoped` | JWT required; often combined with `scopePattern` |

Example override for a user-owned orders table:

```yaml
orders:
  access: private
  mandatoryFilter: "customer_id={{userId}}"
  mandatoryAssign:
    customer_id: "{{userId}}"
```

- **`mandatoryFilter`** — appended to GET, PATCH, DELETE (and bulk operations); overrides client filters on the same fields.
- **`mandatoryAssign`** — on POST, sets columns from JWT claims regardless of client input.

Configure overrides through Management API:

```bash
curl -sS "$BASE/mgmt/v1/apis/$API/schema/overrides" \
  -H "X-Management-Key: $MGMT_KEY" | jq .

# Merge-patch example (adjust entity keys to your schema)
curl -sS -X PATCH "$BASE/mgmt/v1/apis/$API/schema/overrides" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "orders": {
      "access": "private"
    }
  }'

curl -sS -X POST "$BASE/mgmt/v1/apis/$API/schema:rebuild" \
  -H "X-Management-Key: $MGMT_KEY" | jq .warnings
```

---

## Field-level ACL

Per-column permissions in `structure.php`:

```yaml
fields:
  email:
    read: true
    insert: false
    update: false
    delete: false
    search: true
    sort: true
```

Hide internal columns from API consumers while keeping them in the database. Apply via `patch.php` overrides and rebuild.

---

## filterBypassRoles

Let certain JWT roles skip `mandatoryFilter` / `mandatoryAssign` (e.g. admins):

```json
{
  "mode": "dbAuth",
  "filterBypassRoles": ["admin"],
  "dbAuth": { "...": "..." }
}
```

Set in `PUT .../policies/auth`.

---

## Config network — protect Management API

Separate IP policy for who may **configure** the API (`admin_config.php`):

```bash
curl -sS -X PUT "$BASE/mgmt/v1/apis/$API/policies/config-network" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "defaultAction": "deny",
    "rules": [{ "cidr": "10.0.0.0/8", "action": "allow" }]
  }'
```

---

## Troubleshooting auth denials

| Symptom | Check |
|---------|-------|
| **401** / **403** | JWT missing or expired; path rule denied |
| **409 API not active** | Run `:activate` |
| Empty list but no error | `mandatoryFilter` may exclude all rows |
| Works with management key, not app | Path rules vs table `access` |

Decode JWT claims in dev and verify they match `when` clauses and `{{placeholder}}` names in filters.

---

## What you learned

- IP ACLs gate network access; path rules gate URL + method + role.
- Table `access`, `mandatoryFilter`, and `mandatoryAssign` enforce row-level rules.
- Field ACLs hide or restrict individual columns.
- Policies compose — design from the outside in: network → auth → path → table → field.

---

## Exercises

1. Set `data-network` to deny all, then allow only `127.0.0.1` — confirm curl from host still works.
2. Enable `dbAuth` and add a path rule that denies POST without `role=admin`.
3. Inspect `structure.php` for `default` and find one field ACL example.

---

## Next step

[Schema customization](09-schema-customization.md) — rename resources, hide tables, procedures.
