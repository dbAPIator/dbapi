<?php

require_once(APPPATH."third_party/Softaccel/Autoloader.php");
\Softaccel\Autoloader::register();

/**
 * Class ConfigGen
 * @property CI_Loader load
 */
class Setup extends CI_Controller
{
    private $conn = [
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
    private $supportedDbEngines = ["mysqli"];

    function __construct()
    {
        parent::__construct();
        $this->load->config("apiator");
        if(!is_dir(CFG_DIR_BASEPATH)) {
            die("Invalid APIs config dir. Please set correct path in application/config/apiator.php by setting CFG_DIR_BASEPATH\n");
        }
    }

    private static function output_help()
    {
        echo "Syntax: php setup.php [command] [OPTIONS]\n\n";
        echo "Description: Command line DB Apiator administration utility\n\n";
        echo "Commands:\n";
        echo "\tlist: lists existing projects\n";
//        echo "    install [/project_name/db_engine/db_host/db_user/db_pass/db_name]: Installs DbAPI on server by creating config files and\n";
        echo "\tnew [/project_name/db_engine/db_host/db_user/db_pass/db_name]: Creates a REST API for a given database\n";
        echo "\tregen 'projectname': Regenerates existing 'projectname' API\n\n";
        echo "Configuration parameters can be provided directly in the command line\n";
        echo "\t- project_name alfanumeric string with no spaces. Used to uniquely identify the database within the current installation\n";
        echo "\t- db_engine database engine. Currently only mysqli is supported\n";
        echo "\t- db_host IP or hostname of database server. It can also contain the port number in case it differs from default value (eg. localhost:3688)\n" ;
        echo "\t- db_user database username\n";
        echo "\t- db_pass database password\n";
        echo "\t- db_name database name\n\n";
        die();
    }

    private function listprojects()
    {
        if(!is_dir(CFG_DIR_BASEPATH)) {
            throw new Exception("Invalid config directory ".CFG_DIR_BASEPATH);
        }
        $dp = opendir(CFG_DIR_BASEPATH);
        echo "Projects configuration base path:\n\t".CFG_DIR_BASEPATH."\n";
        echo "Existing projects:\n";
        while ($entry=readdir($dp)) {
            if(in_array($entry,[".",".."]))
                continue;
            echo "\t- $entry\n";

        }
    }

    /**
     * @param null $op
     * @param $projName
     */
    function cli($op=null,$project_name=null,$db_engine=null,$db_host=null,$db_user=null,$db_pass=null,$db_name=null)
    {
        print_r(func_get_args());
//        die();
        try {
            switch ($op) {
                case null:
                    $this->output_help();
                    break;
                case "new":
                    $this->saveproject($project_name, $db_engine, $db_host, $db_user, $db_pass, $db_name);
                    break;
                case "regen":
                    $this->regen($project_name);
                    break;
                case "list":
                    $this->listprojects();
                    break;
            }
        }
        catch (Exception $exception) {
            echo "Some error occured:\n\t";
            echo $exception->getMessage()."\n";
        }
    }
    private function regen($project_name)
    {
        $projPath = $this->handle_project_directory($project_name,true);
        $conn = require ($projPath."/connection.php");
//        print_r($conn);
//        die();
//        echo $projPath;
        $this->saveproject($project_name, $conn["dbdriver"], $conn["hostname"], $conn["username"], $conn["password"], $conn["database"], $projPath);
    }

    /**
     * @param $dbdriver
     * @param $hostname
     * @param $username
     * @param $password
     * @param $database
     * @return array
     */
    private function parse_cli4config($dbdriver, $hostname, $username, $password, $database)
    {
        $conn = $this->conn;
        $conn["dbdriver"] = $dbdriver;
        $conn["hostname"] = $hostname;
        $conn["username"] = $username;
        $conn["password"] = $password;
        $conn["database"] = $database;
        return $conn;
    }

    /**
     * @return array
     * @throws Exception
     */
    private function interactive_config()
    {
        $conn = $this->conn;

        $cnt = 0;
        do {
            $projName = readline(($cnt?"Project name cannot be empty. ":"")."Project name (alphanumeric): ");
            $cnt++;
        } while(!$projName);

        $read = readline("Database engine [mysqli]: ");
        $conn["dbdriver"] = $read===""?"mysqli":$read;
        if(!in_array($conn["dbdriver"] ,$this->supportedDbEngines)) {
            throw new Exception("\nError: Database engine '" . $conn["dbdriver"] . "' not supported\n");
        }

        $read = readline("Database host (eg: localhost:3306) [localhost]: ");
        $conn["hostname"] = $read===""?"localhost":$read;

        $conn["username"] = readline("Database username: ");
        $conn["password"] = readline("Database password: ");
        $conn["database"] = readline("Database name: ");

        return [$projName,$conn];
    }

    /**
     * @param null $project_name
     * @param $dbdriver
     * @param $hostname
     * @param $username
     * @param $password
     * @param $database
     * @return array
     * @throws Exception
     */
    private function get_parameters($project_name,$dbdriver, $hostname, $username, $password, $database)
    {
        if(!is_null($dbdriver)) {
            return [ $project_name, $this->parse_cli4config($dbdriver, $hostname, $username, $password, $database)];
        }

        return $this->interactive_config();
    }

    private function handle_project_directory($projectName, $existingProject)
    {
        $projPath = CFG_DIR_BASEPATH."/".$projectName;
        $dirExists = is_dir($projPath);
        if($existingProject && $dirExists) {
            $read = readline("Are you sure you want to override '$projectName'? ([Y]/N):");
            if (!in_array(strtolower($read), ["y", ""])) {
                die("Setup canceled\n");
            }
            return $projPath;
        }

        if($existingProject && !$dirExists) {
            die("Project '$projectName' does not exits.' \n");
        }


        if($dirExists) {
            die("Project '$projectName' already exists. Use option 'regen' to overwrite project. \n");
        }

        echo $projPath;
        if(!mkdir($projPath)) {
            die("Could not create project directory '$projPath'. Please check file permissions");
        }
        chmod($projPath,0777);

        return $projPath;
    }

    /**
     * @param $project_name
     * @param null $db_engine
     * @param null $db_host
     * @param null $db_user
     * @param null $db_pass
     * @param null $db_name
     * @param bool $existing
     * @throws Exception
     */
    private function saveproject($project_name,$db_engine=null,$db_host=null,$db_user=null,$db_pass=null,$db_name=null,$projPath=null)
    {
        global $ciConfigDst;

        // step 1: get project parameters
        list($project_name,$connection) = $this->get_parameters($project_name,$db_engine,$db_host,$db_user,$db_pass,$db_name);


        // step 2: handle project directory
        if(is_null($projPath)) {
            $projPath = $this->handle_project_directory($project_name,false);
        }

        try {
            $this->generate_config($connection, $projPath);
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
        // step 3: generated project files


        echo "\nAPI successfully created and available at:\n\t ".base_url()."v2/$project_name\n\n";
        echo "Project files available at: \n\t$projPath\n\n";
    }
    /**
     * @param $driver
     * @param $hostname
     * @param $username
     * @param $password
     * @param $database
     * @param null $path
     * @param null $helper
     */
    private function generate_config($conn,$path){

        $db = $this->load->database($conn,true);
        $structure = \Softaccel\Apiator\DBApi\DBWalk::parse_mysql($db,$conn['database']);
        $structure = $structure['structure'];

        $path = $path?$path:$_SERVER['PWD'];
        if(is_file("$path/parse_helper.php")) {
            $data = @include "$path/parse_helper.php";
            if(is_array($data)) {
                $structure = smart_array_merge_recursive($structure, $data);
                file_put_contents("$path/parse_helper.php",to_php_code($data,true));
                chmod("$path/parse_helper.php",0666);
            }
        }

        file_put_contents("$path/structure.php",to_php_code($structure,true));
        file_put_contents("$path/connection.php",to_php_code($conn,true));
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
//function to_php_code($data)
//{
//    $str =  preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],json_encode($data,JSON_PRETTY_PRINT)).";";
////    $str = str_replace('"',"'",$str);
//    return $str;
//}
function to_php_code($data,$addBegining=false)
{
//    $json = json_encode($data,JSON_PRETTY_PRINT);
//    print_r($json);
//    $str =  preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],$json).";";
//    $str = str_replace('"',"'",$str);

    return ($addBegining ? "<?php\nreturn " : "").var_export($data,true).";";
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