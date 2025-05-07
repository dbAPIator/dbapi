<?php
require_once(APPPATH."libraries/HttpResp.php");
//require_once (BASEPATH."/../vendor/autoload.php");
require_once(APPPATH."third_party/dbAPI/Autoloader.php");
\dbAPI\Autoloader::register();

use dbAPI\Config\DBWalk;
// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;

/**
 * @todo: authenticate/limit request
 */
/**
 * Class Config
 * @property CI_Loader load
 * @property CI_Input input
 * @property CI_Config config
 * @property Utilities utilities
 * @property CI_DBUtil dbutil
 */
class Config extends CI_Controller {
    /**
     * @var array|false
     */
    private $headers;
    private $errorsCatalog;
    private $configFiles;
    private $configDir;

    public function __construct() {
        parent::__construct();
        header("Content-type: application/json");
        $this->config->load("dbapiator");
        $this->load->library("Utilities");
        $this->load->helper("string");
        $this->load->helper("config_util");
        $this->headers = getallheaders();
        $this->load->config("errorscatalog");
        $this->errorsCatalog = $this->config->item("errors_catalog");
        $this->configFiles = $this->config->item("files");
        $this->configDir = $this->config->item("configs_dir");
    }


    /**
     * check if API exists and if the client is authorized
     * @param $apiName
     * @return bool
     */
    private function api_exists($apiName, $return=false) {
        $apiDir = "$this->configDir/$apiName";
        //echo "apiDir: $apiDir\n";
        if(!is_dir($apiDir)) {
            if(!$return) {
                HttpResp::not_found($this->errorsCatalog["config"]["api_not_found"]);
            }
            return false;
        }
        return true;
    }

    /**
     * @param $config
     * @return mixed
     * @todo to implement
     */
    private function sanitize_auth_config($config) {
        return $config;
    }

    /**
     * @param $apiName
     */
    function update_auth($apiName) {
        $this->authorize_config_update($apiName);

        $apiConfigDir = "$this->configDir/$apiName/{$this->configFiles['auth']}";

        $authData = $this->sanitize_auth_config($this->get_input_data());

        $oldAuth = @include $apiConfigDir;
        $oldAuth = is_array($oldAuth) ? $oldAuth : [];

        // @todo: validate auth input
        $defaultAuth = [
            "key" => $oldAuth["key"] ?? md5(random_string().time()),
            "alg" => 'HS256',
            "validity" => 3600
        ];

        $authData = smart_array_merge_recursive($oldAuth,$authData);
        $authData = smart_array_merge_recursive($defaultAuth,$authData);
        $this->save_config($apiConfigDir,$authData);
        HttpResp::json_out(200,$authData);
    }

    /**
     * @param $apiName
     */
    function replace_auth($apiName) {
        $this->authorize_config_update($apiName);

        $apiConfigDir = "$this->configDir/$apiName/{$this->configFiles['auth']}";
        $authData = $this->sanitize_auth_config($this->get_input_data()) ?? [];

        $defaultAuth = [
            "key" => md5(random_string().time()),
            "alg" => 'HS256',
            "validity" => 3600
        ];


        $authData = smart_array_merge_recursive($defaultAuth,$authData);
        $this->save_config($apiConfigDir,$authData);

        if(!$this->input->get("include") || $this->input->get("include")!=="key")
            $authData["key"] = "**********";

        HttpResp::json_out(200,$authData);
    }


    /**
     * @param $apiName
     */
    function get_auth($apiName) {
        $this->authorize_config_update($apiName);

        $auth = @include "$this->configDir/$apiName/{$this->configFiles['auth']}";
        if(!$auth) {
            HttpResp::json_out(200,[]) && die();
        }


        if(!$this->input->get("include") || $this->input->get("include")!=="key")
            $auth["key"] = "**********";

        HttpResp::json_out(200,$auth);

    }


  

