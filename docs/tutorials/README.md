# dbAPI tutorials

Hands-on tutorials that walk from your first HTTP request through production-style integration. Each tutorial builds on the previous one.

**Prerequisites:** basic HTTP/REST familiarity and a working MySQL or MariaDB database (or the bundled dev stack below).

## Learning path

| # | Tutorial | What you will learn |
|---|----------|---------------------|
| 1 | [Getting started](01-getting-started.md) | Run dbAPI locally, discover endpoints, list your first resource |
| 2 | [JSON:API basics](02-json-api-basics.md) | Response shape, create / update / delete single records |
| 3 | [Reading and querying](03-reading-and-querying.md) | Filters, sort, pagination, sparse fieldsets |
| 4 | [Relationships](04-relationships.md) | `include`, relationship URLs, filter across relations |
| 5 | [Writing data](05-writing-data.md) | Nested creates, bulk ops, upsert, CSV export |
| 6 | [Authentication](06-authentication.md) | Login discovery, JWT, authenticated requests |
| 7 | [Provisioning an API](07-provisioning-an-api.md) | Management API lifecycle from draft to active |
| 8 | [Security policies](08-security-policies.md) | IP rules, path ACLs, scoped tables, field permissions |
| 9 | [Schema customization](09-schema-customization.md) | Overrides, rebuild, views, stored procedures |
| 10 | [Advanced integration](10-advanced-integration.md) | Multi-API hosting, webhooks, client patterns, troubleshooting |

## Recommended environment

The examples assume the **local Docker Compose stack** from the repository root:

```bash
docker compose up -d
```

| Item | Value |
|------|-------|
| Base URL | `http://localhost:8888` |
| Deployment mode | single (`/v1/data/...`) |
| Management API id | `default` |
| Instance secret | `myverysecuresecret` (see `docker-compose.yml`) |
| Demo database | `myapp` — seeded with customers, orders, products, and more |

Service discovery:

```bash
curl -sS http://localhost:8888/ | jq .
```

OpenAPI + Swagger UI:

```bash
open 'http://localhost:8888/swagger.html?url=v1/swagger'
```

## URL conventions in these tutorials

| Mode | Data plane prefix | Auth prefix |
|------|-------------------|-------------|
| **Single** (Docker dev) | `/v1/data/{resource}` | `/v1/auth/...` |
| **Multi-API** | `/v1/apis/{apiId}/data/{resource}` | `/v1/apis/{apiId}/auth/...` |

Tutorials 1–6 use **single-mode** paths. Tutorial 7 onward also show multi-API equivalents where it matters.

## Reference documentation

These tutorials teach by doing. For exhaustive reference material, see:

- [Using the API](../using_the_api.md) — data plane details
- [Management API](../management_api.md) — control plane reference
- [Docker deployment](../docker_deployment.md) — production containers
- [AI integration guide](../ai_dbapi_guide.md) — copy into consumer projects
