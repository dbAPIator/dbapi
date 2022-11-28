<?php


require_once(APPPATH."third_party/Softaccel/Autoloader.php");
\Softaccel\Autoloader::register();
use Softaccel\Apiator\DBApi\DBWalk;

class Config extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->config->load("apiator");
    }

    /**
     * @param $configName
     * @return bool
     */
    private function project_exists($configName, $return=false) {
        if(!is_dir($this->config->item("configs_dir")."/$configName")) {
            http_response_code(404);
            if(!$return) die("Not found");
            return false;
        }
        return true;
    }

    /**
     * @param $configName
     */
    function create($configName) {
        if($this->project_exists($configName,true) && !$this->input->get("regen")) {
            http_response_code(409);
            die("Project $configName already exists");
        }

        if($_SERVER["REQUEST_METHOD"]!=="POST") {
            http_response_code(405);
            die("Method not allowed");
        }

        $conn = [
            "dbdriver" => "mysqli",
            "hostname" => "localhost",
            "username" => "",
            "password" => "",
            "database" => ""
        ];
        $conn = array_merge($conn,$this->input->post());
        $path = $this->config->item("configs_dir")."/$configName";
        if(!is_dir($path))
            mkdir($path);
        $this->generate_config($conn,$path);

    }

    /**
     * @param $configName
     */
    function regen($configName) {
        $this->project_exists($configName);
    }

    /**
     * @param $configName
     */
    function get_structure($configName) {
        $this->project_exists($configName);
        header("Content-type: application/json");
        $structure = require_once($this->config->item("configs_dir")."/$configName/structure.php");
        //print_r($structure);
        die(json_encode($structure));
    }

    /**
     * @param $configName
     */
    function replace_structure($configName) {
        $this->project_exists($configName);
        $data = $this->input->raw_input_stream;
        $data = json_decode($data,JSON_OBJECT_AS_ARRAY);
        if(!$data) {
            http_response_code(400);
            die("Invalid input data");
        }

        header("Content-type: application/json");
        $conn = require_once($this->config->item("configs_dir")."/$configName/connection.php");
        $structure = DBWalk::parse_mysql($this->load->database($conn,true),$conn['database'])['structure'];

//        print_r($data);
        $diff = diff_arr($structure,$data);
        if(count($diff)) {
            $patchFileName = $this->config->item("configs_dir")."/$configName/patch.php";
            file_put_contents($patchFileName,"<?php\nreturn ".to_php_code($diff));
        }
        $structFileName = $this->config->item("configs_dir")."/$configName/structure.php";
        file_put_contents($structFileName,"<?php\nreturn ".to_php_code(smart_array_merge_recursive($structure,$diff)));
        die(json_encode($structure, JSON_UNESCAPED_SLASHES));
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
    function delete($configName) {
        $this->project_exists($configName);
        if($_SERVER["REQUEST_METHOD"]!=="DELETE") {
            http_response_code(405);
            die("Method not allowed");
        }
        echo remove_dir_recursive($this->config->item("configs_dir")."/$configName");
        http_response_code(200);
    }

    /**
     * @param $configName
     */
    function get($configName) {
        $this->project_exists($configName);
    }




    /**
     * @param $conn
     * @param $path
     */
    private function generate_config($conn,$path,$structure=null){
        if(is_null($structure)) {
            $structure = DBWalk::parse_mysql($this->load->database($conn,true),$conn['database'])['structure'];
        }

        $path = $path?$path:$_SERVER['PWD'];
        $patchFile =  is_file("$path/patch.php") ? "$path/patch.php" :
            (is_file("$path/parse_helper.php") ? "$path/parse_helper.php" : null);

        if($patchFile) {
            $data = @include $patchFile;
            if(is_array($data)) {
                $structure = smart_array_merge_recursive($structure, $data);
                file_put_contents("$path/patch.php","<?php\nreturn ".to_php_code($data));
                chmod("$path/patch.php",0666);
            }
        }

        file_put_contents("$path/structure.php","<?php\nreturn ".to_php_code($structure));
        file_put_contents("$path/connection.php","<?php\nreturn ".to_php_code($conn));
        chmod("$path/structure.php",0666);
        chmod("$path/connection.php",0666);
    }

}

/**
 * @param $data
 * @return string
 */
function to_php_code($data)
{
    $str =  preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],json_encode($data,JSON_PRETTY_PRINT)).";";
//    $str = str_replace('"',"'",$str);
    return $str;
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