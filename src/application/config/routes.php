<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'config/home';
$route['404_override'] = 'config/not_found';
$route['translate_uri_dashes'] = false;


// /api/apiName/entryPoint
$controller = "dbapi";

// ApiId part of the domain name
$basePath = "v2";


// ApiId part of the server path
//$stdOpsPath = "[0-9a-z]+/$basePath";
//$blkOpsPath =  "[0-9a-z]+/".$basePath."/b";



// API - just for testing direct method call
$route["^api/([\w\-\_]+)"] = "$controller/$1";

// APIID/EP
//$route["^api/([\w\-\_]+)/([\w\-\_]+)"] = "$controller/api/$1";


/**
/
- POST: bulk_create_records
- PATCH: bulk_update_records
- DELETE: bulk_delete_records

/resourceName**
- GET: get_multiple_records
- POST: create_single_record

/resourceName/$id**
- GET: get_single_record
- PATCH: update_single_records
- DELETE: update_single_records

/resourceName/$id/__relationships/$relation**
- GET: get_relationship
- POST: create_relationship
- PATCH: update_relationship
- DELETE: delete_relationship

/resourceName/$id/$relation**
- GET: get_related_records
 */

$route["^apis/([\w\-\.\_\%]+)/swagger"] = "config/swagger/$1";
$route["^dm/([\w\-\. \_\%]+)"] ="$controller/dm/$1";
$route["^test"] ="$controller/test";
$route["^test/(.*)"] ="$controller/test/$1";

$basePrefix = "^apis/([\w\-\.\_\%]+)";
$route[$basePrefix."/status"]["get"] = "config/status/$1";

$configApiPrefix = "$basePrefix/config";
// structure
$route[$configApiPrefix."/structure"]["get"] = "config/structure/$1";
$route[$configApiPrefix."/structure"]["put"] = "config/structure/$1";
$route[$configApiPrefix."/structure"]["patch"] = "config/structure/$1";
$route[$configApiPrefix."/structure/regen"]["post"] = "config/structure/$1";

// hooks
$route[$configApiPrefix."/hooks"]["get"] = "config/hooks/$1";
$route[$configApiPrefix."/hooks/([\w\-\.\_\%]+)"]["get"] = "config/hooks/$1/$2";
$route[$configApiPrefix."/hooks/([\w\-\.\_\%]+)"]["put"] = "config/hooks/$1/$2";

// config/acls
// authentication
$route[$configApiPrefix."/auth"]["get"] = "config/authentication/$1";
$route[$configApiPrefix."/auth"]["put"] = "config/authentication/$1";
$route[$configApiPrefix."/auth"]["patch"] = "config/authentication/$1";
// acls/IP
$route[$configApiPrefix."/acls/ip"]["get"] = "config/acls_ip/$1";
$route[$configApiPrefix."/acls/ip"]["put"] = "config/acls_ip/$1";

// acls/Paths
$route[$configApiPrefix."/acls/path"]["get"] = "config/acls_path/$1";
$route[$configApiPrefix."/acls/path"]["put"] = "config/acls_path/$1";

// configApi
$route[$configApiPrefix."/admin/acls"]["get"] = "config/admin_acls/$1";
$route[$configApiPrefix."/admin/acls"]["put"] = "config/admin_acls/$1";

$route[$configApiPrefix."/admin/secret/reset"]["post"] = "config/admin_secret_reset/$1";


$route[$configApiPrefix."/testt"] = "config/testt";



$route["^apis/?$"]["post"] = "config/create_api";
$route["^apis/([\w\-\.\_\%]+)$"]["delete"] = "config/delete_api/$1";
$route["^apis$"]["get"] = "config/list_apis";


$apiAuthUrlPrefix = "^apis/([\w\-\.\_\%]+)/auth";
$route["$apiAuthUrlPrefix/login"]["post"] = "auth/login/$1";
$route["$apiAuthUrlPrefix/verify"]["post"] = "auth/mfa_code_verify/$1";
$route["$apiAuthUrlPrefix/apiclienttoken/(.*)/(\d+)"]["get"] = "auth/genApiClientToken/$1/$2/$3";
//$route["$apiAuthUrlPrefix/login"]["post"] = "auth/login/$1";


// stored procedures
$route["^$basePath/([\w\-\.\_\%]+)/__call__/([\w\-\. \_\%]+)$"] ["post"] = "$controller/call_stored_procedure/$1/$2";



