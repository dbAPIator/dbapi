#!/usr/bin/env php
<?php
/**
 * One-time migration: add meta.php and status.php for existing API config directories.
 *
 * Usage: php scripts/migrate_management_api.php [configs_dir]
 */
$configDir = $argv[1] ?? (getenv('CONFIGS_DIR') ?: '/var/www/html/dbapi/dbconfigs');

if (!is_dir($configDir)) {
    fwrite(STDERR, "Configs dir not found: {$configDir}\n");
    exit(1);
}

require_once __DIR__ . '/../src/application/helpers/config_util_helper.php';

function guidv4_local(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$migrated = 0;
foreach (scandir($configDir) ?: [] as $apiId) {
    if ($apiId === '.' || $apiId === '..') {
        continue;
    }
    $dir = "{$configDir}/{$apiId}";
    if (!is_dir($dir)) {
        continue;
    }
    $hasStructure = is_file("{$dir}/structure.php");
    $status = 'draft';
    if ($hasStructure) {
        $status = 'active';
    }
    if (!is_file("{$dir}/status.php")) {
        file_put_contents("{$dir}/status.php", "<?php\nreturn " . var_export($status, true) . ";\n");
        echo "status.php -> {$status} for {$apiId}\n";
    }
    if (!is_file("{$dir}/meta.php")) {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $meta = [
            'id' => guidv4_local(),
            'name' => $apiId,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
        file_put_contents("{$dir}/meta.php", to_php_code($meta, true));
        echo "meta.php created for {$apiId}\n";
    }
    $migrated++;
}
echo "Done. Processed {$migrated} API directories.\n";
