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
$route['default_controller'] = 'home';
//$route['404_override'] = 'errors/not_found';
$route['translate_uri_dashes'] = false;


// /api/apiName/entryPoint
$controller = "dbapi";
// API
$route["^api/([\w\-\_]+)"] = "$controller/$1";

// APIID/EP
//$route["^api/([\w\-\_]+)/([\w\-\_]+)"] = "$controller/api/$1";

// api/$apiId/$resName
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)"]["get"] = "$controller/fetch_multiple_records/$1/$2";
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)"]["post"] = "$controller/simple_insert/$1/$2";
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)"]["delete"] = "$controller/bulk_delete/$1/$2";
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)"]["patch"] = "$controller/update_bulk/$1/$2";



// api/$apiId/$resName/$resId
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)/([\w\-\_%]+)$"]["get"] = "$controller/fetch_record_by_id/$1/$2/$3";
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)/([\w\-\_%]+)$"]["delete"] = "$controller/single_delete/$1/$2/$3";
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)/([\w\-\_%]+)$"]["patch"] = "$controller/update/$1/$2/$3";
$route["^api/([\w\-\_%]+)/([\w\-\_%]+)/([\w\-\_%]+)$"]["post"] = "$controller/create/$1/$2/$3";

$route["^api\/([\w\-\_]+)\/([\w\-\_]+)\/([\w\-\_]+)\/relationships\/([\w\-\_]+)"]["get"] = "$controller/fetch_relationships/$1/$2/$3/$4";


// APIID/RESOURCE/ID/relationships/RELNAME
$route["^api\/([\w\-\_%]+)\/([\w\-\_\%]+)\/([\w\-\_\%]+)\/relationships\/([\w\-\%_]+)"]["get"] = "$controller/read/$1/$2/$3/rels/$4";
$route["^api\/([\w\-\_%]+)\/([\w\-\_\%]+)\/([\w\-\_\%]+)\/relationships\/([\w\-\%_]+)"]["put"] = "$controller/update/$1/$2/$3/rels/$4";
$route["^api\/([\w\-\_%]+)\/([\w\-\_\%]+)\/([\w\-\_\%]+)\/relationships\/([\w\-\%_]+)"]["post"] = "$controller/create/$1/$2/$3/rels/$4";
$route["^api\/([\w\-\_%]+)\/([\w\-\_\%]+)\/([\w\-\_\%]+)\/relationships\/([\w\-\%_]+)"]["delete"] = "$controller/delete/$1/$2/$3/rels/$4";

$route["^api/(.*)"]["options"] = "$controller/options";