# dbAPI — schema-driven REST API for relational databases

**dbAPI is a database API service that turns any relational database into a [JSON:API](https://jsonapi.org/) REST backend.** Point it at a MySQL, MariaDB, PostgreSQL, or SQL Server database, and it introspects the schema, generates configuration, and serves a fully validated HTTP API — without hand-writing CRUD endpoints for every table.

Instead of building and maintaining a custom backend layer, you configure access rules once and let dbAPI handle request validation, SQL generation, relationship traversal, and response formatting.

## What it is

dbAPI sits between client applications and your database:

```text
Clients  →  dbAPI (auth, validation, SQL, JSON:API)  →  Database
                 ↑
          config generated from schema
```

- **Not middleware** in the pipeline sense — it is a standalone, stateless HTTP service.
- **Not a single-app backend** — it reflects whatever schema you connect, so one instance can expose many databases/APIs.
- **Closer to a data API gateway** — a schema-driven layer that makes your database reachable over REST with guardrails.

## How it works

1. **Connect** — Register a database through the [Management API](docs/management_api.md) (`/mgmt/v1/apis`) or the legacy `/apis` endpoint.
2. **Introspect** — dbAPI reads `information_schema` (tables, views, columns, keys, foreign keys) and builds a data model.
3. **Generate config** — The model is saved as PHP config files per API (`structure.php`, `connection.php`, `auth.php`, `security.php`, …) under the configs directory.
4. **Serve requests** — Incoming REST calls are routed to the `Dbapi` controller, which loads the config, validates the request against field and resource permissions, generates parameterized SQL, executes it, and returns a JSON:API document.
5. **Evolve** — When the database schema changes, regenerate the config; dbAPI can diff the old and new structure and preserve custom hooks and overrides.

### Data plane vs control plane

| Plane | Purpose | Examples |
|-------|---------|----------|
| **Data API** | CRUD and relationships over your database | `GET /apis/{apiId}/data/{resource}`, related records, auth tokens |
| **Control plane** | Create and manage API definitions | [Management API](docs/management_api.md) (`/mgmt/v1/apis`, connection, schema, policies, hooks, lifecycle) |

## Features

### API generation

- Automatic REST endpoints for tables and views based on database structure
- [JSON:API](https://jsonapi.org/) responses with support for relationships, filtering, sorting, and pagination
- Auto-generated OpenAPI specification and bundled Swagger UI

### Database support

- MySQL, MariaDB, PostgreSQL, SQL Server (Oracle listed as supported in driver layer)

### Security & access control

- IP-based ACLs for both data and configuration APIs
- Per-table and per-field read/write permissions
- JWT authentication with configurable login queries against the target database
- Path-based authorization rules for logged-in users

### Integration & extensibility

- Webhooks on write operations (insert, update, delete) via Redis Streams
- Hooks for customizing behavior per resource

### Operations

- Stateless design — scale horizontally behind a load balancer
- Docker image and `docker-compose` setup for local development
- One instance can host multiple APIs (e.g. dev, staging, prod) with separate endpoints and policies
- Schema regeneration when the underlying database changes

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- A web server configured to run PHP scripts (Apache, Nginx, etc.)
- PHP extensions: `mysqli`, `json`, `mbstring`

### Web server configuration

For Apache, ensure `mod_rewrite` is enabled.

For Nginx, ensure the following directives are present in the configuration file:

```
location ~ /instalation_path/ {
    try_files $uri $uri/ /instalation_path/index.php?$args;
}
```

### Install

```shell
mkdir dbapi
cd dbapi
git clone https://github.com/vsergione/dbapi .
chmod 750 dbconfigs
# ensure the web server user owns or can read/write this directory
```

### Configure

Edit `installation_path/application/config/dbAPI.php` and update the configuration to suit your needs. You should at least update:

- `$config['config_api_secret']` — secret for authenticating configuration API requests
- `$config['base_url']` — base URL of the API
- `$config['configs_dir']` — folder where API configurations are stored

## Management API (control plane)

Create and manage data APIs via the **Management API** at `/mgmt/v1/apis`.

**Full documentation:** [docs/management_api.md](docs/management_api.md)

| | |
|---|---|
| Base URL (local Apache) | `http://localhost/dbapi/src` |
| OpenAPI | `public/management-openapi.yaml` |
| Swagger UI | `swagger.html?url=management-openapi.yaml` |
| Instance auth | Header `X-Management-Key` (`config_api_secret`) |
| Per-API auth | Header `X-Api-Config-Key` (returned once on create) |
| Data API (after activate) | `/v1/apis/{apiId}/data/...` |
| Integration tests | `cd src && ./vendor/bin/phpunit` |

Typical flow: create draft → connection → schema introspect/rebuild → policies → validate → activate.

### Data plane tests

Full JSON:API data tests use database **`dbapi_dataplane`** ([`src/tests/dbapi_dataplane.sql`](tests/dbapi_dataplane.sql)) — commerce model plus filter fixtures, varied types, views, and constraint cases.

```bash
mysql -u root -p < src/tests/dbapi_dataplane.sql
cp src/tests/connection.dataplane.example.json src/tests/connection.json
cd src && composer install && ./vendor/bin/phpunit
```

See **[docs/data_plane_test_plan.md](../docs/data_plane_test_plan.md)**.

## Generate an API for an existing database

Creating your first API from a preexisting MySQL database is as easy as making a POST request to the legacy endpoint `http(s)://hostname/installation_path/apis` or Management API quick-create `POST /mgmt/v1/apis?provision=immediate`.

Example using curl:

```shell
curl --location 'http://localhost/dbapi/apis' \
--header 'Content-Type: application/json' \
--header 'x-api-key: myverysecuresecret' \
--data '{
    "name":"demo",
    "connection":{
        "hostname": "db_host",
        "username": "db_user",
        "password": "db_pass",
        "database": "db_name"
    }
}'

{"result":"f64e0cb3-f2ff-4c1d-a9a4-1ebf22272a96"}
```

Your API is ready to use — but not for production yet. Save the API secret returned in the response. See the [Management API guide](docs/management_api.md) for the current control-plane workflow.

## Using the API

The easiest way to explore an API is Swagger UI, bundled with the installation:

`http(s)://hostname/installation_path/swagger.html?url=apis/api_name/swagger`

See [Using the API](docs/using_the_api.md) for more details.

## Contributing

Contributions are welcome — please submit a pull request or open an issue.

## License

dbAPI is licensed under the MIT License. See [LICENSE](LICENSE.md) for details.
