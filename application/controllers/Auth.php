<?php
require_once(APPPATH."libraries/HttpResp.php");
require_once (BASEPATH."/../vendor/autoload.php");
//require_once(APPPATH."third_party/Softaccel/Autoloader.php");
//\Softaccel\Autoloader::register();
//use Softaccel\Apiator\DBApi\DBWalk;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Class Auth
 * @property CI_Config config
 * @property CI_Loader load
 * @property CI_DB_driver db
 */
class Auth extends CI_Controller {
    function __construct()
    {
        parent::__construct();
        $this->config->load("apiator");
    }

    private function connectDb($configName) {

        $cfgDir = $this->config->item("api_config_dir")($configName);
        if(!is_dir($cfgDir)) {
            HttpResp::not_found("API not found");
        }
        $conn = @include $cfgDir."/connection.php";
        if(!$conn) {
            HttpResp::server_error("No DB connection file found");
        }

        $auth = @include $cfgDir."/auth.php";
        if(!$auth) {
            HttpResp::bad_request(["error"=>"No authentication mechanism configured"]);
        }

        $conn = @$this->load->database($conn);
        if($this->db->error()["code"]!==0) {
            HttpResp::server_error(["error"=>$this->db->error()]);
        }
        return $auth;
    }

    /**
     * @param $configName
     */
    function login($configName) {
        $auth = $this->connectDb($configName);
        //print_r($auth);
        $uname = $this->input->post($auth["loginUserInputField"]);
        $upass = $this->input->post($auth["loginPassInputField"]);
        $unameFld = $auth["loginUserInputField"];
        $upassFld = $auth["loginPassInputField"];

        $sql = str_replace(
            ["[[$unameFld]]" , "[[$upassFld]]"],
            [$uname,$upass],
            $auth["loginQuery"]);

        /**
         * @var CI_DB_result
         */
        $res = $this->db->query($sql);
        if($this->db->error()["code"]>0) {
            HttpResp::server_error(["error"=>$this->db->error()]);
        }

        if($res->num_rows()!==1) {
            HttpResp::not_found(["error"=>["message"=>"Username not found or password incorrect","code"=>404]]);
        }

        $payload = $res->row_array();
        $payload["_exp"] = time()+$auth["validity"];

        $jwt = JWT::encode($payload, $auth["key"], @$auth["alg"] ? $auth["alg"] : 'HS256');
        HttpResp::json_out(200,["jwt"=>$jwt]);


//        $this->db->query()
    }
}