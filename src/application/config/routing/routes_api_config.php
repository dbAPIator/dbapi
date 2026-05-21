<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$basePrefix = "^apis/(:any)";
$configApiPrefix = "^admin/apis/(:any)";

$route[$configApiPrefix."/connection"]["get"] = "admin/connection/get/$1";
$route[$configApiPrefix."/connection"]["put"] = "admin/connection/update/$1";
$route[$configApiPrefix."/connection:test"]["post"] = "admin/connection/test/$1";


$route[$configApiPrefix."/policies/config-network"]["get"] = "admin/policies/network/get/$1";
$route[$configApiPrefix."/policies/config-network"]["put"] = "admin/policies/network/update/$1";
$route[$configApiPrefix."/policies/data-network"]["get"] = "admin/policies/data/get/$1";
$route[$configApiPrefix."/policies/data-network"]["put"] = "admin/policies/data/update/$1";
$route[$configApiPrefix."/policies/auth"]["get"] = "admin/policies/auth/get/$1";
$route[$configApiPrefix."/policies/auth"]["put"] = "admin/policies/auth/update/$1";
$route[$configApiPrefix."/policies/auth:test"]["post"] = "admin/policies/auth/test/$1";

// Remaining admin routes (schema, hooks, CRUD) live in mgmt/v1 — see routes_mgmt.php.
// Include this file from routes.php if you still expose /admin/apis/... for connection/policies only.