<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$basePrefix = "^apis/(:any)";
$configApiPrefix = "$basePrefix/config";

/**
 * Configuration API endpoints
 * - apis/apiName/config/status
 * - apis/apiName/config/structure
 *      - GET   get structure
 *      - POST  regenerate structure
 *      - PATCH
 *      - PUT
 * - apis/apiName/config/hooks
 *      - GET   get hooks
 *      - PUT   update hook
 * - apis/apiName/config/auth
 *      - GET   get authentication
 *      - PUT   update authentication
 * - apis/apiName/config/acls/ip
 *      - GET   get IP ACLs
 *      - PUT   update IP ACLs
 * - apis/apiName/config/acls/path
 *      - GET   get path ACLs
 *      - PUT   update path ACLs
 * - apis/apiName/config/admin/acls
 *      - GET   get admin ACLs
 *      - PUT   update admin ACLs
 * - apis/apiName/config/admin/secret/reset
 *      - POST  reset admin secret
 */


// api status
// apis/apiName/config/status
$route[$basePrefix."/status"]["get"] = "config/status/$1";

// ADMIN endpoints
// list
$route["^apis$"]["get"] = "config/list_apis";
// create
$route["^apis/?$"]["post"] = "config/create_api";
// delete
$route["^apis/([\w\-\.\_\%]+)$"]["delete"] = "config/delete_api/$1";


// STRUCTURE configuration endpoints
// apis/apiName/config/structure - GET API structure
$route[$configApiPrefix."/structure"]["get"] = "config/structure/$1";
// apis/apiName/config/structure - replace API structure with the one provided in the request
$route[$configApiPrefix."/structure"]["put"] = "config/structure/$1";
// apis/apiName/config/structure - patch API structure with the one provided in the request
$route[$configApiPrefix."/structure"]["patch"] = "config/structure/$1";
// apis/apiName/config/structure - regenerate API structure
$route[$configApiPrefix."/structure"]["post"] = "config/structure/$1";

// HOOKS configuration endpoints
// apis/apiName/config/hooks - GET hooks
$route[$configApiPrefix."/hooks"]["get"] = "config/get_hooks/$1";
// apis/apiName/config/hooks/resourceName - GET hooks by resource name
$route[$configApiPrefix."/hooks/([\w\-\.\_\%]+)"]["get"] = "config/get_hooks/$1/$2";
// apis/apiName/config/hooks/resourceName - Attach hooks to resource by resource name
$route[$configApiPrefix."/hooks/([\w\-\.\_\%]+)"]["put"] = "config/update_hooks/$1/$2";

// AUTHENTICATION configuration endpoints
$route[$configApiPrefix."/auth"]["get"] = "config/authentication/$1";
$route[$configApiPrefix."/auth"]["put"] = "config/authentication/$1";
$route[$configApiPrefix."/auth"]["patch"] = "config/authentication/$1";

// DATA API IP ACLS configuration endpoints
$route[$configApiPrefix."/acls/ip"]["get"] = "config/acls_ip/$1";
$route[$configApiPrefix."/acls/ip"]["put"] = "config/acls_ip/$1";

// DATA API PATH ACLS configuration endpoints
$route[$configApiPrefix."/acls/path"]["get"] = "config/acls_path/$1";
$route[$configApiPrefix."/acls/path"]["put"] = "config/acls_path/$1";

// ADMIN  ACLS configuration endpoints
$route[$configApiPrefix."/admin/acls"]["get"] = "config/admin_acls/$1";
$route[$configApiPrefix."/admin/acls"]["put"] = "config/admin_acls/$1";

// reset config API secret
$route[$configApiPrefix."/admin/secret/reset"]["post"] = "config/admin_secret_reset/$1";

