<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Former Admin API (/admin/apis/...). Returns 410 with migration hints.
 * Control plane is exclusively /mgmt/v1/apis — see routes_mgmt.php.
 */
$route['^admin/apis']['get'] = 'errors/deprecated_admin';
$route['^admin/apis']['post'] = 'errors/deprecated_admin';
$route['^admin/apis']['put'] = 'errors/deprecated_admin';
$route['^admin/apis']['patch'] = 'errors/deprecated_admin';
$route['^admin/apis']['delete'] = 'errors/deprecated_admin';
$route['^admin/apis/(.+)']['get'] = 'errors/deprecated_admin';
$route['^admin/apis/(.+)']['post'] = 'errors/deprecated_admin';
$route['^admin/apis/(.+)']['put'] = 'errors/deprecated_admin';
$route['^admin/apis/(.+)']['patch'] = 'errors/deprecated_admin';
$route['^admin/apis/(.+)']['delete'] = 'errors/deprecated_admin';
