<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// get table records     /resourceName  /table_name
$route[$dataApiPrefix."/(:any)"]["get"] = "dbapi/get_records/$1/$2";

// create records     /resourceName  /table_name
$route[$dataApiPrefix."/(:any)"]["post"] = "dbapi/create_records/$1/$2";

// bulk update records     /resourceName  /table_name
$route[$dataApiPrefix."/(:any)"]["patch"] = "dbapi/bulk_update/$1/$2";

// bulk delete records     /resourceName  /table_name
$route[$dataApiPrefix."/(:any)"]["delete"] = "dbapi/bulk_delete/$1/$2";
