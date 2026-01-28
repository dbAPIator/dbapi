<?php
const BASEPATH = "application/config/";
include "application/config/routes.php";
include "application/config/routes_config.php";

$routes = [];

foreach($route as $key => $value) {
    $routes[$key] = $value;
}

print_r(array_keys($routes));