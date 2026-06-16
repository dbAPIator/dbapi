<?php
// Router for PHP built-in server (CI / local dev). Apache uses .htaccess instead.
chdir(__DIR__);
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$mgmtOpenApi = '#^/management-openapi(-multi|-single)?\.(yaml|json)$#';
if ($uri !== '/' && is_file(__DIR__ . $uri) && !preg_match($mgmtOpenApi, $uri)) {
    return false;
}
if (preg_match($mgmtOpenApi, $uri)) {
    // PHP built-in server sets SCRIPT_NAME to the static file path, which makes CI see an empty URI.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}
require __DIR__ . '/index.php';
