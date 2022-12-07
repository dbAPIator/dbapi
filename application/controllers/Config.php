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
 */
class Config extends CI_Controller {
    public function __construct() {
        parent::__construct();
        header("Content-type: application/json");
        $this->config->load("apiator");
        $this->load->helper("string");
    }

    /**
     * check if API is defined by
     * @param $configName
     * @return bool
     */
    private function project_exists($configName, $return=false) {
        $apiDir = $this->config->item("configs_dir")."/$configName";
        if(!is_dir($apiDir)) {
            if(!$return) {
                HttpResp::not_found(["error"=>"API  $configName not found"]);
            }
            return false;
        }
        return true;
    }

    /**
     * @param $configName
     */
    function update_auth($configName) {
        $this->project_exists($configName);

        $authFilePath = $this->config->item("configs_dir")."/$configName/auth.php";
        $authData = json_decode($this->input->raw_input_stream,JSON_OBJECT_AS_ARRAY);
        if(!count($authData)) {
            file_put_contents($authFilePath,to_php_code([],true));
            HttpResp::json_out([]);
            return;
        }

        $oldAuth = @include $authFilePath;
        $oldAuth = is_array($oldAuth) ? $oldAuth : [];

        // @todo: validate auth input
        $defaultAuth = [
            "key" => $oldAuth["key"] ? $oldAuth["key"] : md5(random_string().time()),
            "alg" => 'HS256',
            "validity" => 3600,
            "allowGuest" => true,
            "defaultAction" => "accept",
            "guestRules" => []
        ];

        $oldAuth = smart_array_merge_recursive($defaultAuth,$oldAuth);

        $authData = smart_array_merge_recursive($oldAuth,$authData);
        file_put_contents($authFilePath,to_php_code($authData,true));

        if(!$this->input->get("include") || $this->input->get("include")!=="key")
            $authData["key"] = "**********";

        HttpResp::json_out(200,$authData);
    }

