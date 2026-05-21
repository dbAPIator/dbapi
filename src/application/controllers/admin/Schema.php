<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'core/MY_AdminController.php');

class Schema extends MY_AdminController {
    public function __construct() {
        parent::__construct();
    }
    public function introspect($apiName) {
        $this->authorize_config_update($apiName);
        $introspect = "Introspection not implemented";
        HttpResp::json_out(200,$introspect);
    }
    public function get_introspected($apiName) {
        $this->authorize_config_update($apiName);
        $introspected = "Introspection not implemented";
        HttpResp::json_out(200,$introspected);
    }
    public function get_overrides($apiName) {
        $this->authorize_config_update($apiName);
        $overrides = "Overrides not implemented";
        HttpResp::json_out(200,$overrides);
    }
    public function replace_overrides($apiName) {
        $this->authorize_config_update($apiName);
        $overrides = "Overrides not implemented";
        HttpResp::json_out(200,$overrides);
    }   
    public function patch_overrides($apiName) {
        $this->authorize_config_update($apiName);
        $overrides = "Overrides not implemented";
        HttpResp::json_out(200,$overrides);
    }
    public function get_effective($apiName) {
        $this->authorize_config_update($apiName);
        $effective = "Effective not implemented";
        HttpResp::json_out(200,$effective);
    }
    public function rebuild($apiName) {
        $this->authorize_config_update($apiName);
        $rebuild = "Rebuild not implemented";
        HttpResp::json_out(200,$rebuild);
    }
    public function preview($apiName) {
        $this->authorize_config_update($apiName);
        $preview = "Preview not implemented";
        HttpResp::json_out(200,$preview);
    }
}