    private function IP_is_allowed($acls) {
        // check if IP is allowed
        $allowed = false;
        foreach ($acls as $rule) {
            if(!in_array($rule["action"],["allow","deny"])) continue;
            if($this->utilities->ip_in_cidr($_SERVER["REMOTE_ADDR"],$rule["ip"])) {
                if($rule["action"]=="allow"){
                    $allowed = true;
                    break;
                } else {
                    HttpResp::not_authorized($this->errorsCatalog["access"]["ip_not_authorized"]);
                }
            }
        }

        if(!$allowed) {
            HttpResp::not_authorized($this->errorsCatalog["access"]["ip_not_authorized"]);
        }
    }

    private function authorize_config_update($apiName) {
        $this->api_exists($apiName);

        $apiConfigDir = "$this->configDir/$apiName";
        $security = @include "$apiConfigDir/{$this->configFiles['admin_config']}";

        // check secret
        $secret = $this->headers["x-api-key"] ?? $this->headers["X-Api-Key"] ?? $this->input->get("xApiKey") ?? null;
        if(!$secret || $secret!==$security["secret"]) {
            HttpResp::not_authorized($this->errorsCatalog["access"]["api_config_secret_not_authorized"]);
        }

        $this->IP_is_allowed($security["acls"]);

    }

    private function authorize_apis_admin() {
        $secret = $this->headers["x-api-key"] ?? $this->headers["X-Api-Key"] ?? $this->input->get("xApiKey") ?? null;
        
        // check secret
        if(!$secret || $secret!==$this->config->item("config_api_secret")) {
            HttpResp::not_authorized($this->errorsCatalog["access"]["secret_not_authorized"]);
        }
        
        // check if IP is allowed
        $allowedIps = $this->config->item("config_api_allowed_ips");
        if(!is_array($allowedIps)) {
            $allowedIps = [$allowedIps];
        }
        foreach($allowedIps as $allowedIp) {
            if($this->utilities->ip_in_cidr($_SERVER["REMOTE_ADDR"],$allowedIp)) {
                return;
            }
        }
        HttpResp::not_authorized($this->errorsCatalog["access"]["ip_not_authorized"]);
        
    }

    /**
     * @return mixed
     */
    private function get_input_data() {
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? explode(";",$_SERVER["CONTENT_TYPE"])[0] : null;
        switch($contentType) {
            case "multipart/form-data":
                $data = $this->input->post();
                break;
            default:
                $data = json_decode($this->input->raw_input_stream,JSON_OBJECT_AS_ARRAY);
                if(json_last_error()!==JSON_ERROR_NONE || !is_array($data)) {
                    HttpResp::invalid_request($this->errorsCatalog["input"]["invalid_input_data"]);
                }
                break;
        }
        return sanitize_data($data);
    }

