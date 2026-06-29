<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// get sub-related records     /resource/id/relation/id/sub_relation
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)/(:any)/(:any)']['get'] = 'dbapi/get_related_2nd/$1/$2/$3/$4/$5/$6';

// create sub-related records
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)/(:any)/(:any)']['post'] = 'dbapi/create_related_2nd/$1/$2/$3/$4/$5/$6';

// update sub-related records (bulk)
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)/(:any)/(:any)']['patch'] = 'dbapi/update_related_2nd/$1/$2/$3/$4/$5/$6';

// get sub-related record     /resource/id/relation/id/sub_relation/id
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)']['get'] = 'dbapi/get_related_2nd/$1/$2/$3/$4/$5/$6/$7';

// update sub-related record
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)']['patch'] = 'dbapi/update_related_2nd/$1/$2/$3/$4/$5/$6/$7';

// delete sub-related record
$route[$dataApiPrefix . '/(:any)/(:any)/(:any)/(:any)/(:any)/(:any)']['delete'] = 'dbapi/delete_related_2nd/$1/$2/$3/$4/$5/$6/$7';
