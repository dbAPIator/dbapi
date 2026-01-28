<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$apiAuthUrlPrefix = "^apis/(:any)/auth";
$route[$apiAuthUrlPrefix."/login"]["post"] = "auth/login/$1";
$route[$apiAuthUrlPrefix."/verify"]["post"] = "auth/mfa_code_verify/$1";
$route[$apiAuthUrlPrefix."/apiclienttoken/(.*)/(\d+)"]["get"] = "auth/genApiClientToken/$1/$2/$3";