    private function create_database($conn,$data) {
            
        $tmpConn = $conn;
        unset($tmpConn["database"]);
        
        $db = $this->load->database($tmpConn,true);
        
        $dbforge = $this->load->dbforge($db, true);                
        if($this->load->dbutil($db,true)->database_exists($conn['database'])){
            // database exists
            if($data["create"]["drop_before_create"] ?? false){
                // drop database
                if (@$dbforge->drop_database($conn['database'])) {
                    $dbforge->create_database($conn['database']);
                    $db->close();
                    $db = @$this->load->database($conn,true);
                }        
                else {
                    throw new Exception("Could not drop database {$conn['database']}");
                }        
            }
            else{
                throw new Exception("Database already exists. Will not drop it.");
            }
        
        }
        else {
            // database does not exist - create it
            $dbforge->create_database($conn['database']);
            $db->close();
            $db = @$this->load->database($conn,true);
        }
        
        
        $createSql = preg_replace("/CREATE DATABASE.*?;/i", "", json_decode($data["create"]["sql"]));
        
        $db->trans_start();

        $statements = explode(';', $createSql);
        foreach($statements as $sql) {
            // Unescape any escaped strings before executing query
            $sql =  trim($sql);
            if (!empty($sql)) {
                $db->query($sql);
            }
        }
        $db->trans_complete();
        if ($db->trans_status() === FALSE) {
            $error = $db->error();
            
            HttpResp::server_error([
                "error" => "Could not create database {$conn['database']} ",
                "details" => $error['message']
            ]);
        }
    }
    /**
     * @param $apiName
     */
    function create_api() {
        if($_SERVER["REQUEST_METHOD"]!=="POST") {
            HttpResp::json_out(405,["error"=>"Method not allowed"]);
        }
        $this->authorize_apis_admin();
        $data = $this->get_input_data();
        
        // extract and validate API name
        if(!isset($data["name"])) {
            HttpResp::bad_request($this->errorsCatalog["config"]["db_name_not_provided"]);
        }
        $apiName = $data["name"];
        if($this->api_exists($apiName,true)) {
            HttpResp::json_out(409,$this->errorsCatalog["config"]["api_exists"]);
        }

        // extract and validate connection parameters
        $conn = [
            "dbdriver" => "mysqli",
            "hostname" => "localhost",
            "username" => "",
            "password" => "",
            "database" => ""
        ];
        if(!isset($data["connection"])){
            HttpResp::json_out(406,["error"=>"Connection parameters not provided"]);
        }
        $conn = array_merge($conn,$data["connection"]);

        // extract and validate adminConfig parameters
        $adminConfig = [
            "acls" => [
                [
                    "ip" => $_SERVER["REMOTE_ADDR"],
                    "action" => "allow"
                ],
                [
                    "ip" => "0.0.0.0/0",
                    "action" => "deny"
                ],
                
            ]
        ];  
        if(isset($data["adminConfig"])) {
            $adminConfig = array_merge($adminConfig,$data["adminConfig"]);
        }
        $adminConfig["secret"] = bin2hex(openssl_random_pseudo_bytes(32));

        // extract and validate dataApi parameters
        $dataApi = [
            "acls" => [
                "IP" => [
                    [
                        "ip" => $_SERVER["REMOTE_ADDR"],
                        "action" => "allow"
                    ],
                    [
                        "ip" => "0.0.0.0/0",
                        "action" => "deny"
                    ]
                ],
                "path" => [
                    [
                        "pattern" => "/*",
                        "method" => "*",
                        "action" => "deny"
                    ]
                ]
            ]
        ];
        if(isset($data["dataApi"])) {
            $dataApi = array_merge($dataApi,$data["dataApi"]);
            if(isset($dataApi["authentication"])) {
                if(isset($dataApi["authentication"]["loginQuery"])) {
                    $dataApi["authentication"]["jwt_key"] = bin2hex(openssl_random_pseudo_bytes(32));
                    $dataApi["authentication"]["validity"] = $dataApi["authentication"]["validity"] ?? 3600;
                }
                else {
                    unset($dataApi["authentication"]);
                }
            }
        }
        
        // Check if host is reachable before attempting database connection
        if (!$this->is_host_port_available($conn['hostname'], 3306)) {
            HttpResp::json_out(406, ["error" => "Cannot connect to database host: {$conn['hostname']} on port 3306"]);
        }
        
        try {
            if(@$data["create"]["sql"]){ 
                $this->create_database($conn,$data);
            }
            $path = $this->config->item("configs_dir")."/$apiName";

            $structure = $this->generate_config($conn, $path);

            if(!is_dir($path) && !@mkdir($path))
                throw new Exception("Could not create config directory {$path}");
        }
        catch (Exception $exception){
            HttpResp::exception_out($exception);
        }
        


        mkdir("$path/clients");
        $accessRights = 0666;
        $this->save_config("$path/{$this->configFiles['structure']}",$structure,$accessRights);
        $this->save_config("$path/{$this->configFiles['connection']}",$conn,$accessRights);
        $this->save_config("$path/{$this->configFiles['patch']}",[],$accessRights);
        $this->save_config("$path/{$this->configFiles['auth']}",$dataApi["authentication"] ?? [],$accessRights);
        $this->save_config("$path/{$this->configFiles['data_api_acls']}",$dataApi["acls"] ?? [],$accessRights);
        $this->save_config("$path/{$this->configFiles['admin_config']}",$adminConfig,$accessRights);
        
        $this->get_admin_secret($apiName);
    }