// first family: - bulk operations /
// #1
//$route["^$basePath/([\w\-\.\_\%]+)"]["post"] ="$controller/createMultipleRecords/$1";
// #2
$route["^$basePath/([\w\-\.\_\%]+)"]["patch"] ="$controller/bulk_update/$1";
// #3
$route["^$basePath/([\w\-\. \_\%]+)"]["delete"] ="$controller/bulk_delete/$1";

//$route["^[v2|data]/([\w\-\.\_\%]+)"] = "$controller/base/$1";

// second family: /resourceŃame
// #4 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)"]["get"] = "$controller/getMultipleRecords/$1";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_records/$1/$2";
// #5
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["post"] = "$controller/create_records/$1/$2";
// #5.1
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateWhere/$1/$2";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/bulk_update/$1/$2";

// #5.2
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/bulk_delete/$1/$2";

// third family: /resourceName/id
// #6 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getSingleRecord/$1/$2";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_records/$1/$2/$3";
// #7
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateSingleRecord/$1/$2/$3";
// #8
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteSingleRecord/$1/$2/$3";


// third family: /resourceName/id/_relationships/relation
// #9
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["get"] = "$controller/get_relationship/$1/$2/$3/$4";
// #10
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["post"] = "$controller/create_relationship/$1/$2/$3/$4";
// #11
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_relationship/$1/$2/$3/$4";
// #12
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["delete"] = "$controller/delete_relationship/$1/$2/$3/$4";


// fourth family: /resourceName/id/relation
// #13
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_related/$1/$2/$3/$4";
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["post"] = "$controller/create_related/$1/$2/$3/$4";
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_related/$1/$2/$3/$4";


// fifth family: /resourceName/id/relation/id
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_related/$1/$2/$3/$4/$5";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_related/$1/$2/$3/$4/$5";
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteRelated/$1/$2/$3/$4/$5";


// fifth family: /resourceName/id/relation/id/2nd_relation
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_related_2nd/$1/$2/$3/$4/$5";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/get_related_2nd/$1/$2/$3/$4/$5";
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/get_related_2nd/$1/$2/$3/$4/$5";

$route["^$basePath/.*"]["options"] = "$controller/options";





/*
 * new format
 */



$apiDataUrlPrefix = "^apis/([\w\-\.\_\%]+)/data";
// first family: - bulk operations /
// #1
$route[$apiDataUrlPrefix]["post"] ="$controller/createMultipleRecords/$1";
// #2
$route[$apiDataUrlPrefix]["patch"] ="$controller/bulk_update/$1";
// #3
//$route["^apis/([\w\-\. \_\%]+)"]["delete"] ="$controller/bulk_delete/$1";

//$route["^[v2|data]/([\w\-\.\_\%]+)"] = "$controller/base/$1";

// second family: /resourceŃame
// #4 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)"]["get"] = "$controller/getMultipleRecords/$1";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["get"] = "$controller/get_records/$1/$2";
// #5
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["post"] = "$controller/create_records/$1/$2";
// #5.1
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_where/$1/$2";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["patch"] = "$controller/bulk_update/$1/$2";

// #5.2
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["delete"] = "$controller/bulk_delete/$1/$2";

// third family: /resourceName/id
// #6 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getSingleRecord/$1/$2";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_records/$1/$2/$3";
// #7
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateSingleRecord/$1/$2/$3";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_single_record/$1/$2/$3";
// #8
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteSingleRecord/$1/$2/$3";


// third family: /resourceName/id/_relationships/relation
// #9
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["get"] = "$controller/get_relationship/$1/$2/$3/$4";
// #10
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["post"] = "$controller/create_relationship/$1/$2/$3/$4";
// #11
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_relationship/$1/$2/$3/$4";
// #12
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/_relationships/([\w\-\. \_\%]+)"]["delete"] = "$controller/delete_relationship/$1/$2/$3/$4";


// fourth family: /resourceName/id/relation
// #13
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_related/$1/$2/$3/$4";
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["post"] = "$controller/create_related/$1/$2/$3/$4";
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_related/$1/$2/$3/$4";


// fifth family: /resourceName/id/relation/id
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/get_related/$1/$2/$3/$4/$5";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/update_related/$1/$2/$3/$4/$5";
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteRelated/$1/$2/$3/$4/$5";

$route["^apis/.*"]["options"] = "$controller/options";







