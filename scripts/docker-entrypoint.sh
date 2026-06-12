#!/bin/bash
set -euo pipefail

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
  wait_for_mysql
  php /app/public/index.php cli/provision run
fi

exec "$@"