    /**
     * @return void
     */
    function list_apis() {
        $this->authorize_apis_admin();

        $apiConfigDir = $this->config->item("configs_dir");
        $dir = @opendir($apiConfigDir);
        if(!$dir) {
            HttpResp::server_error(["error"=>"Invalid configs directory"]);
        }
        $entries = [];
        while($entry=readdir($dir)) {
            if(in_array($entry,[".",".."]) || is_file($entry)) continue;
            $entries[] = $entry;
        }
        HttpResp::json_out(200,$entries);
    }

    /**
     * triggers the regeneration of the 
     * @param $apiName
     * @throws Exception
     */
    function regen_structure($apiName) {
        $this->authorize_config_update($apiName);
        $apiConfigDir = $this->config->item("configs_dir")."/$apiName";
        $conn = include "$apiConfigDir/{$this->configFiles['connection']}";

        $oldStructure = include("$apiConfigDir/{$this->configFiles['structure']}");
        try{
            $newStructure = $this->generate_config($conn,$apiConfigDir);
        } catch (Exception $exception) {
            HttpResp::exception_out($exception);
        }


        foreach(array_keys($newStructure) as $resourceName){
            // copy hooks from old structure to new structure
            if(isset($oldStructure[$resourceName]) && isset($oldStructure[$resourceName]["hooks"])){
                $newStructure[$resourceName]["hooks"] = $oldStructure[$resourceName]["hooks"];
            }
        }

        $this->save_config("$apiConfigDir/{$this->configFiles['structure']}",$newStructure);
        
        $this->get_structure($apiName);
    }

    /**
     * @param $apiName
     */
    private function get_structure($apiName) {
        $path = $this->config->item("configs_dir")."/$apiName/{$this->configFiles['structure']}";
        $structure = include $path;
        //echo file_get_contents($path);
        HttpResp::json_out(200,$structure);
    }

    private function validate_connection_config($config) {
        return $config;
    }
    /**
     * @param $apiName
     */
    function get_connection($apiName) {
        $this->authorize_config_update($apiName);
        $connection = @include $this->config->item("configs_dir")."/$apiName/connection.php";
        $connection["password"]="***********";
        unset($connection["dbdriver"]);
        HttpResp::json_out(200,$connection);
    }

    /**
     * Checks if a host is up and a port is open
     * @param string $host The hostname or IP address
     * @param int $port The port number
     * @param int $timeout Connection timeout in seconds
     * @return bool True if host is up and port is open, false otherwise
     */
    private function is_host_port_available($host, $port, $timeout = 3) {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    function update_connection($apiName) {
        $this->authorize_config_update($apiName);
        $connection = include $this->config->item("configs_dir")."/$apiName/connection.php";
        $config = $this->validate_connection_config($this->get_input_data());
        
        // Check if host is reachable before attempting database connection
        if (!$this->is_host_port_available($config['hostname'], 3306)) {
            HttpResp::bad_request(["error" => "Cannot connect to database host: {$config['hostname']} on port 3306"]);
        }
        
        $db = @$this->load->database($config, true);
        if (!$db) {
            HttpResp::bad_request("Invalid database connection parameters");
        }
    }

    function authentication($apiName) {
        $this->api_exists($apiName);
        $this->authorize_config_update($apiName);
        switch($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                $this->get_authentication($apiName);
                break;
            case "PATCH":
                $this->update_authentication($apiName);
                break;
            case "PUT":
                $this->replace_authentication($apiName);
                break;
        }
    }

    function get_authentication($apiName) {
        $auth = include "$this->configDir/$apiName/{$this->configFiles['auth']}";
        HttpResp::json_out(200,$auth);
    }

    function replace_authentication($apiName) {
        $this->authorize_config_update($apiName);
        $auth = $this->get_input_data();
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['auth']}",$auth);
        $this->get_authentication($apiName);
    }

