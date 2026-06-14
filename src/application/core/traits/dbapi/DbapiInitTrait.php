<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use dbAPI\API\AccessControl;
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

    private function security_check(string $configName) {
        $this->load->library("Utilities");

        $headers = getallheaders() ?: [];

        $security = [];
        $data = @include "$this->configDir/$configName/{$this->configFiles['data_api_acls']}";
        $security = array_merge($security, $data ? $data : []);

        $ipAcls = $security["IP"] ?? [];
        $allow = false;
        foreach ($ipAcls as $rule) {
            if ($rule["allow"] && $this->utilities->ip_in_cidr($_SERVER["REMOTE_ADDR"], $rule["ip"])) {
                $allow = true;
                break;
            }
        }
        if (!$allow) {
            throw new Exception("IP ".$_SERVER["REMOTE_ADDR"]." not allowed", 401);
        }

        $auth = @include "$this->configDir/$configName/{$this->configFiles['auth']}";
        if (!is_array($auth)) {
            $auth = [];
        }
        $this->apiAuthConfig = $auth;

        $jwt = AccessControl::decodeJwt($auth, $headers, $_SERVER);
        $payload = $jwt['payload'];
        $this->apiJwtPayload = $payload;
        $this->apiJwtValid = $jwt['valid'];
        $this->apiJwtAnonymous = $jwt['anonymous'];

        $userData = AccessControl::claimSubstitutions($payload);

        if ($this->input->get("dbg")) {
            print_r($payload);
        }

        $reqPath = $this->dataRequestPath($configName);
        $method = $_SERVER["REQUEST_METHOD"] ?? 'GET';
        $rules = $security["path"] ?? [];
        $pathResult = AccessControl::evaluatePathRules($rules, $reqPath, $method, $userData, $payload);

        if ($pathResult !== null) {
            if (!$pathResult) {
                throw new Exception("You are not allowed to access this resource ".$_SERVER['REQUEST_URI'], 401);
            }
            return;
        }

        // Legacy configs: non-empty path rules with no match → deny (pre-table-access behavior).
        if (!empty($rules)) {
            throw new Exception("You are not allowed to access this resource ".$_SERVER['REQUEST_URI'], 401);
        }

        $structure = @include "$this->configDir/$configName/{$this->configFiles['structure']}";
        if (!is_array($structure)) {
            $structure = [];
        }

        $tableAllowed = AccessControl::evaluateTableAccess(
            $auth,
            $structure,
            $reqPath,
            $method,
            $payload,
            $this->apiJwtValid,
            $this->apiJwtAnonymous
        );

        if ($this->input->get("dbg")) {
            echo "tableAllowed: ".($tableAllowed ? '1' : '0')."\n";
        }

        if (!$tableAllowed) {
            if ($this->apiJwtAnonymous && AccessControl::resolveDefaultAccessRule($auth) === AccessControl::ACCESS_PRIVATE) {
                throw new Exception("No JWT token provided", 401);
            }
            throw new Exception("You are not allowed to access this resource ".$_SERVER['REQUEST_URI'], 401);
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
        $this->recs->setAccessContext($this->apiAuthConfig, $this->apiJwtPayload);
    }

    /**
     * debug function: shows datamodel
     * final
     */
}
