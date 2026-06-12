# PHP 8 upgrade — runbook

Starting point for upgrading dbAPI from **PHP 7.4** to **PHP 8.x** while **keeping CodeIgniter 3** as the HTTP bootstrap.

This is intentionally **not** a CI4 migration or a custom micro-framework rewrite. The goal is a supported PHP runtime with minimal architectural change.

---

## Baseline (as of last review)

| Component | Current | Notes |
|-----------|---------|-------|
| PHP | **7.4** | `Dockerfile`, `configs/php*.conf`, `src/composer.json` platform |
| CodeIgniter | **3.1.0** | `src/system/` (`CI_VERSION` in `src/system/core/CodeIgniter.php`) |
| CI 3.1.13 source | Present at repo root | `CodeIgniter-3.1.13/system/` — use to replace `src/system/` |
| Application code | ~12k lines (excl. vendored OAuth2) | Controllers, traits, libraries, `third_party/dbAPI/` |
| CI usage | Thin glue | Routing, config loader, dynamic `load->database()` |
| Tests | HTTP integration via PHPUnit | See [management_api_test_plan.md](management_api_test_plan.md), [data_plane_test_plan.md](data_plane_test_plan.md) |

**README still says:** PHP 7.4+ — update after the upgrade lands.

---

## Recommended target

| Option | When to choose |
|--------|----------------|
| **PHP 8.2** | Safe default; widely deployed with patched CI3 |
| **PHP 8.3** | Preferred if you want a longer supported runtime in 2026+ |
| PHP 8.1 | Avoid for new work (already EOL) |
| PHP 8.4+ | Out of scope for CI3 without ongoing manual patches; revisit framework strategy separately |

**Recommendation:** **PHP 8.3** in Docker and local dev, with CI **3.1.13** plus PHP 8.2+ compatibility patches on core classes.

---

## Scope

### In scope

- Bump PHP in Docker and local/php-fpm configs
- Replace `src/system/` with CI 3.1.13
- Patch CI3 core for PHP 8.2+ (dynamic properties, return types if needed)
- Update `composer.json` platform and refresh lockfile
- Fix application-level breakages surfaced by tests
- Update README requirements line

### Out of scope (separate projects)

- CodeIgniter 4 migration
- Custom micro-framework / dropping CI
- Decoupling `third_party/dbAPI` from `CI_DB_*` (optional follow-up, not required for PHP 8)
- Database schema migrations (CI Migration library remains disabled)

---

## Pre-flight checklist

Before changing versions:

- [ ] Working tree clean or changes committed on a dedicated branch (e.g. `php8-upgrade`)
- [ ] Baseline green: run full PHPUnit suite on PHP 7.4
- [ ] Note current Docker image tag / deployment PHP version in production
- [ ] MySQL/MariaDB test DBs available (`dbapi_test`, `dbapi_dataplane` — see test plans)
- [ ] Copy `src/tests/test.env.example` → `src/tests/test.env` if not already present

### Baseline test commands

```bash
cd src
composer install
./vendor/bin/phpunit
```

Requires a running web server (Apache or Docker) at `BASE_URL` from `test.env`.

---

## Upgrade procedure

Work in order. Each phase should leave the app bootable before moving on.

### Phase 1 — CodeIgniter 3.1.13

1. **Back up** the current framework tree:
   ```bash
   cp -a src/system src/system.ci310.bak
   ```

2. **Replace** `src/system/` with CI 3.1.13:
   ```bash
   rm -rf src/system
   cp -a CodeIgniter-3.1.13/system src/system
   ```

3. **Verify** version constant:
   ```bash
   grep CI_VERSION src/system/core/CodeIgniter.php
   # expect: 3.1.13
   ```

4. **Smoke test on PHP 7.4 first** (if still available locally) — routing and mgmt API should work before touching PHP version.

> Do **not** copy `CodeIgniter-3.1.13/application/` over `src/application/`. dbAPI’s application tree is heavily customized; only `system/` changes.

---

### Phase 2 — PHP 8.2+ patches for CI3 core

Upstream CI 3.1.13 officially targets PHP 8.0/8.1. For **8.2+**, dynamic property deprecations (and occasionally return-type warnings) appear in core classes dbAPI touches:

- `CI_Controller` — all controllers extend this
- `CI_Loader` — `$this->load->database()`, helpers, config
- `CI_DB_driver` / `CI_DB_query_builder` — data plane, mgmt connection tests
- `CI_URI`, `CI_Router`, `CI_Input` — CLI provision, routing

