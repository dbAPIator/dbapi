#!/usr/bin/env php
<?php
/**
 * One-off: generate openapi.json for every API that has structure.php.
 *
 * Usage: php scripts/generate_openapi_specs.php [configs_dir] [base_url]
 */
$src = dirname(__DIR__) . '/src';
define('BASEPATH', $src . '/system/');
define('APPPATH', $src . '/application/');
define('ENVIRONMENT', 'development');

require_once APPPATH . 'helpers/swagger_helper.php';

$configDir = $argv[1] ?? ($_ENV['CONFIGS_DIR'] ?? '/var/www/html/dbapi/dbconfigs');
$baseUrl = rtrim($argv[2] ?? 'http://localhost', '/');

if (!is_dir($configDir)) {
    fwrite(STDERR, "Configs dir not found: {$configDir}\n");
    exit(1);
}

$ok = 0;
$skip = 0;
foreach (scandir($configDir) ?: [] as $apiId) {
    if ($apiId === '.' || $apiId === '..') {
        continue;
    }
    $dir = "{$configDir}/{$apiId}";
    if (!is_dir($dir) || !is_file("{$dir}/structure.php")) {
        continue;
    }
    try {
        if (write_api_openapi_spec($apiId, $dir, $baseUrl)) {
            echo "OK {$apiId}\n";
            $ok++;
        } else {
            echo "SKIP {$apiId} (empty structure)\n";
            $skip++;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL {$apiId}: {$e->getMessage()}\n");
    }
}

echo "Done: {$ok} generated, {$skip} skipped\n";
