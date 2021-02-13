<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 3/15/18
 * Time: 5:51 PM
 */

$config["authDbConn"] = [
    'dsn'	=> '',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'parola123',
    'database' => 'apiator-saas',
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => (ENVIRONMENT !== 'production'),
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt' => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries' => TRUE
];

$config["tables"] = [
    "users"     => "users",
    "clients"   => "oauth2_clients",
    "access_tokens"    => "oauth2_access_tokens",
    "refresh_tokens"    => "oauth2_refresh_tokens"
];

$config["oauth2Paras"] = [
    "access_lifetime"=>86400
];

$config["grantParas"] = [
    "always_issue_new_refresh_token" => true,
    "unset_refresh_token_after_use" => true,
    "refresh_token_lifetime" => 2419200,
];