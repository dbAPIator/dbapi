<?php
defined('BASEPATH') OR exit('No direct script access allowed');


// get related records     /resourceName  /table_name  /id  /relation
$route[$dataApiPrefix."/(:any)/(:any)/(:any)"]["get"] = "dbapi/get_related/$1/$2/$3/$4";

// create related records     /resourceName  /table_name  /id  /relation
$route[$dataApiPrefix."/(:any)/(:any)/(:any)"]["post"] = "dbapi/create_related/$1/$2/$3/$4";

// update related records     /resourceName  /table_name  /id  /relation
$route[$dataApiPrefix."/(:any)/(:any)/(:any)"]["patch"] = "dbapi/update_related/$1/$2/$3/$4";
