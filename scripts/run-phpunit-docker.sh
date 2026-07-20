#!/usr/bin/env bash
# Run PHPUnit inside the dbapi Docker container (PHP 8.3).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SUITE="${1:-dbapi}"
COMPOSE=(docker compose -f "$ROOT/docker-compose.yml")

"${COMPOSE[@]}" exec -T mysql mariadb -uroot -prootpassword <<'SQL' >/dev/null 2>&1 || true
CREATE USER IF NOT EXISTS 'dbapi'@'%' IDENTIFIED BY 'dbapi';
CREATE DATABASE IF NOT EXISTS dbapi_dataplane;
GRANT ALL ON dbapi_dataplane.* TO 'dbapi'@'%';
FLUSH PRIVILEGES;
SQL

{
  echo 'DROP DATABASE IF EXISTS dbapi_dataplane;'
  echo 'CREATE DATABASE dbapi_dataplane;'
  echo 'USE dbapi_dataplane;'
  cat "$ROOT/src/tests/dataplane-schema-body.sql"
} | "${COMPOSE[@]}" exec -T mysql mariadb -uroot -prootpassword >/dev/null

"${COMPOSE[@]}" exec dbapi bash -lc "
set -euo pipefail
cd /app
composer install --no-interaction >/dev/null

cp tests/test.env.docker tests/test.env
cat > tests/connection.json <<EOF
{\"driver\":\"mysql\",\"host\":\"mysql\",\"port\":3306,\"database\":\"dbapi_dataplane\",\"username\":\"dbapi\",\"password\":\"dbapi\"}
EOF

mkdir -p /tmp/phpunit-configs
set -a && . tests/test.env.docker && set +a

fuser -k 8080/tcp 2>/dev/null || true
php -S 127.0.0.1:8080 -t /app/public /app/public/ci-router.php >/tmp/phpunit-server.log 2>&1 &
for _ in \$(seq 1 15); do
  if curl -sS -o /dev/null --connect-timeout 1 http://127.0.0.1:8080/mgmt/v1/apis 2>/dev/null; then
    break
  fi
  sleep 1
done

./vendor/bin/phpunit --testsuite ${SUITE}
"
