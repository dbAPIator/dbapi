<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use dbAPI\API\Datamodel;

/**
 * @property CI_Config $config
 * @property CI_Loader $load
 * @property CI_Input $input
 * @property Utilities $utilities
 */
trait DbapiInitTrait
{
    public function remap($method, $params = array()) {
        // Rate limiting check
        $clientIp = $this->input->ip_address();
        $rateCheck = $this->rateLimiter->check($clientIp);
        
        if (!$rateCheck['allowed']) {
            $this->output
                ->set_status_header(429)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Too Many Requests',
                    'reset' => $rateCheck['reset'],
                    'remaining' => 0
                ]));
            return;
        }

        // Add rate limit headers
        $this->output
            ->set_header('X-RateLimit-Limit: ' . $this->rateLimiter->limit)
            ->set_header('X-RateLimit-Remaining: ' . $rateCheck['remaining'])
            ->set_header('X-RateLimit-Reset: ' . $rateCheck['reset']);

        // Continue with your existing _remap logic
        // ... existing code ...
    }

    /**
     * do security checks like
     * - check if client IP is allowed
     * - check which client rules match and if req is allowed
     * - authenticate req based on JWT
     */
    /**
     * Resource path segment after /v1/apis/{apiId}/data or /apis/{apiId}/data.
     */
    private function dataRequestPath(string $configName): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $installBase = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($installBase && $installBase !== '/' && strpos($path, $installBase) === 0) {
            $path = substr($path, strlen($installBase)) ?: '/';
        }

        if (!function_exists('is_single_deployment_mode')) {
            require_once APPPATH . 'helpers/deployment_helper.php';
        }
        if (is_single_deployment_mode()) {
            $prefix = '/v1/data';
            if (strpos($path, $prefix) === 0) {
                $rest = substr($path, strlen($prefix));
                return $rest === '' ? '/' : $rest;
            }
            return $path;
        }

        foreach (["/v1/apis/{$configName}/data", "/apis/{$configName}/data"] as $prefix) {
            if (strpos($path, $prefix) === 0) {
                $rest = substr($path, strlen($prefix));
                return $rest === '' ? '/' : $rest;
            }
        }
        $legacy = ($this->basePath ? $this->basePath : '') . "/{$configName}/data";
        if ($legacy && strpos($path, $legacy) === 0) {
            $rest = substr($path, strlen($legacy));
            return $rest === '' ? '/' : $rest;
        }
        return $path;
    }

    private function  security_check(string $configName) {
        $this->load->library("Utilities");

        $headers = getallheaders();

        // default security rules
        $security = [];

        $data = @include "$this->configDir/$configName/{$this->configFiles['data_api_acls']}";
        $security = array_merge($security,$data ? $data : []);

        // check if client IP is allowed 
        $ipAcls = $security["IP"] ?? [];
        $allow = false;
        foreach($ipAcls as $rule) {
            if($rule["allow"] && $this->utilities->ip_in_cidr($_SERVER["REMOTE_ADDR"],$rule["ip"])) {
                $allow = true;
                break;
            }
        }
        if(!$allow) {
            throw new Exception("IP ".$_SERVER["REMOTE_ADDR"]." not allowed",401);
        }

        /*
         * Authenticate request based on JWT tokens
         */

        $auth = @include "$this->configDir/$configName/{$this->configFiles['auth']}";
        $authMode = $auth['mode'] ?? null;
        $allowGuest = $auth['allowGuest'] ?? ($authMode === 'none');
        $requiresJwt = !empty($auth['jwt_key']) && $authMode !== 'none';

        // extract JWT from Authorization header
        $payload = new stdClass();
        if ($requiresJwt) {
            try {
                preg_match("/Bearer (.*$)/i",@$headers["Authorization"],$matches);
                $jwt = count($matches)==2 ? $matches[1] : null;
                if(empty($jwt)) {
                    throw new Exception("No JWT token provided",401);
                }
                $payload = JWT::decode($jwt,new Key($auth["jwt_key"],'HS256'));
            }
            catch (Exception $e) {
                if (!$allowGuest) {
                    throw $e;
                }
                $payload = new stdClass();
            }

            if (!$allowGuest) {
                if (isset($payload->exp) && $payload->exp < time()) {
                    throw new Exception("Token expired",401);
                }
            }
        }
        $userData = [];
        foreach(get_object_vars($payload) as $key => $value) {
            $userData['{{'.$key.'}}'] = $value;
        }


        // check path rules
        $rules = $security["path"] ?? [];

        if($this->input->get("dbg")) {
            print_r($payload);
        }

        $reqPath = $this->dataRequestPath($configName);
        $allow = false;
        foreach ($rules as $rule) {
            $urlPattern = "/^".strtr(str_replace(["/","*"],["\\/",".*"],$rule["pattern"]),$userData)."$/i";
            
            if(!preg_match($urlPattern,$reqPath)) {
                // echo "url not allowed $reqPath".json_encode($rule)."\n";
                continue;
            }

            $ruleMethod = $rule['method'] ?? $rule['methods'] ?? null;
            if ($ruleMethod) {
                $methodPattern = "/^".strtoupper(str_replace("*",".*",$ruleMethod))."$/i";
                if(!preg_match($methodPattern,$_SERVER["REQUEST_METHOD"])) {
                    // echo "method not allowed".$_SERVER["REQUEST_METHOD"].json_encode($rule)."\n";
                    continue;
                }
            }
           
            if($this->input->get("dbg")) {
                echo "urlPattern: $urlPattern\n";
                echo "reqPath: $reqPath\n";
            }
            $allow = $rule["allow"] ?? false;
            break;
        }
        
        if($this->input->get("dbg")) {
            echo "allow: $allow\n";
        }

        if(!$allow) {
            throw new Exception("You are not allowed to access this resource $_SERVER[REQUEST_URI]",401);
        }
    }
    private function _init(string $configName)
    {
        if($this->apiConfigDir)
            return;

        $this->apiConfigDir = "$this->configDir/$configName";

        $this->baseUrl = $this->config->item("base_url");
        $this->basePath = $this->config->item("base_path");
        $this->JsonApiDocOptions["baseUrl"] = $this->baseUrl;

        if(!is_dir($this->apiConfigDir)) {
            RequestContext::log('error', 'API config directory not found', ['api' => $configName]);
            HttpResp::exception_out(new Exception("Invalid API config dir $this->apiConfigDir",404));
        }

        $statusFile = $this->apiConfigDir . '/status.php';
        if (is_file($statusFile)) {
            $apiStatus = @include $statusFile;
            if (is_string($apiStatus) && $apiStatus !== 'active') {
                $this->config->load('errorscatalog');
                $cat = $this->config->item('errors_catalog');
                HttpResp::json_out(409, [
                    'error' => [
                        'code' => (int) $cat['config']['api_not_active']['code'],
                        'message' => $cat['config']['api_not_active']['message'],
                        'details' => ['status' => $apiStatus],
                    ],
                ]);
            }
        }

        try{
            $this->security_check($configName);
        }
        catch (Exception $e) {
            HttpResp::exception_out($e);
        }


        // load structure
        $structure = @include($this->apiConfigDir."/structure.php");
        if(!$structure) {
            // Invalid API config
            // TODO: log error: wrong api config
            HttpResp::exception_out(new Exception("Invalid API configuration",404));
        }

        // load connection
        $dbConf = @include($this->apiConfigDir."/connection.php");
        if(!isset($dbConf)) {
            HttpResp::server_error("Invalid database config");
        }


        // load permissions
        // todo: depending on the API client, load the appropriate permissions file
        $apiKey = $this->input->get("api_key")?$this->input->get("api_key"):$this->input->server("HTTP_X_API_KEY");
        if(empty($apiKey)) {
            $profileFIle = "/profiles/default.php";
        }
        else {
            $profileFIle = "/clients/$apiKey.php";
        }

        $permissions = [];


        // todo configure settings
        $settings = [];


        $apiCfg = array_merge_recursive($permissions,$structure);

        /**
         * @var CI_DB_pdo_driver db
         */
        $db = $this->load->database($dbConf,TRUE);

        if($db->error()["code"]!==0) {
            RequestContext::log('error', 'Database connection failed', ['api' => $configName]);
            HttpResp::service_unavailable(HttpResp::errorPayload('Could not connect to database', null, 503));
        }

        ApiSafety::applyQueryTimeout($db, is_array($dbConf) ? $dbConf : []);

        // initializes DM with structure fetched from $apiCfg
        $dm = Datamodel::init($apiCfg);
        if(!$dm) {
            // TODO log wrong config file
            HttpResp::server_error("Invalid API datamodel");
        }

        $this->apiDb = $db;
        $this->apiDm = $dm;
        $this->apiSettings = $settings;

        // initialize recs
        $this->recs = \dbAPI\API\Records::init($this->apiDb,$this->apiDm,$this->apiConfigDir);
        if(!$this->recs) {
            // TODO log unable to initialize records navigator class
            HttpResp::server_error("Invalid API config");
        }
    }

    /**
     * debug function: shows datamodel
     * final
     */
}
