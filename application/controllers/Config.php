<?php

require_once(APPPATH."libraries/HttpResp.php");
require_once (BASEPATH."/../vendor/autoload.php");
require_once(APPPATH."third_party/Softaccel/Autoloader.php");
\Softaccel\Autoloader::register();
use Softaccel\Apiator\DBApi\DBWalk;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * @todo: authenticate/limit request
 */
/**
 * Class Config
 * @property CI_Loader load
 * @property CI_Input input
 * @property CI_Config config
 * @property Util util
 */
class Config extends CI_Controller {
    /**
     * @var array|false
     */
    private $headers;

    public function __construct() {
        parent::__construct();
        header("Content-type: application/json");
        $this->config->load("dbapiator");
        $this->load->library("Utilities");
        $this->load->helper("string");
        $this->headers = getallheaders();

    }


    /**
     * check if API exists and if the client is authorized
     * @param $apiName
     * @return bool
     */
    private function api_exists($apiName, $return=false) {
        $apiDir = $this->config->item("configs_dir")."/$apiName";
        if(!is_dir($apiDir)) {
            if(!$return) {
                HttpResp::not_found(["error"=>"API  $apiName not found"]);
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

        $authFilePath = $this->config->item("configs_dir")."/$apiName/auth.php";

        $authData = $this->sanitize_auth_config($this->get_input_data());

        $oldAuth = @include $authFilePath;
        $oldAuth = is_array($oldAuth) ? $oldAuth : [];

        // @todo: validate auth input
        $defaultAuth = [
            "key" => $oldAuth["key"] ?? md5(random_string().time()),
            "alg" => 'HS256',
            "validity" => 3600
        ];

        $authData = smart_array_merge_recursive($oldAuth,$authData);
        $authData = smart_array_merge_recursive($defaultAuth,$authData);
        file_put_contents($authFilePath,to_php_code($authData,true));

        HttpResp::json_out(200,$authData);
    }

    /**
     * @param $apiName
     */
    function replace_auth($apiName) {
        $this->authorize_config_update($apiName);

        $authFilePath = $this->config->item("configs_dir")."/$apiName/auth.php";
        $authData = $this->sanitize_auth_config($this->get_input_data()) ?? [];

        $defaultAuth = [
            "key" => md5(random_string().time()),
            "alg" => 'HS256',
            "validity" => 3600
        ];


        $authData = smart_array_merge_recursive($defaultAuth,$authData);
        file_put_contents($authFilePath,to_php_code($authData,true));

        if(!$this->input->get("include") || $this->input->get("include")!=="key")
            $authData["key"] = "**********";

        HttpResp::json_out(200,$authData);
    }


    /**
     * @param $apiName
     */
    function get_auth($apiName) {
        $this->authorize_config_update($apiName);

        $authFilePath = $this->config->item("configs_dir")."/$apiName";
        $auth = @include $authFilePath."/auth.php";
        if(!$auth) {
            HttpResp::json_out(200,[]) && die();
        }


        if(!$this->input->get("include") || $this->input->get("include")!=="key")
            $auth["key"] = "**********";

        HttpResp::json_out(200,$auth);

    }


    /**
     * @param $apiName
     */
    function get_security($apiName) {
        $this->authorize_config_update($apiName);

        $path = $this->config->item("configs_dir")."/$apiName";
        $security = @include "$path/security.php";
        HttpResp::json_out(200,$security);
    }

    /**
     * Update global security settings
     * @param $apiName
     */
    function update_security($apiName) {
        $this->authorize_config_update($apiName);

        $secFilePath = $this->config->item("configs_dir")."/$apiName/security.php";
        $security = @include $secFilePath;
        $data = $this->get_input_data();
        $security = array_merge($security,$data);
        file_put_contents($secFilePath,to_php_code($security,true));
        HttpResp::json_out(200,$security);
    }


    /**
     * @param $apiName
     */
    function get_clients($apiName) {
        $this->authorize_config_update($apiName);

        $path = $this->config->item("configs_dir")."/$apiName";
        $d = opendir("$path/clients");
        $clients = [];
        while ($e=readdir($d)) {
            if(in_array($e,[".",".."])) continue;
            $clients[] = explode(".",$e)[0];
        }
        HttpResp::json_out(200,$clients);
    }

     /**
     * @param $apiName
     * @param $apiKey
     */
    function create_client($apiName) {
        $this->authorize_config_update($apiName);

        $path = $this->config->item("configs_dir")."/$apiName";
        $apiKey = guidv4();
        $fn = "$path/clients/$apiKey.php";
        $default_config = ["default_policy"=>"accept"];
        $config = $this->get_input_data();

        if($this->input->get("includemyself")) {
            if(!is_array($config["from"]))
                $config["from"] = [@$config["from"]];
            $config["from"][] = $_SERVER["REMOTE_ADDR"];
        }

        $config = array_merge($default_config,$config);

        file_put_contents($fn,to_php_code($config,true));
        HttpResp::json_out(200,["key"=>$apiKey]);
    }


    /**
     * @param $apiName
     * @param $apiKey
     */
    function get_client($apiName,$apiKey) {
        $this->authorize_config_update($apiName);

        $path = $this->config->item("configs_dir")."/$apiName/clients/$apiKey.php";
        if(!file_exists($path)) {
            HttpResp::not_found(["error"=>"API Key not found"]);
        }
        HttpResp::json_out(200,include $path);
    }

    /**
     * @param $apiName
     * @param $apiKey
     */
    function delete_client($apiName,$apiKey) {
        $this->authorize_config_update($apiName);

        $path = $this->config->item("configs_dir")."/$apiName/clients/$apiKey.php";
        if(!file_exists($path)) {
            HttpResp::not_found(["error"=>"API Key not found"]);
        }
        if(unlink($path))
            HttpResp::no_content();
        else
            HttpResp::server_error(["error"=>"Could not delete client"]);
    }

    private function authorize_config_update($apiName) {
        $this->api_exists($apiName);
        
        $authFilePath = $this->config->item("configs_dir")."/$apiName";
        $security = require  "$authFilePath/security.php";

        $secret = isset($this->headers["x-api-key"]) ? $this->headers["x-api-key"] : $this->input->get("xApiKey");

        // check secret
        if(!$secret || $secret!==$security["secret"]) {
            HttpResp::not_authorized("API key not authorized to update API config");
        }

        // check if IP is allowed
        if(!$this->utilities->find_cidr($_SERVER["REMOTE_ADDR"],$security["config_allow_from"])) {
            HttpResp::not_authorized("IP {$_SERVER["REMOTE_ADDR"]} not authorized to update API config");
        }

        return $this->config->item("configs_dir")."/$apiName/auth.php";
    }

    private function authorize_dbapi_config() {
        $secret = $this->headers["x-api-key"] ?? $this->input->get("xApiKey") ?? null;

        // check secret
        if(!$secret || $secret!==$this->config->item("config_api_secret")) {
            HttpResp::not_authorized("API key not authorized to access config API");
        }

        // check if IP is allowed
        if(!$this->utilities->find_cidr($_SERVER["REMOTE_ADDR"],$this->config->item("config_api_allowed_ips"))) {
            HttpResp::not_authorized("IP {$_SERVER["REMOTE_ADDR"]} not authorized to access config API");
        }
    }

    /**
     * @return mixed
     */
    private function get_input_data() {
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? explode(";",$_SERVER["CONTENT_TYPE"])[0] : null;
        switch($contentType) {
            case "multipart/form-data":
                return $_POST;
                break;
            case "application/json":
                return json_decode($this->input->raw_input_stream,JSON_OBJECT_AS_ARRAY);
                break;
            default:
                HttpResp::invalid_request("Invalid content type");
        }
    }

    /**
     * @param $apiName
     */
    function create_api() {
        $this->authorize_dbapi_config();
        $data = $this->get_input_data();

        if(!$data || !is_array($data)) {
            HttpResp::bad_request(["error"=>"Invalid input data"]);
        }
        if(!isset($data["name"])) {
            HttpResp::bad_request(["error"=>"No API name provided"]);
        }
        $apiName = $data["name"];
        if($this->api_exists($apiName,true)) {
            HttpResp::json_out(409,["error"=>"Project  $apiName already exists"]);
        }

        if($_SERVER["REQUEST_METHOD"]!=="POST") {
            HttpResp::json_out(405,["error"=>"Method not allowed"]);
        }

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

        $path = $this->config->item("configs_dir")."/$apiName";

        try {
            $structure = $this->generate_config($data["connection"], $path);
            if(!is_dir($path))
                @mkdir($path);
        }
        catch (Exception $exception){
            set_status_header(500);
            die(json_encode(["error"=>$exception->getMessage()]));
        }

        $auth = [];
        if(isset($data["authentication"]) && is_array($data["authentication"])) {
            $auth = $data["authentication"];
            $auth["key"] = md5(random_string().time());
            $auth["alg"] = 'HS256';
            $auth["validity"] = 3600;
            $auth["allowGuest"] = true;
            $auth["defaultAction"] = "allow";
            $auth["guestRules"] = [];
        }



        $security = [
            "default_policy"=>"allow",
            "from"=>["0.0.0.0/0","::/0"],
            "config_allow_from"=>["0.0.0.0/0","::/0"],
        ];
        if(isset($data["security"]) && is_array($data["security"])) {
            $security = array_merge($security,$data["security"]);
        }
        $security["secret"] = guidv4();



        mkdir("$path/clients");
        file_put_contents("$path/structure.php",to_php_code($structure,true));
        chmod("$path/structure.php",0600);
        file_put_contents("$path/connection.php",to_php_code($conn,true));
        chmod("$path/connection.php",0600);
        file_put_contents("$path/patch.php",to_php_code([],true));
        chmod("$path/patch.php",0600);
        file_put_contents("$path/auth.php",to_php_code($auth,true));
        chmod("$path/auth.php",0600);
        file_put_contents("$path/security.php",to_php_code($security,true));
        chmod("$path/auth.php",0600);

        HttpResp::json_out(200,["result"=>$security["secret"]]);

    }

    function list_apis() {
        $this->authorize_dbapi_config();

        $authFilePath = $this->config->item("configs_dir");
        $dir = @opendir($authFilePath);
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
    function regen($apiName) {
        $this->authorize_config_update($apiName);

        $authFilePath = $this->config->item("configs_dir")."/$apiName";
        $conn = require  "$authFilePath/connection.php";
        $structure = $this->generate_config($conn,$authFilePath);
        if(!$structure) {
            http_response_code(500);
            die("Could not save config");
        }
        file_put_contents("$authFilePath/structure.php",to_php_code($structure,true));
        $this->get_structure($apiName);
    }

    /**
     * @param $apiName
     */
    function get_structure($apiName) {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir")."/$apiName/structure.php");
        //print_r($structure);
        HttpResp::json_out(200,$structure);
    }

    private  function validate_connection_config($config) {
        return $config;
    }
    /**
     * @param $apiName
     */
    function get_connection($apiName) {
        $this->authorize_config_update($apiName);
        $connection = require_once($this->config->item("configs_dir")."/$apiName/connection.php");
        $connection["password"]="***********";
        unset($connection["dbdriver"]);
        HttpResp::json_out(200,$connection);
    }

    function update_connection($apiName) {
        $this->authorize_config_update($apiName);
        $connection = require_once($this->config->item("configs_dir")."/$apiName/connection.php");
        $config = $this->validate_connection_config($this->get_input_data());
        $db = @$this->load->database($config,true);
        if(!$db) {
            HttpResp::bad_request("Invalid database connection parameters");
        }
    }


    function get_endpoints($apiName) {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir")."/$apiName/structure.php");
        //print_r($structure);
        HttpResp::json_out(200,array_keys($structure));
    }

    function get_endpoint_structure($apiName, $endpointName) {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir")."/$apiName/structure.php");
        if(isset($structure[$endpointName]))
            HttpResp::json_out(200,$structure[$endpointName]);
        else
            echo "asdas" && $this->not_found();
    }

    /**
     * @todo: to implement update_endpoint_structure
     * @param $apiName
     * @param $endpointName
     */
    function update_endpoint_structure($apiName, $endpointName) {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir")."/$apiName/structure.php");
        if(isset($structure[$endpointName]))
            HttpResp::json_out(200,$structure[$endpointName]);
        else
            echo "asdas" && $this->not_found();
    }

    /**
     * @todo  to implement replace_endpoint_structure
     * @param $apiName
     * @param $endpointName
     */
    function replace_endpoint_structure($apiName, $endpointName) {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir")."/$apiName/structure.php");
        if(isset($structure[$endpointName]))
            HttpResp::json_out(200,$structure[$endpointName]);
        else
            echo "asdas" && $this->not_found();
    }



    /**
     * @param $apiName
     */
    function replace_structure($apiName) {
        $this->authorize_config_update($apiName);

        $new_structure = $this->get_input_data();
        if(is_null($new_structure)) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]);
        }


        $conn = require_once($this->config->item("configs_dir")."/$apiName/connection.php");
        // get natural structure
        $db_struct = DBWalk::parse_mysql($this->load->database($conn,true),$conn['database'])['structure'];
        // compute difference
        $diff = compute_struct_diff($db_struct,$new_structure);
        echo json_encode($diff);

        // save patch file
        file_put_contents($this->config->item("configs_dir")."/$apiName/patch.php",to_php_code($diff,true));

        //$newStruct = smart_array_merge_recursive($db_struct,$diff);

        //save structure
        file_put_contents($this->config->item("configs_dir")."/$apiName/structure.php",to_php_code($new_structure,true));

        HttpResp::json_out(200,$new_structure);
    }

