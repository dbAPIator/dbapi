<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route["^test/"]["get"] ="dbapi/test";
$route["^test/(.*)"]["get"] ="dbapi/test/$1";

// call stored procedure     /resourceName  /procedureName
$route[$dataApiPrefix."/__call__/(:any)$"] ["post"] = "dbapi/call_stored_procedure/$1/$2";

// OPTIONS method for all APIs
$route[$basePath."/.*"]["options"] = "dbapi/options";

// Swagger documentation
$route[$basePath."/(:any)/swagger"]["get"] = "swagger/index/$1";

