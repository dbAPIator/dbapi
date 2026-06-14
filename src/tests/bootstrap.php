<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Load tests/test.env into the process environment (KEY=VALUE, # comments skipped).
 */
$envFile = __DIR__ . '/test.env';
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/DataPlaneTestCase.php';
require_once __DIR__ . '/SingleModeDataPlaneTestCase.php';
require_once __DIR__ . '/DataPlaneTestsTrait.php';
require_once __DIR__ . '/AccessControlIntegrationTrait.php';