    function patch_structure($apiName) {
        $this->authorize_config_update($apiName);

        $data = $this->get_input_data();;
        if(!$data) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]) && die();
        }
        $conn = require_once($this->config->item("configs_dir")."/$apiName/connection.php");
        $structure = DBWalk::parse_mysql($this->load->database($conn,true),$conn['database'])['structure'];
        $patch = @include $this->config->item("configs_dir")."/$apiName/patch.php";
        $patch = $patch ? $patch : [];
        $newStruct = smart_array_merge_recursive($structure,$patch);
        $newStruct = smart_array_merge_recursive($newStruct,$data);

        // compute difference
        $diff = compute_struct_diff($structure,$newStruct);
        // create patch file
        if(count($diff)) {
            $patchFileName = $this->config->item("configs_dir")."/$apiName/patch.php";
            file_put_contents($patchFileName,to_php_code($diff,true));
        }
        $structFileName = $this->config->item("configs_dir")."/$apiName/structure.php";
        $newStruct = smart_array_merge_recursive($structure,$diff);
        file_put_contents($structFileName,to_php_code($newStruct,true));
        HttpResp::json_out(200,$newStruct);


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
            remove_dir_recursive($this->config->item("configs_dir")."/$apiName");
            HttpResp::no_content();
        }
        catch (Exception $exception) {
            HttpResp::exception_out($exception);
        }




    }

    function not_found() {
        die(json_encode(["error"=>"Resource not found"]));
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
        $authFilePath = $this->config->item("configs_dir")."/$apiName";
        $structure = require "$authFilePath/structure.php";
        $openApiSpec = generate_swagger(
            $_SERVER["SERVER_NAME"],
            $structure,
            "/$apiName",
            "$apiName Spec",
            "$apiName spec",
            "$apiName",
            "test@user.com");
        HttpResp::json_out(200,$openApiSpec);
    }


    function get_config_endpoints($apiName) {
        $this->authorize_config_update($apiName);

        $resp = [
            "status"=>"ok",
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

    function api_endpoints($apiName) {
        $this->authorize_config_update($apiName);

        $authFilePath = $this->config->item("configs_dir")."/$apiName";
        $structure = require "$authFilePath/structure.php";
        HttpResp::json_out(200,array_keys($structure));
    }


    /**
     * @param $apiName
     */
    function get($apiName) {
        $this->authorize_config_update($apiName);
        $resp = [
            "status"=>"ok",
            "endpoints"=>[
                "/regen" => [
                    [
                        "method"=>"PUT",
                        "desc"=>"Regenerate the configuration"
                    ]
                ],
                "/structure"=>[
                    [
                        "method"=>"GET",
                        "desc"=>"Get API definition"
                    ],
                    [
                        "method"=>"PATCH",
                        "desc"=>"Update API definition"
                    ],
                    [
                        "method"=>"PUT",
                        "desc"=>"Replace API definition"
                    ]
                ]
            ]];
        HttpResp::json_out(200,$resp);
    }


    /**
     * @param $conn
     * @param $authFilePath
     * @return bool|null
     * @throws Exception
     */
    private function generate_config($conn,$authFilePath,$structure=null){
        if(is_null($structure)) {
            $db = @$this->load->database($conn,true);

            $error = $db->error();
//            print_r($error);
            if($error["code"]!==0) {
                throw new Exception($error["message"],$error["code"]);
            }
            $structure = DBWalk::parse_mysql($db,$conn['database'])['structure'];
        }

        $authFilePath = $authFilePath?$authFilePath:$_SERVER['PWD'];
        $patchFile =  is_file("$authFilePath/patch.php") ? "$authFilePath/patch.php" :
            (is_file("$authFilePath/parse_helper.php") ? "$authFilePath/parse_helper.php" : null);

        if($patchFile) {
            $data = @include $patchFile;
            if(is_array($data)) {
                $structure = smart_array_merge_recursive($structure, $data);
                $res = @file_put_contents("$authFilePath/patch.php", to_php_code($data, true));
                if(!$res) {
                    return null;
                }
                @chmod("$authFilePath/patch.php",0666);
            }
        }

        return $structure;

    }

}

/**
 * @param $data
 * @return string
 */
function to_php_code($data,$addBegining=false)
{
//    $json = json_encode($data,JSON_PRETTY_PRINT);
//    print_r($json);
//    $str =  preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],$json).";";
//    $str = str_replace('"',"'",$str);

    return ($addBegining ? "<?php\nreturn " : "").var_export($data,true).";";
}

/**
 * @param $db_struct
 * @param $target_struct
 * @return array
 */
function compute_struct_diff($db_struct, $target_struct) {
    if(is_array($db_struct) xor is_array($target_struct)) {
        return $target_struct;
    }
    if(!is_array($target_struct)) {
        return $target_struct;
    }

    $diff = [];
    foreach ($target_struct as $key=>$val) {
        if(!isset($db_struct[$key])) {
            $diff[$key] = $val;
            continue;
        }
        if($val==$db_struct[$key]) {
            continue;
        }
        $diff[$key] = compute_struct_diff($db_struct[$key],$val);
    }
    return $diff;
}


/**
 * @param $arr1
 * @param $arr2
 * @return bool
 */
function smart_array_merge_recursive($arr1,$arr2) {
    if(!is_array($arr1) || !is_array($arr2) )
        return $arr1;

    foreach ($arr2 as $key=>$val) {
        if(is_null($val)) {
            unset($arr1[$key]);
            continue;
        }
        if(!array_key_exists($key,$arr1)) {
            $arr1[$key] = $val;
            continue;
        }
        if(is_array($val) && is_array($arr1[$key])) {
            $arr1[$key] = smart_array_merge_recursive($arr1[$key],$val);
            continue;
        }
        $arr1[$key] = $val;

    }
    return  $arr1;
}

function remove_dir_recursive($fsEntry) {
    if(!is_dir($fsEntry)) {
        if(!unlink($fsEntry)) throw new Exception("Could not remove $fsEntry");
        return true;
    }

    $dir = opendir($fsEntry);
    while ($entry = readdir($dir)) {
        if(in_array($entry,[".",".."])) continue;
        remove_dir_recursive($fsEntry."/".$entry);
    }
    closedir($dir);
    if(!rmdir($fsEntry)) throw new Exception("Could not remove $fsEntry");
    return true;
}

function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
