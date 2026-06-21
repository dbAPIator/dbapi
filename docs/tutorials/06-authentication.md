# Tutorial 6 — Authentication

Configure database-backed JWT auth and call protected endpoints from your application.

**Prerequisites:** [Writing data](05-writing-data.md)  
**Next:** [Provisioning an API](07-provisioning-an-api.md)

---

## Auth modes

| `policies/auth.mode` | Behavior |
|---------------------|----------|
| `none` | No JWT required; guest read may be allowed (depends on network and table ACLs) |
| `dbAuth` | Login via SQL → JWT Bearer token |

The local Docker stack starts with **`none`** for easy exploration. This tutorial switches to **`dbAuth`** using the demo `app_users` table.

**Management key** (server-side only):

```bash
export MGMT_KEY=myverysecuresecret
export BASE=http://localhost:8888
```

---

## Step 1 — Enable dbAuth

```bash
curl -sS -X PUT "$BASE/mgmt/v1/apis/default/policies/auth" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{
    "mode": "dbAuth",
    "dbAuth": {
      "validity": 3600,
      "loginMethods": {
        "password": {
          "sql": "SELECT username AS unm, role FROM app_users WHERE username='\''[[login]]'\'' AND password='\''[[password]]'\''"
        },
        "pin": {
          "sql": "SELECT username AS unm, role FROM app_users WHERE pin='\''[[pin]]'\''",
          "validity": 900
        }
      }
    }
  }'
```

Placeholders `[[login]]`, `[[password]]`, `[[pin]]` map to form fields on login. Columns in the SELECT become JWT claims (`unm`, `role`, etc.).

The API stays active — no re-activation needed for policy changes, but give the config a moment to reload if you see stale behavior.

---

## Step 2 — Discover login methods

Clients should **never hard-code** login fields. Fetch them at runtime:

```bash
curl -sS "$BASE/v1/auth/login" | jq .
```

Expected response:

```json
{
  "loginMethods": [
    { "name": "password", "fields": ["login", "password"], "expiresIn": 3600 },
    { "name": "pin", "fields": ["pin"], "expiresIn": 900 }
  ]
}
```

Build your login form from `fields`. When `mode` is `none`, `loginMethods` is empty.

---

## Step 3 — Log in

Login is always **`POST /v1/auth/login/{method}`** with **`application/x-www-form-urlencoded`**:

```bash
TOKEN=$(curl -sS -X POST "$BASE/v1/auth/login/password" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'login=testuser&password=testpass' | jq -r .access_token)

echo "Token: ${TOKEN:0:20}..."
```

PIN login:

```bash
curl -sS -X POST "$BASE/v1/auth/login/pin" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'pin=1234' | jq .
```

| Situation | HTTP code |
|-----------|-----------|
| Missing `{loginMethod}` in URL | **404** |
| Unknown method | **404** |
| Missing/empty required field | **400** |
| Invalid credentials | **404** |

Token response:

```json
{
  "access_token": "<jwt>",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

---

## Step 4 — Authenticated requests

```bash
curl -sS "$BASE/v1/data/customers?page[limit]=1" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

Refresh the token before `expires_in` seconds elapse. Store tokens securely (memory or httpOnly cookies on your backend — not `localStorage` if XSS is a concern).

---

## Step 5 — Restore open access (optional)

When finished experimenting:

```bash
curl -sS -X PUT "$BASE/mgmt/v1/apis/default/policies/auth" \
  -H 'Content-Type: application/json' \
  -H "X-Management-Key: $MGMT_KEY" \
  -d '{"mode":"none"}'
```

---

## Multi-API URL difference

| Mode | Discover | Login |
|------|----------|-------|
| Single | `GET /v1/auth/login` | `POST /v1/auth/login/{method}` |
| Multi | `GET /v1/apis/{apiId}/auth/login` | `POST /v1/apis/{apiId}/auth/login/{method}` |

---

## Client pattern (JavaScript)

```javascript
const base = 'http://localhost:8888';

async function login(method, fields) {
  const body = new URLSearchParams(fields);
  const res = await fetch(`${base}/v1/auth/login/${method}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  });
  if (!res.ok) throw await res.json();
  return res.json();
}

async function apiGet(resource, token, query = {}) {
  const url = new URL(`${base}/v1/data/${resource}`);
  Object.entries(query).forEach(([k, v]) => url.searchParams.set(k, v));
  const res = await fetch(url, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  if (!res.ok) throw await res.json();
  return res.json();
}
```

---

## What you learned

- Discover login methods with `GET .../auth/login` before building UI.
- POST credentials to `.../auth/login/{method}` as form-urlencoded.
- Attach `Authorization: Bearer <token>` to data plane requests.
- Validate a token with `GET .../auth/session` (same Bearer header; **204** if valid, **401** with empty body if not).
- Configure auth via Management API `PUT .../policies/auth`.

Path rules, scoped tables, and `mandatoryFilter` build on JWT claims — see [Tutorial 8](08-security-policies.md).

---

## Exercises

1. Log in as `admin` / `adminpass` and inspect the token payload (decode JWT at [jwt.io](https://jwt.io) in dev only).
2. Call login with a wrong password and note the status code.
3. Log in with PIN `9999` and compare `expires_in` to the password method.

---

## Next step

[Provisioning an API](07-provisioning-an-api.md) — create and activate an API from scratch.
