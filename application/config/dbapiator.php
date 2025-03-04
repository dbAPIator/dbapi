<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 6/19/18
 * Time: 11:51 AM
 */

defined('BASEPATH') OR exit('No direct script access allowed');

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
$config["configs_dir"] = $_ENV["CONFIGS_DIR"]  ?? "/var/www/html/dbapi/dbconfigs";


// default linked resources set page size
$config["default_relationships_page_size"] = $_ENV["DEFAULT_RELATIONSHIPS_PAGE_SIZE"] ?? 10;
// default page size
$config["default_page_size"] = $_ENV["DEFAULT_PAGE_SIZE"] ?? 100;
// max page size
$config["max_page_size"] = $_ENV["MAX_PAGE_SIZE"] ?? 10000;

/**
 *  Configuration API settings. These settings are taken into account when creating a new API configuration, deleting an existing one or generating a new API key for an existing API configuration. 
 * For updating an existing API configuration, use the secret generated when the API was created.
 */
// configuration API secret; pass it in header as x-api-key or as an URL parameter
$config["config_api_secret"] = $_ENV["CONFIG_API_SECRET"] ?? "myverysecuresecret";
// restrict access to configuration API based on IPs
$config["config_api_allowed_ips"] = isset($_ENV["CONFIG_API_ALLOWED_IPS"]) ? json_decode($_ENV["CONFIG_API_ALLOWED_IPS"]) : ["0.0.0.0/0"];

