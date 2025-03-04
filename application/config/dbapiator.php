<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 6/19/18
 * Time: 11:51 AM
 */

defined('BASEPATH') OR exit('No direct script access allowed');

// location where API configurations are saved
define("CFG_DIR_BASEPATH",__DIR__."/../../dbconfigs");

// default resource access rules (tables and views);
// custom rules can be configured on a per table/view base
$config["default_resource_access_read"] = true;
$config["default_resource_access_update"] = true;
$config["default_resource_access_insert"] = true;
$config["default_resource_access_delete"] = true;

// default field level access rights
$config["default_field_access_insert"] = true;
$config["default_field_access_update"] = true;
$config["default_field_access_select"] = true;
$config["default_field_access_sort"] = true;
$config["default_field_access_search"] = true;

// path to where the APIs confingurations are stored
$config["configs_dir"] = isset($_ENV["CONFIGS_DIR"]) ?  $_ENV["CONFIGS_DIR"]  : "/var/www/html/dbapi/dbconfigs";


// default linked resources set page size
$config["default_relationships_page_size"] = 10;
// default page size
$config["default_page_size"] = 100;
// max page size
$config["max_page_size"] = 10000;

// configuration API secret; pass it in header as x-api-key or as an URL parameter
$config["configApiSecret"] = isset($_ENV["SECRET"]) ?  $_ENV["SECRET"]  : "myverysecuresecret";
// restrict access to configuration API based on IPs
$config["configApiAllowedIPs"] = [
    "0.0.0.0/0"
];

