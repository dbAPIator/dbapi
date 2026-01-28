<?php
$path = __DIR__ . '/../apis/test1/structure.php';

function fmt($bytes) {
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return sprintf('%.2f %s', $bytes, $units[$i]);
}

clearstatcache(true, $path);
$fileSize = filesize($path);

$before = memory_get_usage(true);
$beforeReal = memory_get_usage(false);

$schema = include $path;     // IMPORTANT: this forces full materialization

$after = memory_get_usage(true);
$afterReal = memory_get_usage(false);

echo "Schema file size:        " . fmt($fileSize) . PHP_EOL;
echo "Delta mem (real):        " . fmt($afterReal - $beforeReal) . PHP_EOL;
echo "Delta mem (allocated):   " . fmt($after - $before) . PHP_EOL;
echo "Peak mem (allocated):    " . fmt(memory_get_peak_usage(true)) . PHP_EOL;

// prevent optimizer from dropping it
echo "Tables: " . (is_array($schema) ? count($schema) : 0) . PHP_EOL;