<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$autoload = defined('BASEPATH') ? BASEPATH . '/../vendor/autoload.php' : __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once(APPPATH."libraries/HttpResp.php");
require_once(APPPATH."libraries/Utilities.php");
require_once(APPPATH."libraries/OpenApiBodyValidator.php");
require_once(APPPATH."third_party/dbAPI/Autoloader.php");
\dbAPI\Autoloader::register();

/**
 * MY_AdminController is a base controller for all admin controllers
 * It provides common functionality for all admin controllers
 * @property CI_Config config
 * @property CI_Loader load
 * @property CI_Input input
 * @property Utilities utilities
 * @property CI_DBUtil dbutil
 */
class MY_AdminController extends CI_Controller {
    protected $configDir;
    protected $headers;
    protected $errorsCatalog;
    protected $configFiles;
    protected $utilities;
    protected $apisMap;
    protected $linksDir;

    public function __construct() {
        parent::__construct();
        $this->config->load("dbapiator");
        $this->utilities = new Utilities();
        $this->load->helper("string");
        $this->load->helper("config_util");
        $this->headers = getallheaders();
        $this->load->config("errorscatalog");
        $this->errorsCatalog = $this->config->item("errors_catalog");
        $this->configFiles = $this->config->item("files");
        $this->configDir = $this->config->item("configs_dir");
        $this->linksDir = $this->config->item("links_dir");
        
        $this->apisMap = @include "$this->configDir/apis.php" ?? [];
    }
    public function authorize_config_update($apiName) {
        //$this->authorize_config_update($apiName);
    }
    protected function authorize_apis_admin() {
        $secret = $this->headers["x-admin-api-key"] ?? $this->headers["X-Admin-Api-Key"] 
            ?? $this->input->get("X-Admin-Api-Key") ?? $this->input->get("X-Admin-Api-Key") 
            ?? null;
        // check secret
        if(!$secret || $secret!==$this->config->item("config_api_secret")) {
            HttpResp::not_authorized($this->errorsCatalog["access"]["secret_not_authorized"]);
        }
        
        // check if IP is allowed
        $ipsAcl = $this->config->item("config_api_ips_acls");
        error_log("ipsAcl: ".json_encode($ipsAcl)."\n");
        if(!$this->utilities->IP_is_allowed($ipsAcl)) {
            HttpResp::not_authorized($this->errorsCatalog["access"]["ip_not_authorized"]);
        }
    }

    protected function get_api_config_path($apiName) {
        //return $this->apisMap[$apiName] ?? null;
        return @readlink("$this->linksDir/$apiName");
    }

    protected function rename_api($apiName, $newApiName) {
        rename("$this->linksDir/$apiName", "$this->linksDir/$newApiName");
    }

    protected function add_api_link($dir,$apiName) {
        symlink($dir, "$this->linksDir/$apiName");
        return readlink("$this->linksDir/$apiName");
    }

    protected function validate_payload() {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            HttpResp::bad_request(['Invalid JSON']);
            return;
        }

        $validator = new OpenApiBodyValidator(APPPATH.'../public/admin-openapi.json');

        try {
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $validator->validate($_SERVER['REQUEST_METHOD'], $path, $payload);
        } catch (InvalidArgumentException $e) {
            //HttpResp::bad_request(['Validation failed', 'details' => json_decode($e->getMessage(), true)]);
            // echo $e->getMessage();
            // exit();
            throw new Exception( $e->getMessage(),1001);
        }
        return $payload;
    }
}

