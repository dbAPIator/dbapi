<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 6/19/18
 * Time: 11:51 AM
 */
defined('BASEPATH') OR exit('No direct script access allowed');

define("BASE_DOMAIN",".dbapi.apiator");
define("APIS_DIR","/var/www/domains/apiator/apiator_data/___apis");
define("CFG_DIR_REL_PATH","/dbapi");

$config["default_page_size"] = 10;

$config["default_resource_access_read"] = true;
$config["default_resource_access_update"] = true;
$config["default_resource_access_insert"] = true;
$config["default_resource_access_delete"] = true;

$config["default_field_access_insert"] = true;
$config["default_field_access_update"] = true;
$config["default_field_access_select"] = true;
$config["default_field_access_sort"] = true;
$config["default_field_access_search"] = true;


$config["api_config_dir"] = (function() {
    $tmp = explode(BASE_DOMAIN,$_SERVER["SERVER_NAME"]);
    if(count($tmp)<2)
        die("Invalid URL");
    $apiId = $tmp[0];

    return APIS_DIR."/$apiId".CFG_DIR_REL_PATH;
})();

