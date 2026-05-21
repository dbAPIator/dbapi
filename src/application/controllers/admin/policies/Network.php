<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'core/MY_AdminController.php');

class Network extends MY_AdminController {
    public function __construct() {
        parent::__construct();
        $this->authorize_apis_admin();
    }
    public function get($apiName) {
        $this->authorize_config_update($apiName);
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        $network = @include "$configPath/policies/config-network.php";
        // if(empty($network)) {
        //     HttpResp::not_found();
        // }
        HttpResp::json_out(200,$network);
    }
    
    public function update($apiName) {
        $this->authorize_config_update($apiName);
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        $network = @include "$configPath/policies/config-network.php";
        if(empty($network)) {
            HttpResp::not_found();
        }
        try {
            $payload = $this->validate_payload();
        } catch (Exception $e) {
            HttpResp::bad_request([
                "error"=>"Invalid payload",
                "details"=>json_decode($e->getMessage())
            ]);
            return;
        }
        file_put_contents("$configPath/policies/config-network.php",to_php_code(json_decode(json_encode($payload),true),true));
        HttpResp::json_out(200,$payload);
    }
}