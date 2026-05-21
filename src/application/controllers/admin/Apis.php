<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'core/MY_AdminController.php');

class Apis extends MY_AdminController {
    public function __construct() {
        parent::__construct();
    }
    /**
     * Proxy to list APIs   
     * @return void
     */
    public function index() {
        if($_SERVER["REQUEST_METHOD"] == "GET") {
            $this->list();
        } else if($_SERVER["REQUEST_METHOD"] == "POST") {
            $this->create();
        } else {
            HttpResp::method_not_allowed();
        }
    }
    
    /**
     * Remap method to handle API requests
     * @param string $method
     * @return void
     */
    public function _remap($method,$params=[])
    {
        if($method == "index") {
            $this->index();
            exit;
        }

        if($method == "clone") {
            $this->clone(array_shift($params) ?? null);
            exit;
        }

        if($_SERVER["REQUEST_METHOD"] == "GET") {
            $this->get_api($method);
        } else if($_SERVER["REQUEST_METHOD"] == "DELETE") {
            $this->delete_api($method);
        } else if($_SERVER["REQUEST_METHOD"] == "PATCH") {
            $this->update_api($method);
        } else {
            HttpResp::method_not_allowed();
        }
    }
    
    /**
     * List APIs
     * @return void
     */
    public function list() {
        $this->authorize_apis_admin();
        HttpResp::json_out(200,array_values(array_diff(scandir($this->linksDir),[".",".."])));
    }

    public function clone($apiName) {
        $this->authorize_apis_admin();
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        $id = $this->utilities->short_uuid();
        $cloneConfigPath = "$this->configDir/$id";
        recurse_copy($configPath, $cloneConfigPath);
        $cloneName = $apiName."_clone_".time();
        $this->add_api_link($cloneConfigPath, $cloneName);
        $api = include "$cloneConfigPath/meta.php";
        $api["name"] = $cloneName;
        $api["createdAt"] = date("Y-m-d\TH:i:s\Z", time());
        $api["updatedAt"] = date("Y-m-d\TH:i:s\Z", time());
        $api["id"] = $id;
        file_put_contents("$cloneConfigPath/meta.php",to_php_code(json_decode(json_encode($api),true),true));

        HttpResp::json_out(200, $api);
    }

    /**
     * Create API
     * Accepts JSON: { name, description?, contact?: { name?, email?, phone? } }
     * @return void
     */
    public function create() {  
        $this->authorize_apis_admin();
        try {
            $payload = $this->validate_payload();
        } catch (Exception $e) {
            HttpResp::bad_request([
                "error"=>"Invalid payload",
                "details"=>json_decode ($e->getMessage())
            ]);
            return;
        }
        $apiName = $payload->name;
        $configPath = $this->get_api_config_path($apiName);
        if($configPath) {
            HttpResp::json_out(409, $this->errorsCatalog["config"]["api_exists"]);
            return;
        }
        
        $uid = $this->utilities->short_uuid();
        $configPath = "$this->configDir/".$uid;
        $payload->id = $uid;
        $payload->status = "draft";
        $payload->createdAt = date("Y-m-d\TH:i:s\Z", time());
        $payload->updatedAt = date("Y-m-d\TH:i:s\Z", time());
        mkdir($configPath);
        mkdir("$configPath/schema");
        mkdir("$configPath/policies");
        $this->add_api_link($configPath, $payload->name);
        file_put_contents("$configPath/meta.php",to_php_code(json_decode(json_encode($payload),true),true));
        file_put_contents("$configPath/connection.php","<?php\nreturn [];");
        file_put_contents("$configPath/status.php","<?php\nreturn 'draft';");
        file_put_contents("$configPath/hooks.php","<?php\nreturn [];");
        file_put_contents("$configPath/policies/config-network.php","<?php\nreturn ['default_policy' => 'allow'];");
        file_put_contents("$configPath/policies/data-network.php","<?php\nreturn ['default_policy' => 'allow'];");
        file_put_contents("$configPath/policies/auth.php","<?php\nreturn [];");
        
        HttpResp::json_out(201, $payload);
    }

    /**
     * Get API
     * @param string $apiName
     * @return void
     */
    public function get_api($apiName) {
        $this->authorize_apis_admin();
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        $api = include "$configPath/meta.php";
        $api["status"] = include "$configPath/status.php";
        HttpResp::json_out(200, $api);
    }

    /**
     * Delete API
     * @param string $apiName
     * @return void
     */
    public function delete_api($apiName) {  
        $this->authorize_apis_admin();
        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }
        try {
            unlink("$this->linksDir/$apiName");
            remove_dir_recursive($configPath);
            
            HttpResp::no_content();
        }
        catch (Exception $exception) {
            HttpResp::exception_out($exception);
        }
    }

    /**
     * Update API
     * @param string $apiName
     * @return void
     */
    public function update_api($apiName) {
        $this->authorize_apis_admin();

        $configPath = $this->get_api_config_path($apiName);
        if(!$configPath) {
            HttpResp::not_found();
        }

        try {
            $payload = $this->validate_payload();
        } catch (Exception $e) {
            HttpResp::bad_request([
                "error"=>"Invalid payload",
                "details"=>json_decode($e->getMessage())
            ]);
            return;
        }
        
        $api = include "$configPath/meta.php";  
        $rename = false;
        if(isset($payload->name) && $payload->name !== $apiName) {
            if($this->get_api_config_path($payload->name)) {
                HttpResp::bad_request([
                    "error"=>"API name already exists",
                    "details"=>$payload->name
                ]);
                return;
            }
            $rename = true;
        }
        
        $api = smart_array_merge_recursive($api, json_decode(json_encode($payload),true));
        $api["status"] = include "$configPath/status.php";
        $api["updatedAt"] = date("Y-m-d\TH:i:s\Z", time());
        file_put_contents("$configPath/meta.php",to_php_code(json_decode(json_encode($api),true),true));
        if($rename) {
            $this->rename_api($apiName, $payload->name);
        }
        HttpResp::json_out(200, $api);
    }

}

function recurse_copy($src,$dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else { 
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}