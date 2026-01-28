<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// get related record     /resourceName  /table_name  /id  /relation  /id
$route[$dataApiPrefix."/(:any)/(:any)/(:any)/(:any)"]["get"] = "dbapi/get_related/$1/$2/$3/$4/$5";

// update related record     /resourceName  /table_name  /id  /relation  /id
$route[$dataApiPrefix."/(:any)/(:any)/(:any)/(:any)"]["patch"] = "dbapi/update_related/$1/$2/$3/$4/$5";

// delete related record     /resourceName  /table_name  /id  /relation  /id
$route[$dataApiPrefix."/(:any)/(:any)/(:any)/(:any)"]["delete"] = "dbapi/deleteRelated/$1/$2/$3/$4/$5";
