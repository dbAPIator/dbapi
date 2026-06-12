#!/usr/bin/env bash
# Load the data-plane test database locally (dbapi_dataplane).
# Run from anywhere; does not rely on mysql SOURCE paths.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"

MYSQL_ARGS=(-h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER")
if [[ -n "${MYSQL_PASSWORD:-}" ]]; then
  MYSQL_ARGS+=(-p"$MYSQL_PASSWORD")
elif [[ -n "${MYSQL_PWD:-}" ]]; then
  export MYSQL_PWD
fi

{
  cat <<'EOF'
DROP DATABASE IF EXISTS `dbapi_dataplane`;
CREATE DATABASE `dbapi_dataplane`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `dbapi_dataplane`;
EOF
  cat "$ROOT/src/tests/dataplane-schema-body.sql"
} | mysql "${MYSQL_ARGS[@]}"

echo "Loaded dbapi_dataplane from src/tests/dataplane-schema-body.sql"
