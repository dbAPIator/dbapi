#!/usr/bin/env bash
# Verify local and Docker schema entry points both load the shared body file.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BODY="$ROOT/src/tests/dataplane-schema-body.sql"
LOCAL_SQL="$ROOT/src/tests/dbapi_dataplane.sql"
DOCKER_INIT="$ROOT/docker/mysql-init/001-load-demo-schema.sh"

fail() {
  echo "Schema sync check failed: $*" >&2
  exit 1
}

[[ -f "$BODY" ]] || fail "missing $BODY"
[[ -f "$LOCAL_SQL" ]] || fail "missing $LOCAL_SQL"
[[ -f "$DOCKER_INIT" ]] || fail "missing $DOCKER_INIT"

grep -q 'dataplane-schema-body.sql' "$LOCAL_SQL" \
  || fail "$LOCAL_SQL must SOURCE dataplane-schema-body.sql"

grep -q 'dataplane-schema-body.sql' "$DOCKER_INIT" \
  || fail "$DOCKER_INIT must load dataplane-schema-body.sql"

if [[ -f "$ROOT/docker/mysql-init/001-demo-schema.sql" ]]; then
  fail "remove stale docker/mysql-init/001-demo-schema.sql (replaced by 001-load-demo-schema.sh)"
fi

# Seed section must contain all 20 filter_cases rows.
grep -q "alpha-low" "$BODY" || fail "body missing filter_cases seed (alpha-low)"
grep -q "negated" "$BODY" || fail "body missing filter_cases seed (negated)"

echo "Dataplane schema entry points reference shared body OK."
