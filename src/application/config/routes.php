<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'errors/home';
$route['404_override'] = 'errors/error_404';
$route['translate_uri_dashes'] = false;

// Management API (sole control plane)
include 'routing/routes_mgmt.php';

require_once APPPATH . 'helpers/deployment_helper.php';

if (is_single_deployment_mode()) {
    include 'routing/routes_mgmt_single.php';
}

// Legacy Admin API paths → 410 Gone (see errors/deprecated_admin)
include 'routing/routes_deprecated_admin.php';

if (is_single_deployment_mode()) {
    include 'routing/routes_single.php';
} else {
    // v1 data plane
    $dataApiPrefix = '^v1/apis/(:any)/data';
    $basePath = '^v1/apis';
    $apiAuthUrlPrefix = '^v1/apis/(:any)/auth';
    include 'routing/routes_other.php';
    include 'routing/routes_auth.php';
    include 'routing/routes_table_lvl.php';
    include 'routing/routes_record_lvl.php';
    include 'routing/routes_relation_lvl.php';
    include 'routing/routes_related_record_lvl.php';
    include 'routing/routes_sub_relation_lvl.php';

    // Legacy data plane (backward compatibility)
    $dataApiPrefix = '^apis/(:any)/data';
    $basePath = '^apis';
    $apiAuthUrlPrefix = '^apis/(:any)/auth';
    include 'routing/routes_other.php';
    include 'routing/routes_auth.php';
    include 'routing/routes_table_lvl.php';
    include 'routing/routes_record_lvl.php';
    include 'routing/routes_relation_lvl.php';
    include 'routing/routes_related_record_lvl.php';
    include 'routing/routes_sub_relation_lvl.php';
}
