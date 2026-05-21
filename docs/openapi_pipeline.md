# OpenAPI pipeline (data APIs)

How dbAPI builds, validates, stores, and serves OpenAPI specs for each data API (`apiId`).

---

## Overview

```text
structure.php (+ patch.php)
        │
        ▼
  generate_swagger()     ← helpers: swagger/SpecBuilder, Paths, Schemas
        │
        ▼
  validate_data_api_openapi_spec()   ← OpenApiSpecValidator
        │
        ▼
  atomic write → {configs_dir}/{apiId}/openapi.json
        │
        ▼
  GET /apis/{apiId}/swagger  (reads cache; no generation per request)
```

Management API **lifecycle** `:validate` checks that `openapi.json` exists, parses as JSON, passes structural validation, and is not older than `structure.php` (warning only).

---

## When the spec is regenerated

| Action | Regenerates openapi.json? |
|--------|---------------------------|
| `POST /mgmt/v1/apis/{id}/schema:rebuild` | Yes (via `saveStructure`) |
| `POST /mgmt/v1/apis/{id}/schema:regenerate-openapi` | Yes (structure on disk only) |
| `PUT/PATCH .../schema/overrides` | No (run regenerate-openapi or rebuild after patch) |
| Hooks update (`saveStructure` in mgmt/Hooks) | Yes |

On failure, `meta.php` stores `schema.openapiError` and `:validate` fails until fixed.

---

## Management API endpoints

### `GET /mgmt/v1/apis/{apiId}/schema/openapi`

Returns cache **metadata** (not the full document):

- `exists`, `sizeBytes`, `generatedAt`, `error`, `stale`
- `validation` — result of `OpenApiSpecValidator`
- `swaggerUrl` — public URL for the JSON spec

### `POST /mgmt/v1/apis/{apiId}/schema:regenerate-openapi`

Rebuilds `openapi.json` from current `structure.php` without re-introspecting the database. Response includes `validation`.

---

## Structural validation rules

`OpenApiSpecValidator` checks:

- `openapi` version 3.x
- `info.title` present
- `paths` non-empty with at least one HTTP operation
- Warnings for missing `servers`, empty `components.schemas`, etc.

Validation runs **before** writing the file. Invalid generated specs throw and do not overwrite a good cache.

---

## Atomic write

`write_api_openapi_spec()` writes to `{path}.tmp.{pid}` then `rename()` to `openapi.json` so readers never see a half-written file.

---

## Code locations

| Piece | Path |
|-------|------|
| Generator | `application/helpers/swagger/SpecBuilder.php` |
| Path operations | `application/helpers/swagger/Paths.php` |
| Schemas | `application/helpers/swagger/Schemas.php` |
| Loader | `application/helpers/swagger_helper.php` |
| Validator | `application/libraries/OpenApiSpecValidator.php` |
| Lifecycle check | `application/libraries/MgmtLifecycle.php` |
| Serve spec | `application/controllers/Swagger.php` |

---

## CLI regeneration

For all APIs under `configs_dir`:

```bash
php scripts/generate_openapi_specs.php
```

Requires `CONFIGS_DIR` / app bootstrap as documented in the script.
