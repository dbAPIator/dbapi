#!/bin/bash
# Load shared data-plane test schema into the Docker MySQL database (myapp).
# Re-run seed: rm -rf .docker_data/mysql && docker compose up -d

set -euo pipefail

BODY="/docker-entrypoint-initdb.d/dataplane-schema-body.sql"
if [[ ! -f "$BODY" ]]; then
  echo "Missing shared schema body: $BODY" >&2
  exit 1
fi

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" < "$BODY"
