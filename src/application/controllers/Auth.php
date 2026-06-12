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
     * @param $configName
     * @return array
     */
    private function load_auth_config($configName): array
    {
        $cfgDir = $this->configDir."/$configName";

        if(!is_dir($cfgDir)) {
            HttpResp::not_found("API not found");
        }

        $auth = @include $cfgDir."/authentication.php";
        if(!$auth) {
            HttpResp::bad_request(["error"=>"No authentication mechanism configured"]);
        }

        return $this->normalize_auth_config($auth);
    }

    /**
     * Connects to database
     * @param $configName
     * @return mixed
     */
    private function db_connect($configName) {
        $auth = $this->load_auth_config($configName);

        $cfgDir = $this->configDir."/$configName";
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
     * Backward compat: legacy loginQuery becomes loginMethods.password.
     * @param array $auth
     * @return array
     */
    private function normalize_auth_config(array $auth): array
    {
        if (empty($auth['loginMethods']) && !empty($auth['loginQuery'])) {
            $auth['loginMethods'] = [
                'password' => ['loginQuery' => $auth['loginQuery']],
            ];
        }
        return $auth;
    }

    /**
     * @param string $query
     * @return string[]
     */
    private function placeholders_in_query(string $query): array
    {
        preg_match_all('/\[\[(\w+)\]\]/', $query, $matches);
        return array_values(array_unique($matches[1]));
    }

    /**
     * @param array $auth
     * @param string $method
     * @param array $methodConfig
     * @return array{0:CI_DB_result,1:array}
     */
    private function fetch_user_by_method(array $auth, string $method, array $methodConfig): array
    {
        $query = $methodConfig['loginQuery'] ?? null;
        if (empty($query)) {
            HttpResp::bad_request(['error' => 'Login method is not configured', 'method' => $method]);
        }

        $fields = $methodConfig['fields'] ?? $this->placeholders_in_query($query);
        if (empty($fields)) {
            HttpResp::bad_request(['error' => 'Login method has no input fields configured', 'method' => $method]);
        }

        $replacements = [];
        foreach ($fields as $field) {
            $value = $this->input->post($field);
            if ($value === null || $value === '') {
                HttpResp::bad_request(['error' => "Missing required field: {$field}", 'method' => $method]);
            }
            $replacements["[[{$field}]]"] = $value;
        }

        $sql = strtr($query, $replacements);
        /** @var CI_DB_result $res */
        $res = $this->db->query($sql);

        $effectiveAuth = $auth;
        if (isset($methodConfig['validity'])) {
            $effectiveAuth['validity'] = (int) $methodConfig['validity'];
        }

        return [$res, $effectiveAuth];
    }

    /**
     * @param string $name
     * @param array $methodConfig
     * @param array $auth
     * @return array
     */
    private function public_login_method_descriptor(string $name, array $methodConfig, array $auth): array
    {
        $query = $methodConfig['loginQuery'] ?? '';
        $fields = $methodConfig['fields'] ?? $this->placeholders_in_query($query);

        $descriptor = [
            'name' => $name,
            'fields' => $fields,
        ];

        $validity = $methodConfig['validity'] ?? ($auth['validity'] ?? null);
        if ($validity !== null) {
            $descriptor['expiresIn'] = (int) $validity;
        }

        return $descriptor;
    }

    /**
     * @param $configName
     */
    function login_methods($configName) {
        $auth = $this->load_auth_config($configName);

        if (($auth['mode'] ?? null) === 'none') {
            HttpResp::json_out(200, ['loginMethods' => []]);
            return;
        }

        $methods = [];
        foreach ($auth['loginMethods'] ?? [] as $name => $methodConfig) {
            if (!is_array($methodConfig) || empty($methodConfig['loginQuery'])) {
                continue;
            }
            $methods[] = $this->public_login_method_descriptor($name, $methodConfig, $auth);
        }

        HttpResp::json_out(200, ['loginMethods' => $methods]);
    }

    /**
     * @param $configName
     * @param $loginMethod
     * @throws Exception
     */
    function login($configName, $loginMethod) {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $loginMethod)) {
            HttpResp::bad_request(['error' => 'Invalid login method name', 'method' => $loginMethod]);
        }

        $auth = $this->db_connect($configName);

        if (empty($auth['loginMethods']) || !is_array($auth['loginMethods'])) {
            HttpResp::bad_request(['error' => 'No login methods configured']);
        }

        if (empty($auth['loginMethods'][$loginMethod]) || !is_array($auth['loginMethods'][$loginMethod])) {
            HttpResp::not_found(['error' => 'Unknown or disabled login method', 'method' => $loginMethod]);
        }

        [$res, $auth] = $this->fetch_user_by_method($auth, $loginMethod, $auth['loginMethods'][$loginMethod]);
        if($this->db->error()["code"]>0) {
            HttpResp::server_error(["error"=>$this->db->error()]);
        }

        if($res->num_rows()!==1) {
            HttpResp::not_found(["errors"=>["message"=>"Authentication failed","code"=>404]]);
        }

        $result = $res->row_array();
        $result['login_method'] = $loginMethod;

        if(@$result["mfa_enabled"])
            $this->mfa_session_create($auth,$result);
        else
            $this->generate_token($result,$auth);

    }
}