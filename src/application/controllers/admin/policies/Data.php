<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH.'core/MY_AdminController.php');

class Data extends MY_AdminController {
    public function __construct() {
        parent::__construct();
    }
    public function get($apiName) {
        $this->authorize_config_update($apiName);
        $data = "Data policy not implemented";
        HttpResp::json_out(200,$data);
    }
    public function update($apiName) {
        $this->authorize_config_update($apiName);
        $data = "Data policy not implemented";
        HttpResp::json_out(200,$data);
    }
}