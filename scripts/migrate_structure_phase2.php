#!/usr/bin/env php
<?php
/**
 * Phase 2 migration: relations-only schema (drop redundant field.foreignKey).
 *
 * - Adds missing outbound relations from legacy field.foreignKey
 * - Removes field.foreignKey when an outbound relation covers the same edge
 *
 * Run Phase 1 first if configs still have referencedBy / fkfield / redundant name.
 *
 * Usage:
 *   php scripts/migrate_structure_phase2.php [configs_dir] [--dry-run]
 */

$configDir = null;
$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if ($configDir === null) {
        $configDir = $arg;
    }
}

$configDir = $configDir ?? (getenv('CONFIGS_DIR') ?: '/var/www/html/dbapi/dbconfigs');

if (!is_dir($configDir)) {
    fwrite(STDERR, "Configs dir not found: {$configDir}\n");
    exit(1);
}

require_once __DIR__ . '/../src/application/helpers/config_util_helper.php';

$updated = 0;
$skipped = 0;
$errors = 0;

foreach (scandir($configDir) ?: [] as $apiId) {
    if ($apiId === '.' || $apiId === '..') {
        continue;
    }
    $path = "{$configDir}/{$apiId}/structure.php";
    if (!is_file($path)) {
        continue;
    }

    $structure = @include $path;
    if (!is_array($structure)) {
        fwrite(STDERR, "Skip {$apiId}: invalid structure.php\n");
        $errors++;
        continue;
    }

    $beforeSize = filesize($path);
    $p1 = structure_phase1_slim($structure);
    $structure = $p1['structure'];
    $p2 = structure_phase2_normalize($structure);
    $structure = $p2['structure'];
    $changed = $p1['changed'] || $p2['changed'];

    if (!$changed) {
        echo "unchanged: {$apiId}\n";
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "would update: {$apiId} (" . number_format($beforeSize) . " bytes)\n";
        $updated++;
        continue;
    }

    $content = to_php_code($structure, true) . "\n";
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "Failed to write: {$path}\n");
        $errors++;
        continue;
    }
    @chmod($path, 0600);
    clearstatcache(true, $path);
    $afterSize = filesize($path);
    $pct = $beforeSize > 0 ? round(100 * (1 - $afterSize / $beforeSize), 1) : 0;
    echo "updated: {$apiId} {$beforeSize} -> {$afterSize} bytes (~{$pct}% smaller)\n";
    $updated++;
}

echo "\nDone. updated={$updated} unchanged={$skipped} errors={$errors}";
if ($dryRun) {
    echo " (dry-run)";
}
echo "\n";

exit($errors > 0 ? 1 : 0);
