<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
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

$route["^swagger/([\w\-\. \_\%]+)"] = "$controller/swagger/$1";
$route["^dm/([\w\-\. \_\%]+)"] ="$controller/dm/$1";
$route["^test"] ="$controller/test";
$route["^test/(.*)"] ="$controller/test/$1";



$route["^apis/([\w\-\.\_\%]+)/config"]["get"] = "config/get/$1";
$route["^apis/([\w\-\.\_\%]+)/config/swagger"]["get"] = "config/swagger/$1";

$route["^apis/([\w\-\.\_\%]+)/config/structure"]["get"] = "config/get_structure/$1";
$route["^apis/([\w\-\.\_\%]+)/config/structure"]["put"] = "config/replace_structure/$1";
$route["^apis/([\w\-\.\_\%]+)/config/structure"]["patch"] = "config/patch_structure/$1";
$route["^apis/([\w\-\.\_\%]+)/config/structure/regen"]["get"] = "config/regen/$1";

$route["^apis/([\w\-\.\_\%]+)/config/auth"]["get"] = "config/get_auth/$1";
$route["^apis/([\w\-\.\_\%]+)/config/auth"]["patch"] = "config/update_auth/$1";
$route["^apis/([\w\-\.\_\%]+)/config/auth"]["put"] = "config/replace_auth/$1";


$route["^apis/([\w\-\.\_\%]+)"]["GET"] = "config/api/$1";
$route["^apis/([\w\-\.\_\%]+)/(data|v2)"]["GET"] = "config/api_endpoints/$1";
$route["^apis/([\w\-\.\_\%]+)$"]["post"] = "config/create/$1";
$route["^apis/([\w\-\.\_\%]+)$"]["delete"] = "config/delete/$1";
$route["^apis$"]["get"] = "config/list_apis";




// stored procedures
$route["^$basePath/([\w\-\.\_\%]+)/__call__/([\w\-\. \_\%]+)$"] ["post"] = "$controller/callStoredProcedure/$1/$2";



// first family: - bulk operations /
// #1
$route["^$basePath/([\w\-\.\_\%]+)"]["post"] ="$controller/createMultipleRecords/$1";
// #2
$route["^$basePath/([\w\-\.\_\%]+)"]["patch"] ="$controller/updateMultipleRecords/$1";
// #3
//$route["^$basePath/([\w\-\. \_\%]+)"]["delete"] ="$controller/deleteMultipleRecords/$1";

//$route["^[v2|data]/([\w\-\.\_\%]+)"] = "$controller/base/$1";

// second family: /resourceŃame
// #4 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)"]["get"] = "$controller/getMultipleRecords/$1";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getRecords/$1/$2";
// #5
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["post"] = "$controller/createRecords/$1/$2";
// #5.1
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateWhere/$1/$2";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateMultipleRecords/$1/$2";

// #5.2
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteMultipleRecords/$1/$2";

// third family: /resourceName/id
// #6 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getSingleRecord/$1/$2";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getRecords/$1/$2/$3";
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
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getRelated/$1/$2/$3/$4";
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["post"] = "$controller/createRelated/$1/$2/$3/$4";
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateRelated/$1/$2/$3/$4";


// fifth family: /resourceName/id/relation/id
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getRelated/$1/$2/$3/$4/$5";
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateRelated/$1/$2/$3/$4/$5";
// OK
$route["^$basePath/([\w\-\.\_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteRelated/$1/$2/$3/$4/$5";

$route["^$basePath/.*"]["options"] = "$controller/options";





/*
 * new format
 */




$apiDataUrlPrefix = "^apis/([\w\-\.\_\%]+)/data";
// first family: - bulk operations /
// #1
$route[$apiDataUrlPrefix]["post"] ="$controller/createMultipleRecords/$1";
// #2
$route[$apiDataUrlPrefix]["patch"] ="$controller/updateMultipleRecords/$1";
// #3
//$route["^apis/([\w\-\. \_\%]+)"]["delete"] ="$controller/deleteMultipleRecords/$1";

//$route["^[v2|data]/([\w\-\.\_\%]+)"] = "$controller/base/$1";

// second family: /resourceŃame
// #4 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)"]["get"] = "$controller/getMultipleRecords/$1";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["get"] = "$controller/getRecords/$1/$2";
// #5
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["post"] = "$controller/createRecords/$1/$2";
// #5.1
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateWhere/$1/$2";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateMultipleRecords/$1/$2";

// #5.2
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteMultipleRecords/$1/$2";

// third family: /resourceName/id
// #6 OK
//$route["^$stdOpsPath/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getSingleRecord/$1/$2";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getRecords/$1/$2/$3";
// #7
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateSingleRecord/$1/$2/$3";
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
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getRelated/$1/$2/$3/$4";
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["post"] = "$controller/createRelated/$1/$2/$3/$4";
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateRelated/$1/$2/$3/$4";


// fifth family: /resourceName/id/relation/id
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["get"] = "$controller/getRelated/$1/$2/$3/$4/$5";
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["patch"] = "$controller/updateRelated/$1/$2/$3/$4/$5";
// OK
$route["$apiDataUrlPrefix/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)/([\w\-\. \_\%]+)"]["delete"] = "$controller/deleteRelated/$1/$2/$3/$4/$5";

$route["^apis/.*"]["options"] = "$controller/options";


$apiAuthUrlPrefix = "^apis/([\w\-\.\_\%]+)/auth";
$route["$apiAuthUrlPrefix/login"]["post"] = "auth/login/$1";
$route["$apiAuthUrlPrefix/login"]["post"] = "auth/login/$1";





