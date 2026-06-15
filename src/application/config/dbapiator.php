<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 6/19/18
 * Time: 11:51 AM
 */

defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('dbapi_env')) {
    /**
     * Read config from environment (works in FPM, CLI, and Docker).
     *
     * @param mixed $default
     * @return mixed
     */
    function dbapi_env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        return $default;
    }
}

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

if (!defined('DBAPI_DEFAULT_API_ID')) {
    require_once APPPATH . 'helpers/deployment_helper.php';
}

// deployment: "multi" (default) or "single" (Docker single-database onboarding)
$config["deployment_mode"] = dbapi_env("DEPLOYMENT_MODE", "multi");
$config["default_api_id"] = DBAPI_DEFAULT_API_ID;

// path to where the APIs confingurations are stored
$config["configs_dir"] = dbapi_env("CONFIGS_DIR", "/var/www/html/dbapi/dbconfigs");


// default linked resources set page size
$config["default_relationships_page_size"] = (int) dbapi_env("DEFAULT_RELATIONSHIPS_PAGE_SIZE", 10);
// default page size
$config["default_page_size"] = (int) dbapi_env("DEFAULT_PAGE_SIZE", 100);
// max page size
$config["max_page_size"] = (int) dbapi_env("MAX_PAGE_SIZE", 1000);

// filter expression guardrails
$config["max_filter_expression_length"] = (int) dbapi_env("MAX_FILTER_EXPRESSION_LENGTH", 4096);
$config["max_filter_ast_depth"] = (int) dbapi_env("MAX_FILTER_AST_DEPTH", 20);
$config["max_filter_ast_nodes"] = (int) dbapi_env("MAX_FILTER_AST_NODES", 100);

// bulk write limits
$config["bulk_insert_limit"] = (int) dbapi_env("BULK_INSERT_LIMIT", 100);
$config["bulk_update_limit"] = (int) dbapi_env("BULK_UPDATE_LIMIT", 50);

// per-request / query timeout (seconds); override per API in connection.php as query_timeout_seconds
$config["request_timeout_seconds"] = (int) dbapi_env("REQUEST_TIMEOUT_SECONDS", 60);

// nested include depth for GET ?include=
$config["max_include_depth"] = (int) dbapi_env("MAX_INCLUDE_DEPTH", 5);

/**
 *  Configuration API settings. These settings are taken into account when creating a new API configuration, deleting an existing one or generating a new API key for an existing API configuration. 
 * For updating an existing API configuration, use the secret generated when the API was created.
 */
// configuration API secret; pass it in header as x-api-key or as an URL parameter
$config["config_api_secret"] = dbapi_env("CONFIG_API_SECRET", "myverysecuresecret");
// restrict access to configuration API based on IPs
$ipsAcls = dbapi_env("CONFIG_API_IPS_ACLS");
$config["config_api_ips_acls"] = $ipsAcls !== null
    ? json_decode($ipsAcls, true)
    : [["allow" => true, "ip" => "0.0.0.0/0"]];

$config["redis_host"] = dbapi_env("REDIS_HOST");
$config["redis_port"] = (int) dbapi_env("REDIS_PORT", 6379);
$config["redis_user"] = dbapi_env("REDIS_USER");
$config["redis_password"] = dbapi_env("REDIS_PASSWORD");
$config["redis_stream"] = dbapi_env("REDIS_STREAM");
$config["redis_group"] = dbapi_env("REDIS_GROUP");

$config["files"] = [
    "auth" => "authentication.php",
    "data_api_acls" => "data_api_acls.php",
    "admin_config" => "admin_config.php",
    "connection" => "connection.php",
    "patch" => "patch.php",
    "structure" => "structure.php",
    "openapi" => "openapi.json",
];
