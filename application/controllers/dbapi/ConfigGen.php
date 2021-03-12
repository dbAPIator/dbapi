<?php

require_once(APPPATH."third_party/Softaccel/Autoloader.php");
\Softaccel\Autoloader::register();

/**
 * Class ConfigGen
 * @property CI_Loader load
 */
class ConfigGen extends CI_Controller
{
    private static $conn = [
        "dsn"=> "",
        "hostname"=> null,
        "username"=> null,
        "password"=> null,
        "database"=> null,
        "dbdriver"=> null,
        "dbprefix"=> "",
        "pconnect"=> false,
        "db_debug"=> true,
        "cache_on"=> false,
        "cachedir"=> "",
        "char_set"=> "utf8",
        "dbcollat"=> "utf8_general_ci",
        "swap_pre"=> "",
        "encrypt"=> false,
        "compress"=> false,
        "stricton"=> false,
        "failover"=> [],
        "save_queries"=> true
    ];

    /**
     * @param $driver
     * @param $hostname
     * @param $username
     * @param $password
     * @param $database
     * @param null $path
     * @param null $helper
     */
    function cli($driver,$hostname,$username,$password,$database,$path=null,$helper=null){
        if($path) {
            $path = base64_decode($path);
        }
        $username = base64_decode($username);
        $password = base64_decode($password);
        $database = base64_decode($database);
//        $username = base64_decode($username);
        $conn = self::$conn;
        $conn["dbdriver"] = $driver;
        $conn["hostname"] = $hostname;
        $conn["username"] = $username;
        $conn["password"] = $password;
        $conn["database"] = $database;

        $db = $this->load->database($conn,true);
        $structure = \Softaccel\Apiator\DBApi\DBWalk::parse_mysql($db,$conn['database']);
        $structure = $structure['structure'];

        $path = $path?$path:$_SERVER['PWD'];
        $helper = urldecode($helper);
        if(is_file($helper)) {
            $data = @include $helper;
            if(is_array($data)) {
                $structure = smart_array_merge_recursive($structure, $data);
                file_put_contents("$path/parse_helper.php","<?php\nreturn ".to_php_code($data));
                chmod("$path/parse_helper.php",0666);
            }
        }

        file_put_contents("$path/structure.php","<?php\nreturn ".to_php_code($structure));
        file_put_contents("$path/connection.php","<?php\nreturn ".to_php_code($conn));
        chmod("$path/structure.php",0666);
        chmod("$path/connection.php",0666);
    }


    /**
     *
     */
//    function mysql() {
//        $conn = array_merge(self::$conn,$_POST);
//
//        $conn["dbdriver"] = 'mysqli';
//        $db = $this->load->database($conn,true);
//
//        $structure = \Softaccel\Apiator\DBApi\DBWalk::parse_mysql($db,$_POST['database']);
//        $data['structure'] = "<?php\nreturn ".preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],json_encode($structure['structure'],JSON_PRETTY_PRINT)).";";
//        $data['connection'] = "<?php\nreturn ".preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],json_encode($conn,JSON_PRETTY_PRINT)).";";
//        $this->load->view("conf_output",$data);
//    }

    function index() {
        $this->load->view("dbgen");
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