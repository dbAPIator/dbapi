<?php
// Router for PHP built-in server (CI / local dev). Apache uses .htaccess instead.
chdir(__DIR__);
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}
require __DIR__ . '/index.php';
