# `src/` — web application root

This directory is the **document root** for dbAPI (CodeIgniter 3). Project-wide documentation lives in the **[repository README](../README.md)**.

## Local URLs

| Install | Base URL |
|---------|----------|
| Apache (common) | `http://localhost/dbapi/src` |
| Docker Compose | `http://localhost:8888` |

## Configuration

| File | What to set |
|------|-------------|
| [`application/config/dbapiator.php`](application/config/dbapiator.php) | `configs_dir`, paging limits (or env vars) |
| [`application/config/config.php`](application/config/config.php) | `base_url` for your vhost |
| Env / Docker | `CONFIG_API_SECRET`, `CONFIGS_DIR`, `REDIS_*` |

API definitions are stored under **[`../dbconfigs/`](../dbconfigs/)** (not inside `src/`).

## Tests

From this directory:

```bash
composer install
./vendor/bin/phpunit
```

See [../docs/management_api_test_plan.md](../docs/management_api_test_plan.md) and [../docs/data_plane_test_plan.md](../docs/data_plane_test_plan.md).

## Docs

- [Management API](../docs/management_api.md)
- [Using the data API](../docs/using_the_api.md)
