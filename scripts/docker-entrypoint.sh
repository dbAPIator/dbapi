#!/bin/bash
set -euo pipefail

if [[ -n "${LOCAL_UID:-}" && -n "${LOCAL_GID:-}" ]]; then
  FILE_OWNER="${LOCAL_UID}:${LOCAL_GID}"
else
  FILE_OWNER="www-data:www-data"
fi

maybe_chown() { [[ "$(id -u)" -eq 0 ]] && chown "$@"; }

wait_for_mysql() {
  local host="${DB_HOST:-mysql}"
  local port="${DB_PORT:-3306}"
  echo "Waiting for MySQL at ${host}:${port}..."
  for _ in $(seq 1 60); do
    if php -r "
      \$errno = 0;
      \$errstr = '';
      \$fp = @fsockopen('${host}', (int) '${port}', \$errno, \$errstr, 1);
      if (is_resource(\$fp)) { fclose(\$fp); exit(0); }
      exit(1);
    "; then
      echo "MySQL is ready."
      return 0
    fi
    sleep 1
  done
  echo "MySQL did not become ready within 60 seconds." >&2
  return 1
}

if [ "${DEPLOYMENT_MODE:-multi}" = "single" ]; then
  if [ ! -f /app/vendor/autoload.php ]; then
    echo "Installing PHP dependencies (vendor/ missing — dev volume mount)..."
    composer install --no-dev --no-interaction --optimize-autoloader
    maybe_chown -R "${FILE_OWNER}" /app/vendor
  fi
  if ! wait_for_mysql; then
    echo "Warning: continuing with draft provisioning (database not reachable)." >&2
  fi
  php /app/public/index.php cli/provision run || echo "Warning: auto-provision reported an error (instance may remain in draft)." >&2
  if [ -d "/app/apis/default" ]; then
    maybe_chown -R "${FILE_OWNER}" /app/apis/default
    chmod 644 /app/apis/default/openapi.json 2>/dev/null || true
  fi
fi

exec "$@"
