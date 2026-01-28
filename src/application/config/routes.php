<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'config/home';
$route['404_override'] = 'config/not_found';
$route['translate_uri_dashes'] = false;


// ApiId part of the domain name
$basePath = "^apis";

// Data API prefix
$dataApiPrefix = "^apis/(:any)/data";
$apiDataUrlPrefix = "^apis/(:any)/data";


// other routes
include "routing/routes_other.php";

// config routes
include "routing/routes_api_config.php";

// auth routes
include "routing/routes_auth.php";

// table routes
include "routing/routes_table_lvl.php";

// record routes
include "routing/routes_record_lvl.php";

// relation routes
include "routing/routes_relation_lvl.php";

// related record routes
include "routing/routes_related_record_lvl.php";



// stored procedures






/*

// third family: /resourceName/id/_relationships/relation
// #9
$route[$dataApiPrefix."/(:any)/(:any)/_relationships/(:any)"]["get"] = "dbapi/get_relationship/$1/$2/$3/$4";
// #10
$route[$dataApiPrefix."/(:any)/(:any)/_relationships/(:any)"]["post"] = "dbapi/create_relationship/$1/$2/$3/$4";
// #11
$route[$dataApiPrefix."/(:any)/(:any)/_relationships/(:any)"]["patch"] = "dbapi/update_relationship/$1/$2/$3/$4";
// #12
$route[$dataApiPrefix."/(:any)/(:any)/_relationships/(:any)"]["delete"] = "dbapi/delete_relationship/$1/$2/$3/$4";


*/







/*
 * new format
 */


// fifth family: /resourceName/id/relation/id
// OK
$route["$apiDataUrlPrefix/(:any)/(:any)/(:any)/(:any)"]["get"] = "dbapi/get_related/$1/$2/$3/$4/$5";
$route["$apiDataUrlPrefix/(:any)/(:any)/(:any)/(:any)"]["patch"] = "dbapi/update_related/$1/$2/$3/$4/$5";
// OK
$route["$apiDataUrlPrefix/(:any)/(:any)/(:any)/(:any)"]["delete"] = "dbapi/deleteRelated/$1/$2/$3/$4/$5";








