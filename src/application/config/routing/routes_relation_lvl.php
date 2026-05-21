<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// get related records     /data/{resource}/{id}/{relation}
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)']['get'] = 'dbapi/get_related/$1/$2/$3/$4';

// create related records
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)']['post'] = 'dbapi/create_related/$1/$2/$3/$4';

// update related records
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)']['patch'] = 'dbapi/update_related/$1/$2/$3/$4';