    /**
     * @param $configName
     */
    function replace_auth($configName) {
        $this->project_exists($configName);

        $authFilePath = $this->config->item("configs_dir")."/$configName/auth.php";
        $authData = json_decode($this->input->raw_input_stream,JSON_OBJECT_AS_ARRAY);
        if(!count($authData)) {
            file_put_contents($authFilePath,to_php_code([],true));
            HttpResp::json_out([]);
            return;
        }


        // @todo: validate auth input
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
     * @param $configName
     */
    function get_auth($configName) {
        $authFilePath = $this->config->item("configs_dir")."/$configName";
        $auth = @include $authFilePath."/auth.php";
        if(!$auth) {
            HttpResp::json_out(200,[]) && die();
        }


        if(!$this->input->get("include") || $this->input->get("include")!=="key")
            $auth["key"] = "**********";

        HttpResp::json_out(200,$auth);

    }


    /**
     * @param $configName
     */
    function get_security($configName) {
        $this->project_exists($configName);
        $path = $this->config->item("configs_dir")."/$configName";
        $security = @include "$path/security.php";
        HttpResp::json_out(200,$security);
    }

    /**
     * Update global security settings
     * @param $configName
     */
    function update_security($configName) {
        $this->project_exists($configName);
        $secFilePath = $this->config->item("configs_dir")."/$configName/security.php";
        $security = @include $secFilePath;
        $data = json_decode($this->input->raw_input_stream,JSON_OBJECT_AS_ARRAY);
        $security = array_merge($security,$data);
        file_put_contents($secFilePath,to_php_code($security,true));
        HttpResp::json_out(200,$security);
    }


    /**
     * @param $configName
     */
    function get_clients($configName) {
        $this->project_exists($configName);
        $path = $this->config->item("configs_dir")."/$configName";
        $d = opendir("$path/clients");
        $clients = [];
        while ($e=readdir($d)) {
            if(in_array($e,[".",".."])) continue;
            $clients[] = explode(".",$e)[0];
        }
        HttpResp::json_out(200,$clients);
    }

     /**
     * @param $configName
     * @param $apiKey
     */
    function create_client($configName) {
        $this->project_exists($configName);
        $path = $this->config->item("configs_dir")."/$configName";
        $apiKey = guidv4();
        $fn = "$path/clients/$apiKey.php";
        $default_config = ["default_policy"=>"accept"];
        $config = json_decode($this->input->raw_input_stream,JSON_OBJECT_AS_ARRAY);

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
     * @param $configName
     * @param $apiKey
     */
    function get_client($configName,$apiKey) {
        $this->project_exists($configName);
        $path = $this->config->item("configs_dir")."/$configName/clients/$apiKey.php";
        if(!file_exists($path)) {
            HttpResp::not_found(["error"=>"API Key not found"]);
        }
        HttpResp::json_out(200,include $path);
    }

    /**
     * @param $configName
     * @param $apiKey
     */
    function delete_client($configName,$apiKey) {
        $this->project_exists($configName);
        $path = $this->config->item("configs_dir")."/$configName/clients/$apiKey.php";
        if(!file_exists($path)) {
            HttpResp::not_found(["error"=>"API Key not found"]);
        }
        if(unlink($path))
            HttpResp::no_content();
        else
            HttpResp::server_error(["error"=>"Could not delete client"]);
    }
    /**
     * @param $configName
     */
    function create_api() {
        $data = json_decode($this->input->raw_input_stream,JSON_OBJECT_AS_ARRAY);
        if(!$data || !is_array($data)) {
            HttpResp::bad_request(["error"=>"Invalid input data"]);
        }
        if(!isset($data["name"])) {
            HttpResp::bad_request(["error"=>"No API name provided"]);
        }
        $configName = $data["name"];
        if($this->project_exists($configName,true)) {
            HttpResp::json_out(409,["error"=>"Project  $configName already exists"]);
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

        $path = $this->config->item("configs_dir")."/$configName";

        try {
            $structure = $this->generate_config($data["connection"], $path);
            if(!is_dir($path))
                @mkdir($path);
        }
        catch (Exception $exception){
//            print_r($exception);
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
            $auth["defaultAction"] = "accept";
            $auth["guestRules"] = [];
        }



        $security = [
            "default_policy"=>"accept",
            "from"=>["0.0.0.0/0","::/0"],

        ];
        if(isset($data["security"]) && is_array($data["security"])) {
            $security = array_merge($security,$data["security"]);
        }



            mkdir("$path/clients");
        file_put_contents("$path/structure.php",to_php_code($structure,true));
        chmod("$path/structure.php",0600);
        file_put_contents("$path/connection.php",to_php_code($data["connection"],true));
        chmod("$path/connection.php",0600);
        file_put_contents("$path/patch.php",to_php_code([],true));
        chmod("$path/patch.php",0600);
        file_put_contents("$path/auth.php",to_php_code($auth,true));
        chmod("$path/auth.php",0600);
        file_put_contents("$path/security.php",to_php_code($security,true));
        chmod("$path/auth.php",0600);

        HttpResp::json_out(200,["result"=>guidv4()]);

    }

    function list_apis() {
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
     * @param $configName
     * @throws Exception
     */
    function regen($configName) {
        $this->project_exists($configName);

        $authFilePath = $this->config->item("configs_dir")."/$configName";
        $conn = require  "$authFilePath/connection.php";
        $structure = $this->generate_config($conn,$authFilePath);
        file_put_contents("$authFilePath/structure.php",to_php_code($structure,true));
    }

    /**
     * @param $configName
     */
    function get_structure($configName) {
        $this->project_exists($configName);
        $structure = require_once($this->config->item("configs_dir")."/$configName/structure.php");
        //print_r($structure);
        HttpResp::json_out(200,$structure);
    }

    /**
     * @param $configName
     */
    function replace_structure($configName) {
        $this->project_exists($configName);
        $data = $this->input->raw_input_stream;
        $data = json_decode($data,JSON_OBJECT_AS_ARRAY);
        if(!$data) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]) && die();
        }

        $conn = require_once($this->config->item("configs_dir")."/$configName/connection.php");
        // get natural structure
        $structure = DBWalk::parse_mysql($this->load->database($conn,true),$conn['database'])['structure'];
        // compute difference
        $diff = diff_arr($structure,$data);

        // save patch file
        $patchFileName = $this->config->item("configs_dir")."/$configName/patch.php";
        file_put_contents($patchFileName,to_php_code($diff,true));

        $newStruct = smart_array_merge_recursive($structure,$diff);

        //save structure
        $structFileName = $this->config->item("configs_dir")."/$configName/structure.php";
        file_put_contents($structFileName,to_php_code($newStruct,true));




        HttpResp::json_out(200,$newStruct);
    }

    function patch_structure($configName) {
        $this->project_exists($configName);

        $data = $this->input->raw_input_stream;
        $data = json_decode($data,JSON_OBJECT_AS_ARRAY);
        if(!$data) {
            HttpResp::json_out(400,["error"=>"Invalid input data"]) && die();
        }
        $conn = require_once($this->config->item("configs_dir")."/$configName/connection.php");
        $structure = DBWalk::parse_mysql($this->load->database($conn,true),$conn['database'])['structure'];
        $patch = @include $this->config->item("configs_dir")."/$configName/patch.php";
        $patch = $patch ? $patch : [];
        $newStruct = smart_array_merge_recursive($structure,$patch);
        $newStruct = smart_array_merge_recursive($newStruct,$data);

        // compute difference
        $diff = diff_arr($structure,$newStruct);
        // create patch file
        if(count($diff)) {
            $patchFileName = $this->config->item("configs_dir")."/$configName/patch.php";
            file_put_contents($patchFileName,to_php_code($diff,true));
        }
        $structFileName = $this->config->item("configs_dir")."/$configName/structure.php";
        $newStruct = smart_array_merge_recursive($structure,$diff);
        file_put_contents($structFileName,to_php_code($newStruct,true));
        HttpResp::json_out(200,$newStruct);


    }


    /**
     * @param $configName
     */
    function patch($configName) {
        $this->project_exists($configName);
    }

    /**
     * @param $configName
     */
    function delete_api($configName) {
        $this->project_exists($configName);

        if(remove_dir_recursive($this->config->item("configs_dir")."/$configName"))
            HttpResp::no_content();
        else
            HttpResp::server_error();



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

    function swagger($configName)
    {
        $this->project_exists($configName);
        $this->load->helper("swagger");
        $authFilePath = $this->config->item("configs_dir")."/$configName";
        $structure = require "$authFilePath/structure.php";
        $openApiSpec = generate_swagger(
            $_SERVER["SERVER_NAME"],
            $structure,
            "/$configName",
            "$configName Spec",
            "$configName spec",
            "$configName",
            "test@user.com");
        HttpResp::json_out(200,$openApiSpec);
    }


    function get_config_endpoints($configName) {
        $this->project_exists($configName);

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

    function api_endpoints($configName) {
        $this->project_exists($configName);

        $authFilePath = $this->config->item("configs_dir")."/$configName";
        $structure = require "$authFilePath/structure.php";
        HttpResp::json_out(200,array_keys($structure));
    }


    /**
     * @param $configName
     */
    function get($configName) {
        $this->project_exists($configName);
        $resp = [
            "status"=>"ok",
            "endpoints"=>[
                "/regen" => [
                    [
                        "method"=>"GET",
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
                file_put_contents("$authFilePath/patch.php",to_php_code($data,true));
                chmod("$authFilePath/patch.php",0666);
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

function diff_arr($arr1,$arr2) {
    if(is_array($arr1) xor is_array($arr2)) {
        return $arr2;
    }
    if(!is_array($arr2)) {
        return $arr2;
    }
//    print_r($arr1);
//    die();
    $diff = [];
    foreach ($arr2 as $key=>$val) {
        if(!isset($arr1[$key])) {
            $diff[$key] = $val;
            continue;
        }
        if($val==$arr1[$key]) {
            continue;
        }
        $diff[$key] = diff_arr($arr1[$key],$val);
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
        unlink($fsEntry);
        return;
    }

    $dir = opendir($fsEntry);
    while ($entry = readdir($dir)) {
        if(in_array($entry,[".",".."])) continue;
        remove_dir_recursive($fsEntry."/".$entry);
    }
    closedir($dir);
    rmdir($fsEntry);

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
