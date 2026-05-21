<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'core/MY_AdminController.php');
class Connection extends MY_AdminController {
    public function __construct() {
        parent::__construct();
    }
    
    public function get($apiName) {
        
        $this->authorize_apis_admin();
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        $connection = @include "$configPath/connection.php";
        if(empty($connection)) {
            HttpResp::not_found();
        }
        $connection["password"] = "***********";
        HttpResp::json_out(200,$connection);
    }

    public function update($apiName) {
        $this->authorize_apis_admin();
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        try {
            $payload = $this->validate_payload();
        } catch (Exception $e) {
            HttpResp::exception_out($e);
            return;
        }
        $connection = $payload;
        file_put_contents("$configPath/connection.php",to_php_code(json_decode(json_encode($connection),true),true));
        $connection->password = "***********";
        HttpResp::json_out(200,$connection);
    }


    public function test($apiName) {
        
        $this->authorize_apis_admin();
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        $connection = @include "$configPath/connection.php";
        //print_r($connection);
        if(empty($connection)) {
            HttpResp::not_found();
        }
        $start = microtime(true);
        try {
            $db = @$this->load->database(remap_conn_fields($connection),true);
        } catch (Exception $e) {
            HttpResp::exception_out($e);
            return;
        }
        $end = microtime(true);
        $latencyMs = ($end - $start) * 1000;
        
        $error = $db->error();
        if($error["code"]!==0) {
            HttpResp::bad_request(["error" => $error["message"]]);
        }
        
        
        HttpResp::json_out(200,[
            "status" => "ok",
            "at" => time(),
            "latencyMs" => $latencyMs,
            "errorCode" => null
        ]);
    }
}

function remap_conn_fields($connection) {
    return [
        "dbdriver" => "mysqli",
        "hostname" => $connection["host"],
        "username" => $connection["username"],
        "password" => $connection["password"],
        "database" => $connection["database"]
    ];
}