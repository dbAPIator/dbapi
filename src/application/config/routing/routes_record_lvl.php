<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// get single record     /resourceName  /table_name  /id
$route[$dataApiPrefix."/(:any)/(:any)"]["get"] = "dbapi/get_records/$1/$2/$3";

// update single record     /resourceName  /table_name  /id
$route[$dataApiPrefix."/(:any)/(:any)"]["patch"] = "dbapi/update_single_record/$1/$2/$3";

// delete single record     /resourceName  /table_name  /id
$route[$dataApiPrefix."/(:any)/(:any)"]["delete"] = "dbapi/delete_single_record/$1/$2/$3";
