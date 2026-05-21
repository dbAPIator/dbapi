# Admin API removed

The former **Admin API** (`/admin/apis/...`) and `admin-openapi.yaml` are **deprecated and removed**.

Use the **Management API** instead:

| Old | New |
|-----|-----|
| `GET /admin/apis` | `GET /mgmt/v1/apis` |
| `POST /admin/apis` | `POST /mgmt/v1/apis` |
| `X-Admin-API-Key` | `X-Management-Key` (instance) or `X-Api-Config-Key` (per API) |

- Documentation: [docs/management_api.md](../docs/management_api.md)
- OpenAPI: [management-openapi.yaml](management-openapi.yaml)
- Swagger UI: `swagger.html?url=management-openapi.yaml`

Requests to `/admin/apis` receive **410 Gone** with migration hints.