**Minimal fix (preferred for this project):** add `#[\AllowDynamicProperties]` immediately above each affected `class` declaration in `src/system/`.

Start with these files (dbAPI exercise path):

```
src/system/core/Controller.php
src/system/core/Loader.php
src/system/core/URI.php
src/system/core/Router.php
src/system/core/Input.php
src/system/database/DB_driver.php
src/system/database/DB_query_builder.php
```

**Alternative:** track patches in a small script under `scripts/patch-ci3-php82.sh` so upgrades are reproducible, or adopt a maintained CI3 fork via Composer (evaluate carefully — adds vendor coupling).

**Community references:**

- [CodeIgniter issue #6278 — PHP 8.3](https://github.com/bcit-ci/CodeIgniter/issues/6278)
- [ib3ltd/CodeIgniter compare](https://github.com/bcit-ci/CodeIgniter/compare/master...ib3ltd:CodeIgniter:master) — consolidated PHP 8.2+ fixes

Apply patches **before** switching the runtime to PHP 8 to reduce noise while debugging.

---

### Phase 3 — Docker / PHP-FPM

Update every `7.4` reference. Files to touch:

| File | Change |
|------|--------|
| `Dockerfile` | `php7.4-*` packages → `php8.3-*` (or `8.2`) |
| `Dockerfile` | `/etc/php/7.4/...` paths → `/etc/php/8.3/...` |
| `configs/php.ini` | Copy from stock 8.3 fpm ini or diff against current |
| `configs/php-fpm.conf` | `pid`, `include` paths |
| `configs/www.conf` | pool user, socket paths if versioned |
| `src/www.conf` | Same if used outside Docker |

Suggested package set (mirror current extensions):

```
php8.3-cli php8.3-fpm php8.3-mysql php8.3-redis php8.3-mbstring
php8.3-xml php8.3-zip php8.3-yaml php8.3-curl php8.3-opcache php8.3-apcu
```

Rebuild and smoke test:

```bash
docker compose build --no-cache
docker compose up -d
curl -sS -o /dev/null -w '%{http_code}' http://localhost:8888/mgmt/v1/apis
# expect 401 without key (proves bootstrap works)
```

CLI entrypoint (`scripts/docker-entrypoint.sh`) calls `php /app/public/index.php cli/provision run` — verify in `DEPLOYMENT_MODE=single`.

---

### Phase 4 — Composer

Edit `src/composer.json`:

```json
"config": {
    "platform": {
        "php": "8.3.0"
    }
}
```

Then:

```bash
cd src
composer update --lock
composer install
```

**Dependencies (expected compatible, re-verify lock):**

| Package | Notes |
|---------|-------|
| `firebase/php-jwt` | Pinned `~6.10.0` on PHP 7.4; bump to `^7.0` during PHP 8 upgrade (removes CVE-2025-45769 advisory) |
| `guzzlehttp/guzzle` ^7.9 | PHP 8 OK |
| `predis/predis` ^2.3 | PHP 8 OK |
| `opis/json-schema` ^2.0 | PHP 8 OK |
| `phpunit/phpunit` (dev) | Pin to a version supporting PHP 8.3 if resolver picks an old release |

---

### Phase 5 — Application code audit

Most dbAPI logic is PHP 7.4-clean. Focus review on:

#### High priority

| Area | File(s) | Why |
|------|---------|-----|
| Dynamic properties | Controllers, traits | PHP 8.2 deprecates undeclared properties on `$this` |
| `get_instance()` | `third_party/dbAPI/API/Records.php`, `RateLimiter.php`, `helpers/swagger/SpecBuilder.php` | Still valid on CI3; watch for null if bootstrap order changes |
| Nullable / `@` suppression | `SingleModeProvisioner.php`, mgmt controllers, `DbapiInitTrait.php` | Stricter notices in 8.x — replace `@` with explicit checks where practical |
| JWT / auth | `controllers/Auth.php` | `@$auth["alg"]` patterns — use `??` |

#### Low priority (unlikely blockers)

- `each()`, `create_function`, `mysql_*` — not used in application code
- Vendored `OAuth2-Server` under `third_party/` — only exercise if OAuth paths are tested
- `json` extension — bundled in PHP 8; Docker can drop standalone `php-json` package

#### Optional hardening (not required for go-live)

- Declare missing properties on trait-using controllers
- Replace `get_instance()` with injected dependencies (helps a future CI removal, not PHP 8)

---

### Phase 6 — Configuration & docs

- [ ] `README.md` — change **PHP 7.4+** to **PHP 8.2+** (or 8.3+)
- [ ] `src/README.md` — same if mentioned
- [ ] `docker-compose.yml` — comment or env docs if PHP version matters to operators
- [ ] CI patches documented in this file or `scripts/patch-ci3-php82.sh` committed to repo

---

## Test plan (definition of done)

Run on **PHP 8.3** with CI 3.1.13 patched, against real HTTP (Apache or Docker).

### Automated

```bash
cd src
composer install
./vendor/bin/phpunit
```

Suite (from `phpunit.xml`):

- `TestManagementAPI.php`
- `TestDataPlaneAPI.php`
- `TestOpenApiSpecValidator.php`
- `TestSwaggerGenerator.php`
- `TestDeploymentHelper.php`
- `TestApiSafety.php`
- `TestFilterParser.php`

### Manual (follow existing docs)

1. [Management API test plan](management_api_test_plan.md) — full happy path + quick-create + legacy shim
2. [Data plane test plan](data_plane_test_plan.md) — CRUD, filters, auth, inactive API 409
3. Docker **single mode** — `DEPLOYMENT_MODE=single` auto-provision on container start
4. Swagger UI loads — `{base}/swagger.html?url=management-openapi.yaml`

### Runtime checks

```bash
php -v                    # 8.2.x or 8.3.x
php -m | grep -E 'mysqli|redis|mbstring|curl|json'
php -r "echo CI_VERSION;" # N/A — use grep on system/core/CodeIgniter.php inside container
```

Inside container:

```bash
docker compose exec <web-service> php -v
docker compose exec <web-service> grep CI_VERSION /app/system/core/CodeIgniter.php
```

---

## Known risks & mitigations

| Risk | Mitigation |
|------|------------|
| CI3 unmaintained on PHP 8.4+ | Stay on 8.2/8.3; plan framework exit separately if needed |
| Dynamic property noise from CI core | `AllowDynamicProperties` patch set committed in repo |
| Dynamic properties in app traits | Run test suite with `E_ALL`; declare properties or allow attribute on base controller |
| Production still on 7.4 | Upgrade staging first; align Docker and bare-metal php-fpm |
| `src/.phpunit.result.cache` | Regenerate on PHP 8; add to `.gitignore` if not already |

---

## Rollback

1. Restore `src/system/` from `src/system.ci310.bak` (or git checkout)
2. Revert Dockerfile / php-fpm configs to 7.4
3. Revert `composer.json` platform to `7.4.33` and `composer install`
4. Rebuild Docker image / restart php-fpm

Keep the backup branch until PHP 8 has baked in staging.

---

## Effort estimate

| Phase | Estimate |
|-------|----------|
| CI 3.1.13 swap + CI patches | 0.5–1 day |
| Docker / local PHP bump | 0.5 day |
| Composer + fix test failures | 0.5–1 day |
| Manual test plans + staging | 0.5–1 day |
| **Total** | **~2–4 days** |

Add buffer if production hosts differ from Docker (multiple php-fpm pools, Apache modules, etc.).

---

## Follow-ups (after PHP 8 is stable)

These improve maintainability but are **not** blockers:

1. **Vendor CI via Composer** — `"codeigniter/framework": "3.1.*"` instead of vendored `src/system/`, with post-install patch script
2. **Decouple `third_party/dbAPI` from `CI_DB_*`** — small DB adapter interface; eases a future CI removal
3. **Pin PHPUnit** to a specific major in `composer.json` for reproducible CI
4. **Enable PHPStan/Psalm** at level 0–1 on `application/` (excluding OAuth2 vendor tree)

---

## Quick reference — files that mention PHP 7.4

```
Dockerfile
configs/php.ini
configs/php-fpm.conf
configs/www.conf
src/www.conf
src/composer.json
README.md
```

Search before merge:

```bash
rg '7\.4|php7' --glob '!vendor/**' --glob '!CodeIgniter-3.1.13/**'
```

---

## Decision log

| Date | Decision |
|------|----------|
| 2026-06-12 | Upgrade PHP only; keep CI3. Target PHP 8.2/8.3. No CI4 / micro-framework in this effort. |
| | CI 3.1.13 source already at `CodeIgniter-3.1.13/`. |

Update this table when the upgrade ships or targets change.
