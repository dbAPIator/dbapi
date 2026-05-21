#!/usr/bin/env bash
# Management API end-to-end test (stepped happy path + data API smoke)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "${SCRIPT_DIR}/tests/test.env" ]]; then
  # shellcheck source=/dev/null
  source "${SCRIPT_DIR}/tests/test.env"
fi

BASE_URL="${BASE_URL:-http://localhost/dbapi/src}"
MGMT_KEY="${MGMT_KEY:-myverysecuresecret}"
API_NAME="${API_NAME:-mgmt-e2e-$$}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-dbapi}"
DB_PASS="${DB_PASS:-dbapi}"
DB_NAME="${DB_NAME:-dbapi_test}"

CONN_FILE="${SCRIPT_DIR}/tests/connection.json"
if [[ -f "$CONN_FILE" ]]; then
  CONNECTION_JSON="$(cat "$CONN_FILE")"
else
  CONNECTION_JSON=$(cat <<EOF
{"driver":"mysql","host":"${DB_HOST}","port":${DB_PORT},"database":"${DB_NAME}","username":"${DB_USER}","password":"${DB_PASS}"}
EOF
)
fi

MGMT_HDR=(-H "Content-Type: application/json" -H "X-Management-Key: ${MGMT_KEY}")
API_CFG_HDR=()

fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "PASS: $*"; }

json_field() {
  python3 -c "import sys,json; d=json.load(sys.stdin); print(d$1)" 2>/dev/null
}

assert_code() {
  local expected="$1" actual="$2" label="$3"
  [[ "$actual" == "$expected" ]] || fail "${label}: expected HTTP ${expected}, got ${actual}"
}

request() {
  local method="$1" url="$2"
  shift 2
  curl -sS -w "\n%{http_code}" -X "$method" "${url}" "$@"
}

# ---------------------------------------------------------------------------
echo "== 1. POST /mgmt/v1/apis (draft)"
# ---------------------------------------------------------------------------
RESP=$(request POST "${BASE_URL}/mgmt/v1/apis" "${MGMT_HDR[@]}" \
  -d "{\"name\":\"${API_NAME}\",\"description\":\"e2e automated test\"}")
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
assert_code 201 "$CODE" "create draft"
echo "$BODY" | json_field "['api']['status']" | grep -q draft || fail "status should be draft"
SECRET=$(echo "$BODY" | json_field "['managementCredential']['secret']")
[[ -n "$SECRET" ]] || fail "missing managementCredential.secret"
API_CFG_HDR=(-H "X-Api-Config-Key: ${SECRET}")
pass "draft created"

# ---------------------------------------------------------------------------
echo "== 2. PUT connection + test"
# ---------------------------------------------------------------------------
RESP=$(request PUT "${BASE_URL}/mgmt/v1/apis/${API_NAME}/connection" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}" -d "${CONNECTION_JSON}")
CODE=$(echo "$RESP" | tail -1)
assert_code 200 "$CODE" "put connection"

RESP=$(request POST "${BASE_URL}/mgmt/v1/apis/${API_NAME}/connection:test" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
assert_code 200 "$CODE" "connection test"
if ! echo "$BODY" | json_field "['status']" | grep -q ok; then
  MSG=$(echo "$BODY" | json_field "['message']" 2>/dev/null || echo "$BODY")
  fail "connection:test status not ok — ${MSG}"
fi
pass "connection ok"

# ---------------------------------------------------------------------------
echo "== 3. Schema introspect + rebuild"
# ---------------------------------------------------------------------------
RESP=$(request POST "${BASE_URL}/mgmt/v1/apis/${API_NAME}/schema:introspect" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
assert_code 200 "$CODE" "schema introspect"

RESP=$(request POST "${BASE_URL}/mgmt/v1/apis/${API_NAME}/schema:rebuild" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
assert_code 200 "$CODE" "schema rebuild"
pass "schema ready"

# ---------------------------------------------------------------------------
echo "== 4. Policies (auth none + data path read)"
# ---------------------------------------------------------------------------
RESP=$(request PUT "${BASE_URL}/mgmt/v1/apis/${API_NAME}/policies/auth" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}" -d '{"mode":"none"}')
CODE=$(echo "$RESP" | tail -1)
assert_code 200 "$CODE" "put auth policy"

RESP=$(request PUT "${BASE_URL}/mgmt/v1/apis/${API_NAME}/policies/data-network" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}" -d '{"defaultAction":"deny","rules":[{"cidr":"0.0.0.0/0","action":"allow"}]}')
CODE=$(echo "$RESP" | tail -1)
assert_code 200 "$CODE" "put data-network policy"
pass "policies set"

# ---------------------------------------------------------------------------
echo "== 5. Validate + activate"
# ---------------------------------------------------------------------------
RESP=$(request POST "${BASE_URL}/mgmt/v1/apis/${API_NAME}:validate" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
assert_code 200 "$CODE" "validate"
echo "$BODY" | json_field "['ready']" | grep -q True || fail "validate ready=false: $BODY"
pass "validate ready"

RESP=$(request POST "${BASE_URL}/mgmt/v1/apis/${API_NAME}:activate" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
assert_code 200 "$CODE" "activate"
echo "$BODY" | json_field "['status']" | grep -q active || fail "status not active"
pass "activated"

# ---------------------------------------------------------------------------
echo "== 6. Data API GET customers"
# ---------------------------------------------------------------------------
RESP=$(request GET "${BASE_URL}/v1/apis/${API_NAME}/data/customers" "${MGMT_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
assert_code 200 "$CODE" "data api customers"
if ! echo "$BODY" | grep -q '"data"'; then
  fail "expected JSON:API data envelope — got: $(echo "$BODY" | head -c 400)"
fi
pass "data api serves"

# ---------------------------------------------------------------------------
echo "== 7. Deactivate blocks data API"
# ---------------------------------------------------------------------------
RESP=$(request POST "${BASE_URL}/mgmt/v1/apis/${API_NAME}:deactivate" \
  "${MGMT_HDR[@]}" "${API_CFG_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
assert_code 200 "$CODE" "deactivate"

RESP=$(request GET "${BASE_URL}/v1/apis/${API_NAME}/data/customers" "${MGMT_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
assert_code 409 "$CODE" "data api blocked when inactive"
pass "inactive blocked"

# ---------------------------------------------------------------------------
echo "== 8. Cleanup DELETE"
# ---------------------------------------------------------------------------
RESP=$(request DELETE "${BASE_URL}/mgmt/v1/apis/${API_NAME}?force=true" "${MGMT_HDR[@]}")
CODE=$(echo "$RESP" | tail -1)
assert_code 204 "$CODE" "delete api"
pass "deleted"

echo ""
echo "All Management API e2e checks passed."
