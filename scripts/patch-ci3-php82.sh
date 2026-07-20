#!/usr/bin/env bash
# Re-apply dbAPI CI 3.1.13 patches for PHP 8.2+ after replacing src/system/.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SYSTEM="$ROOT/src/system"

allow_dynamic_properties() {
  local file="$1"
  local class_line="$2"
  if grep -q 'AllowDynamicProperties' "$file"; then
    echo "skip (already patched): $file"
    return
  fi
  sed -i "/^${class_line}/i #[\\\\AllowDynamicProperties]" "$file"
  echo "patched: $file"
}

allow_dynamic_properties "$SYSTEM/core/Controller.php" 'class CI_Controller'
allow_dynamic_properties "$SYSTEM/core/Loader.php" 'class CI_Loader'
allow_dynamic_properties "$SYSTEM/core/URI.php" 'class CI_URI'
allow_dynamic_properties "$SYSTEM/core/Router.php" 'class CI_Router'
allow_dynamic_properties "$SYSTEM/core/Input.php" 'class CI_Input'
allow_dynamic_properties "$SYSTEM/database/DB_driver.php" 'abstract class CI_DB_driver'
allow_dynamic_properties "$SYSTEM/database/DB_query_builder.php" 'abstract class CI_DB_query_builder'

echo "Done. Also verify dbAPI result_array_num() patch in DB_result.php, mysqli_result.php, DB_driver.php."
