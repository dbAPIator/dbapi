<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'core/MY_AdminController.php');

class Hooks extends MY_AdminController {
    public function __construct() {
        parent::__construct();
    }
    public function get($apiName) {
        $this->authorize_config_update($apiName);
        $hooks = "Hooks not implemented";
        HttpResp::json_out(200,$hooks);
    }
    public function replace_all($apiName) {
        $this->authorize_config_update($apiName);
        $hooks = "Hooks not implemented";
        HttpResp::json_out(200,$hooks);
    }   
    public function update_entity($apiName, $entityName) {
        $this->authorize_config_update($apiName);
        $hooks = "Hooks not implemented";
        HttpResp::json_out(200,$hooks);
    }
    public function delete_from_entity($apiName, $entityName) {
        $this->authorize_config_update($apiName);
        $hooks = "Hooks not implemented";
        HttpResp::json_out(200,$hooks);
    }
}   