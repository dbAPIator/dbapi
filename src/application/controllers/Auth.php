<?php
require_once(APPPATH."libraries/HttpResp.php");
require_once (BASEPATH."/../vendor/autoload.php");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Class Auth
 * Processes user authentication by username and password and MFA when enabled by user
 * @property CI_Config config
 * @property CI_Loader load
 * @property CI_DB_driver db
 * @property CI_Input input
 */
class Auth extends CI_Controller {
    private $configDir;

    function __construct()
    {
        parent::__construct();
        $this->config->load("dbapiator");
        $this->configDir = $this->config->item("configs_dir");
    }

    /**
     * Connects to database
     * @param $configName
     * @return mixed
     */
    private function db_connect($configName) {
        $cfgDir = $this->configDir."/$configName";

//        print_r($_ENV);
        if(!is_dir($cfgDir)) {
            HttpResp::not_found("API not found");
        }

        $auth = @include $cfgDir."/authentication.php";
        if(!$auth) {
            HttpResp::bad_request(["error"=>"No authentication mechanism configured"]);
        }

        $conn = @include $cfgDir."/connection.php";
        $conn["db_debug"] = FALSE;
        try {
            $this->load->database($conn);
        }
        catch (Exception $exception) {
            HttpResp::service_unavailable(["errors"=>[["message"=>"Could not connect to database"]]]);
        }
        
        if($this->db->error()["code"]!==0) {
            HttpResp::service_unavailable(["errors"=>[["message"=>"Could not connect to database"]]]);
        }
        return $auth;
    }

    /**
     * Create MFA session by generating a token and a verification code
     * The code is sent tu user over selected channel (currently only email is implemented)
     * @param $auth
     * @param $payload
     * @throws Exception
     */
    private function mfa_session_create($auth, $payload) {
        $verification_code = random_int(100000, 999999);
        $this->load->helper("string_helper");
        $session_token = random_string("md5",30);
        if($payload["email"]) {
            $this->send_code_per_email($auth,$payload,$session_token,$verification_code);

            $sql = str_replace(
                ["[[username]]","[[token]]","[[code]]","[[expire]]"],
                [$payload["unm"],$session_token,$verification_code,time()+300],
                $auth["mfaCreateTokenSql"]);
            $this->db->query($sql);

            $jwt = JWT::encode([
                "unm"=>$payload["unm"],
                "mfatoken" =>$session_token
            ], $auth["key"], @$auth["alg"] ? $auth["alg"] : 'HS256');
            HttpResp::json_out(200,["jwt"=>$jwt],["Authorization"=>"Bearer $jwt","Content-type"=>"application/json"]);
            die();
        }
        HttpResp::error_out_json(json_encode($payload),500);
    }

    /**
     * @param $auth
     * @param $payload
     * @param $session_token
     * @param $verification_code
     */
    private function send_code_per_email($auth,$payload,$session_token,$verification_code) {
        $url = $auth["mfaEmailApiUrl"]."/send";
        $data = [
            'token' => $auth["mfaEmailApiToken"],
            'to' => $payload["email"],
            'subject' => str_replace(
                ["[[email]]" , "[[uname]]"],
                [$payload["email"],$payload["unm"]],
                $auth["mfaEmailSubjectTemplate"]),
            'message' => str_replace(
                ["[[email]]" , "[[uname]]","[[cod]]"],
                [$payload["email"],$payload["unm"],$verification_code],
                $auth["mfaEmailMessageTemplate"]),
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            HttpResp::server_error("Could not send email with verification code. Please contact the administrator: ".print_r($options));
        }
    }

    /**
     * @param $configName
     */
    function mfa_code_verify($configName) {
        $auth = $this->db_connect($configName);
        $uname = $this->input->post($auth["loginUserInputField"]);
        $token = $this->input->post("token");
        $code = $this->input->post("code");

        $sql = str_replace(
            ["[[username]]","[[token]]","[[code]]"],
            [$uname,$token,$code],
            $auth["mfaVerifyCodeByUpdateSql"]
        );
        $this->db->query($sql);
        if($this->db->affected_rows()) {
            $sql = str_replace(
                "[[username]]",
                $uname,
                $auth["mfaVerifiedOKGetUserQuery"]);

            /** @var CI_DB_result $res */
            $res = $this->db->query($sql);
            $result = $res->row_array();

            $this->generate_token($result,$auth);
        }
        else {
            HttpResp::error_out_json("Invalid or expired code",400);
        }
    }

    /**
     * @param $userId
     * @param $validity
     */
    function genApiClientToken($configName,$uname,$validity) {
        $auth = $this->db_connect($configName);
        $sql = str_replace(
            "[[username]]",
            $uname,
            $auth["mfaVerifiedOKGetUserQuery"]);
        /** @var CI_DB_result $res */
        $res = $this->db->query($sql);
        $result = $res->row_array();

        if(!$result)
            HttpResp::not_found();
        $this->generate_token($result,$auth);
    }

    /**
     * @param $result
     * @param $auth
     */
    private function generate_token($payload,$auth) {
        $validity = $auth["validity"];
        $payload["exp"] = time()+$validity;
            $jwt = JWT::encode($payload, $auth["jwt_key"],  'HS256');
        HttpResp::json_out(200,[
            "access_token"=>$jwt,
            "expires_in"=>$validity,
            "token_type"=>"Bearer"
        ]);
    }

    /**
     * @param $configName
     * @return array
     */
    private function fetch_user($configName) {
        $auth = $this->db_connect($configName);

        $uname = $this->input->post("login");
        $upass = $this->input->post("password");

        $sql = strtr($auth["loginQuery"],["[[login]]"=>$uname,"[[password]]"=>$upass]);

        /**
         * @var CI_DB_result
         */
        $res = $this->db->query($sql);
        return [$res,$auth];
    }

    /**
     * @param $configName
     * @throws Exception
     */
    function login($configName) {
        [$res,$auth] = $this->fetch_user($configName);
        if($this->db->error()["code"]>0) {
            HttpResp::server_error(["error"=>$this->db->error()]);
        }

        if($res->num_rows()!==1) {
            HttpResp::not_found(["errors"=>["message"=>"Username not found or password incorrect","code"=>404]]);
        }

        $result = $res->row_array();

        if(@$result["mfa_enabled"])
            $this->mfa_session_create($auth,$result);
        else
            $this->generate_token($result,$auth);

    }
}