    function update_authentication($apiName) {
        $this->authorize_config_update($apiName);
        $auth = $this->get_input_data();
        $existingAuth = include "$this->configDir/$apiName/{$this->configFiles['auth']}";
        $mergedAuth = array_merge($existingAuth,$auth);
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['auth']}",$mergedAuth);
        $this->get_authentication($apiName);
    }

     
    /**
     * @param $apiName
     */
    private function replace_structure($apiName) {
        $this->authorize_config_update($apiName);

        $new_structure = $this->get_input_data();
        if(is_null($new_structure)) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]);
        }


        $conn = include "$this->configDir/$apiName/{$this->configFiles['connection']}";
        // get natural structure
        $db_struct = DBWalk::parse($this->load->database($conn,true),$conn['database'])['structure'];
        // compute difference
        $diff = compute_struct_diff($db_struct,$new_structure);

        // save patch file
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['patch']}",$diff);

        //$newStruct = smart_array_merge_recursive($db_struct,$diff);

        //save structure
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['structure']}",$new_structure);

        HttpResp::json_out(200,$new_structure);
    }

    function patch_structure($apiName) {
        $this->authorize_config_update($apiName);

        $data = $this->get_input_data();;
        if(!$data) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]) && die();
        }
        $conn = require_once($this->config->item("configs_dir")."/$apiName/connection.php");
        $structure = DBWalk::parse($this->load->database($conn,true),$conn['database'])['structure'];
        $patch = @include $this->config->item("configs_dir")."/$apiName/patch.php";
        $patch = $patch ? $patch : [];
        $newStruct = smart_array_merge_recursive($structure,$patch);
        $newStruct = smart_array_merge_recursive($newStruct,$data);

        // compute difference
        $diff = compute_struct_diff($structure,$newStruct);
        // create patch file
        if(count($diff)) {
            $patchFileName = $this->config->item("configs_dir")."/$apiName/patch.php";
            $this->save_config($patchFileName,$diff);
        }
        $structFileName = $this->config->item("configs_dir")."/$apiName/structure.php";
        $newStruct = smart_array_merge_recursive($structure,$diff);
        $this->save_config($structFileName,$newStruct);
        HttpResp::json_out(200,$newStruct);
    }

    function acls_ip($apiName) {
        $this->api_exists($apiName);
        $this->authorize_config_update($apiName);
        switch($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                $this->get_acls_ip($apiName);
                break;
            case "PUT":
                $this->update_acls_ip($apiName);
                break;
        }
    }

    private function get_acls_ip($apiName) {
        $acls = include "$this->configDir/$apiName/{$this->configFiles['data_api_acls']}";
        HttpResp::json_out(200,$acls['IP'] ?? []);
    }

    private function update_acls_ip($apiName) {
        $acls = $this->get_input_data();
        if(!is_array($acls)) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]);
        }
        // Check if array is numerical (list) or associative (hash)
        $oldAcls = include "$this->configDir/$apiName/{$this->configFiles['data_api_acls']}";
        $oldAcls['IP'] = [];
        foreach ($acls as $acl) {
            if(!is_array($acl) || !isset($acl['ip']) || !isset($acl['action'])
                    || !in_array($acl['action'],['allow','deny'])
                    || !preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(\/[0-9]{1,2})?$/",$acl['ip'])) {
                HttpResp::json_out(400, ["error" => "Invalid ACL rule {$acl['ip']} {$acl['action']}"]);
            }
            $oldAcls['IP'][] = $acl;
        }
        
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['data_api_acls']}",$oldAcls);
        $this->get_acls_ip($apiName);
    }

    function acls_path($apiName) {
        $this->api_exists($apiName);
        $this->authorize_config_update($apiName);
        switch($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                $this->get_acls_path($apiName);
                break;
            case "PUT":
                $this->update_acls_path($apiName);
                break;
        }
    }

    private function get_acls_path($apiName) {
        $acls = include "$this->configDir/$apiName/{$this->configFiles['data_api_acls']}";
        HttpResp::json_out(200,$acls['path'] ?? []);
    }

    private function update_acls_path($apiName) {
        $acls = $this->get_input_data();
        if(!is_array($acls)) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]);
        }
        $oldAcls = include "$this->configDir/$apiName/{$this->configFiles['data_api_acls']}";
        $oldAcls['path'] = [];
        foreach ($acls as $acl) {
            if (!is_array($acl) || !isset($acl['pattern']) || !isset($acl['action'])
                || !in_array($acl['action'],['allow','deny'])  ) {
                HttpResp::json_out(400, ["error" => "Invalid ACL rule"]);
            }
            $oldAcls['path'][] = $acl;
        }
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['data_api_acls']}",$oldAcls);
        $this->get_acls_path($apiName);
    }

    function admin_acls($apiName) {
        $this->authorize_config_update($apiName);
        switch($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                $this->get_admin_acls($apiName);
                break;
            case "PUT":
                $this->update_admin_acls($apiName);
                break;
        }
    }

    private function get_admin_acls($apiName) {
        $adminConfig = include "$this->configDir/$apiName/{$this->configFiles['admin_config']}";
        HttpResp::json_out(200,$adminConfig["acls"] ?? []);
    }

    private function update_admin_acls($apiName) {
        $acls = $this->get_input_data();
        if(!is_array($acls)) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]);
        }
        foreach($acls as $acl) {
            if(!is_array($acl) || !isset($acl['ip']) || !isset($acl['action'])
                    || !in_array($acl['action'],['allow','deny'])
                    || !preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(\/[0-9]{1,2})?$/",$acl['ip'])) {
                HttpResp::json_out(400, ["error" => "Invalid ACL rule {$acl['ip']} {$acl['action']}"]);
            }
        }
        $adminConfig = include "$this->configDir/$apiName/{$this->configFiles['admin_config']}";
        $adminConfig["acls"] = $acls;
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['admin_config']}",$adminConfig);
        $this->get_admin_acls($apiName);
    }

    private function get_admin_secret($apiName) {
        $this->api_exists($apiName);
        $this->authorize_apis_admin();
        $adminConfig = include "$this->configDir/$apiName/{$this->configFiles['admin_config']}";
        HttpResp::json_out(200,["apiKey"=>$adminConfig["secret"]]);
    }

    function admin_secret_reset($apiName) {
        $this->api_exists($apiName);
        $this->authorize_apis_admin();
        $adminConfig = include "$this->configDir/$apiName/{$this->configFiles['admin_config']}";
        $adminConfig["secret"] = bin2hex(openssl_random_pseudo_bytes(32));
        $this->save_config("$this->configDir/$apiName/{$this->configFiles['admin_config']}",$adminConfig);
        $this->get_admin_secret($apiName);
    }

    /**
     * @param $apiName
     * @param $resourceName
     */
    function hooks($apiName,$resourceName=null) {
        $this->authorize_config_update($apiName);
        switch($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                $this->get_hooks($apiName,$resourceName);
                break;
            case "PUT":
                $this->update_hooks($apiName,$resourceName);
                break;
        }
    }

    /**
     * @param $apiName
     * @param $resourceName
     */
    private function get_hooks($apiName,$resourceName=null) {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir")."/$apiName/structure.php");
        $hooks = [];
        if($resourceName){
            if(isset($structure[$resourceName]["hooks"])){
                HttpResp::json_out(200,$structure[$resourceName]["hooks"]);
            }
            else{
                HttpResp::json_out(200,[]);
            }
        }
        else{
            foreach($structure as $resourceName => $resource){
                if(isset($resource["hooks"])){
                    $hooks[$resourceName] = $resource["hooks"];
                }
            }
            HttpResp::json_out(200,$hooks);
        }
    }

    /**
     * @param $apiName
     * @param $resourceName
     */
    private function update_hooks($apiName,$resourceName) {
        $structure = require_once($this->config->item("configs_dir")."/$apiName/structure.php");
        $hooks = $this->get_input_data();
        if(!is_array($hooks)) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]);
        }
        $structure[$resourceName]["hooks"] = [];
        
        foreach($hooks as $hookName => $hooks){
            if(!in_array($hookName,["create","update","delete"],true)){
                HttpResp::json_out(400,["error"=>"Invalid webhook event"]);
            }
            $structure[$resourceName]["hooks"][$hookName] = [];
            if(!is_array($hooks)) {
                HttpResp::json_out(400,["error"=>"Invalid webhook {$hookName} data"]);
            }
            foreach($hooks as $hook){

                if(!isset($hook["url"]) || !filter_var($hook["url"], FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//i", $hook["url"])) {
                    HttpResp::json_out(400,["error"=>"Invalid webhook {$hookName} URL {$hook['url']}"]);
                }

                if(!isset($hook["method"]) || !in_array(strtoupper($hook["method"]),["GET","POST","PUT","DELETE"])) {
                    HttpResp::json_out(400,["error"=>"Invalid webhook {$hookName} method"]);
                }

                $hook["headers"] = $hook["headers"] ?? [];
                if(!is_array($hook["headers"])) {
                    HttpResp::json_out(400,["error"=>"Invalid webhook headers"]);
                }
                foreach($hook["headers"] as $headerName => $headerValue) {
                    if(!is_string($headerName) || !is_string($headerValue)) {
                        HttpResp::json_out(400,["error"=>"Header name and value must be strings"]);
                    }
                    if(!preg_match("/^[a-zA-Z0-9\-]+$/", $headerName)) {
                        HttpResp::json_out(400,["error"=>"Invalid header name format"]);
                    }
                    if(strlen($headerValue) > 1024) {
                        HttpResp::json_out(400,["error"=>"Header value too long"]);
                    }
                }
                
                $hook["body"] = $hook["body"] ?? null;
                $hook["body"] = sanitize_data($hook["body"]);

                $structure[$resourceName]["hooks"][$hookName][] = $hook;
            }
        }

        try {
            $this->save_config($this->config->item("configs_dir")."/$apiName/structure.php",$structure);
        } catch (Exception $e) {
            HttpResp::exception_out($e);
        }
        HttpResp::json_out(200,$structure[$resourceName]["hooks"]);
    }

    private function save_config($fileName,$data,$rights=0666) {
        try {
            $res = file_put_contents($fileName,to_php_code($data,true));
            if($res===false) {
                throw new Exception("Could not save config");
            }
            opcache_invalidate($fileName, true);
            chmod($fileName, $rights);
        } catch (Exception $e) {
            HttpResp::exception_out($e);
        }
    }

    /**
     * @param $apiName
     */
    function patch($apiName) {
        $this->authorize_config_update($apiName);
    }

    /**
     * @param $apiName
     */
    function delete_api($apiName) {
        $this->authorize_config_update($apiName);
        try {
            if($this->input->get("delete_db")=="true"){
                $conn = require_once($this->config->item("configs_dir")."/$apiName/connection.php");
                $db = $this->load->database($conn,true);
                /**
                 * @var CI_DB_forge $dbforge
                 */
                $dbforge = $this->load->dbforge($db, true);
                if(!$dbforge->drop_database($conn['database'])) {
                    $db->close();
                    throw new Exception("Could not drop database {$conn['database']}");
                }
                else {
                    $db->close();
                }
            }
        
            remove_dir_recursive($this->config->item("configs_dir")."/$apiName");
            HttpResp::no_content();
        }
        catch (Exception $exception) {
            HttpResp::exception_out($exception);
        }




    }

    function not_found() {
        HttpResp::not_found(["error"=>"Resource not found"]);
    }

    function home() {
        $resp = [
            "status"=>"ok",
            "endpoints"=>[
                "/apis/{apiName}"=>[
                    [
                        "method"=>"POST",
                        "desc"=>"Create a new API"
                    ]
                ]
            ]
        ];
        HttpResp::json_out(200,$resp);

    }

    /**
     * @param $apiName
     */
    function swagger($apiName)
    {
        $this->api_exists($apiName);
        $this->load->helper("swagger");
        $structure = require "$this->configDir/$apiName/{$this->configFiles['structure']}";
        $openApiSpec = generate_swagger(
            $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["SERVER_NAME"]."/dbapi/apis/$apiName/data",
            $structure,
            "/$apiName",
            "$apiName Spec",
            "$apiName spec",
            "test@user.com",
            );
        HttpResp::json_out(200,$openApiSpec);
    }

    private function get_db_status($apiName) {
        $this->authorize_config_update($apiName);
        $conn = include "$this->configDir/$apiName/{$this->configFiles['connection']}";
        $db = $this->load->database($conn,true);
        $status = $db->query("SELECT 1")->result_array();
        $db->close();
        return $status ? "ok" : "down";
    }

    function get_config_endpoints($apiName) {
        $this->authorize_config_update($apiName);

        $resp = [
            "dbstatus"=>$this->get_db_status($apiName),
            "endpoints"=>[
                "/config"=>[
                    [
                        "method"=>"GET",
                        "desc"=>"Get Config Help"
                    ]
                ],
                "/data"=>[
                    [
                        "method"=>"GET",
                        "desc"=>"Get data help"
                    ]
                ],

            ]
        ];
        HttpResp::json_out(200,$resp);

    }

    function status($apiName) {
        $this->authorize_config_update($apiName);
        $resp = [
            "dbstatus"=>$this->get_db_status($apiName),
            "apistatus"=>"ok"
        ];
        HttpResp::json_out(200,$resp);
    }

    function structure($apiName) {
        $this->api_exists($apiName);
        switch($_SERVER["REQUEST_METHOD"]) {
            case "GET":
                $this->get_structure($apiName);
                break;
            case "POST":
                $this->regen_structure($apiName);
                break;
            case "PATCH":
                $this->patch_structure($apiName);
                break;
            case "PUT":
                $this->replace_structure($apiName);
                break;
        }
    }

    /**
     * @param $conn
     * @param $apiConfigDir
     * @return array
     * @throws Exception
     */
    private function generate_config($conn,$apiConfigDir,$structure=null){
        if(is_null($structure)) {
            $db = @$this->load->database($conn,true);

            $error = $db->error();
            if($error["code"]!==0) {
                throw new Exception($error["message"],$error["code"]);
            }

            $structure = DBWalk::parse($db,$conn['database'])['structure'];
        }

        /**
         * @todo: check later algorithm. Does not work as expected.
         */
        $patchFile =  is_file("$apiConfigDir/patch.php") ? "$apiConfigDir/patch.php" : null;
        if($patchFile) {
            $data = @include $patchFile;
            if(is_array($data)) {
                $structure = smart_array_merge_recursive($structure, $data);
                $this->save_config("$apiConfigDir/patch.php",$data);
                @chmod("$apiConfigDir/patch.php",0666);
            }
        }

        return $structure;

    }

}
