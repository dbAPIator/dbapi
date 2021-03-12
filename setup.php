#!/usr/bin/php
<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
define("BASEPATH",getcwd());

$apiatorCfgTpl = __DIR__ . "/application/config/apiator.single.php";
$apiatorCfgDst = __DIR__ . "/application/config/apiator.php";
$ciConfigTpl = __DIR__ . "/application/config/config.tpl.php";
$ciConfigDst = __DIR__."/application/config/config.php";
$db_engines = ["mysqli"];


if(count($argv)===1) {
    echo "Syntax: php setup.php [command] [OPTIONS]\n\n";
    echo "Description: Command line DB Apiator administration utility\n\n";
    echo "Commands:\n";
    echo "    list: lists existing projects\n";
    echo "    install [/project_name/db_engine/db_host/db_user/db_pass/db_name]: Installs DbAPI on server by creating config files and\n";
    echo "    new /project_name/db_engine/db_host/db_user/db_pass/db_name: Creates a REST API for a given database\n";
    echo "    regen: Regenerates an existing API\n\n";
    echo "newproject Options:\n";
    echo "-c /project_name/db_engine/db_host/db_user/db_pass/db_name\n";
    echo "All configuration parameters are provided directly in the command line\n";
    echo "   - project_name alfanumeric string with no spaces. Used to uniquely identify the database within the current installation\n";
    echo "   - db_engine database engine. Currently only mysqli is supported\n";
    echo "   - db_host IP or hostname of database server. It can also contain the port number in case it differs from default value (eg. localhost:3688)\n" ;
    echo "   - db_user database username\n";
    echo "   - db_pass database password\n";
    echo "   - db_name database name\n\n";
    echo "regen Options:\n";
    die();
}

if(!is_dir(__DIR__."/application"))
    die("Invalid setup script.... Cannot find application directory");
@include_once $apiatorCfgDst;
$apisConfigDir = defined("CFG_DIR_BASEPATH")?CFG_DIR_BASEPATH:getcwd();
//echo CFG_DIR_BASEPATH;
array_shift($argv);
$cmd = array_shift($argv);
try {
    switch ($cmd) {
        case "install":
            install($apisConfigDir, $argv);
            break;
        case "new":
            createproject($apisConfigDir, $argv);
            break;
        case "regen":
            regen($apisConfigDir, array_shift($argv));
            break;
        case "list":
            listProjects($apisConfigDir);
            break;
        default:
            die("invalid command\n\n");
    }
}
catch (Exception $exception) {
    echo $exception->getMessage()."\n";
}


function listProjects($dir)
{
    if(!is_dir($dir))
        throw new Exception("Invalid config directory");
    $dp = opendir($dir);
    $p = [];
    while ($entry=readdir($dp)) {
        if(in_array($entry,[".",".."]))
            continue;
        $p[] = $entry;

    }
    print_r($p);
}

function handle_apis_config_dir_creation($configDir)
{
    // Projects base dir
    $readConfigDir = readline("Configurations base directory [".$configDir."]: ");
    if($readConfigDir!=="")
        $configDir = $readConfigDir;

    if(!is_dir($configDir)) {
        $confirmCreateProjeDir = readline("Directory $configDir does not exist. Do you want to create it? ([Yes]\\Y\\No\\N)");
        $conf = $confirmCreateProjeDir===""?"yes":$confirmCreateProjeDir;
        if(in_array(strtolower($conf),["yes","y"]))
            if(!mkdir($configDir))
                throw new Exception("Could not create $configDir");
            chmod($configDir,0777);
    }
    return realpath($configDir);
}

/**
 * @param $configDir
 */

function handle_main_config_file_creation($configDir)
{
    global $apiatorCfgTpl,$apiatorCfgDst;
    $apiatorCfg = file_get_contents($apiatorCfgTpl);
    if($apiatorCfg===false)
        die("DB Apiator config template not found");

    $apiatorCfg = str_replace("%%conn_dir_path%%",addslashes($configDir),$apiatorCfg);
    if(file_put_contents($apiatorCfgDst,$apiatorCfg)===false)
        die("Could not save $apiatorCfgDst config file");
}

/**
 *
 */
function handle_ci_config_file()
{
    global $ciConfigTpl,$ciConfigDst;
    $ciConfig = file_get_contents($ciConfigTpl);
    if($ciConfig===false)
        die("CI config template not found");

    if(is_file($ciConfigDst)) {
        $cfg = include $ciConfigDst;
    }

    $prompt = "Base URL for DBAPI (optional)";
    if(isset($cfg["base_url"])) {
        $baseUrl = $cfg["base_url"];
        $prompt = "Base URL for DBAPI [".$cfg["base_url"]."]";
    }
    else {
        $baseUrl = null;
    }

    $readBaseUrl = readline($prompt);
    $readBaseUrl = (empty($readBaseUrl) && $baseUrl)?$baseUrl:$readBaseUrl;

    $urlRegex = "/^\b(([\w-]+:\/\/?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|\/)))$/";
    if(preg_match($urlRegex,$readBaseUrl))
        $ciConfig = str_replace("%%base_url%%","'$readBaseUrl'",$ciConfig);
    else
        $ciConfig = str_replace("%%base_url%%","null",$ciConfig);

    if(file_put_contents($ciConfigDst,$ciConfig)===false)
        die("Could not save $ciConfigDst config file");
}

/**
 * - check app path
 * - read existing config
 */
function install($configDir,$argv) {
    echo "DbAPI install wizard. Please fill requested info.\n\n";

    // Step 1: check if exists and if not create projects base directory
    $configDir = handle_apis_config_dir_creation($configDir);

    // Step 2: generate pnpbase configuration file
    handle_main_config_file_creation($configDir);

    // Step 3: generate CodeIgniter configuration file
//    handle_ci_config_file();

    echo "\n";

    $setupConfirm = readline("Do you want to configure a project ([Y]/N)?");
    if(in_array(strtoupper($setupConfirm),["","Y"]))
        createproject($configDir,$argv);
}

function regen($configDir,$projName)
{
    if(!$projName)
        die("Please specify a project name\n");

    $projPath = "$configDir/$projName";
    if(!is_dir($projPath))
        die("Project $projName not found\n");

    $conn = require_once "$projPath/connection.php";
    $args = [
            sprintf("/%s/%s/%s/%s/%s/%s",$projName,$conn["dbdriver"],$conn["hostname"],$conn["username"],$conn["password"],$conn["database"])
    ];
    if(is_file($projPath."/parse_helper.php"))
        $args[] = $projPath."/parse_helper.php";

    createproject($configDir,$args);
}

/**
 * @param $params
 * @return array[$project_name,$command]
 */
function get_parameters($params)
{
    global $db_engines;
    if(isset($params[0])) {
//        $pattern = "/\/([a-z0-9]+)\/(mysqli)\/([0-9a-z\.\-\_]+(\:[0-9]{2,5})?)\/([a-z][a-z0-9\.\_\-]+)\/(.*)\/([a-z][a-z0-9\.\_\-]+)/";
        $re = '/\/(.+)\/(.+)\/(.+)\/(.+)\/(.+)\/(.+)/m';
        if(!preg_match($re, $params[0], $matches))
            die("Invalid configuration parameters. See help.\n\n");

//        $cliCallPath = sprintf("dbapi/ConfigGen/cli/%s/%s/%s/%s/%s",$matches[2],$matches[3],$matches[5],$matches[6],$matches[7]);
//        $httpCallPath = sprintf("dbapi/ConfigGen/cli/%s/%s/%s/%s/%s",urlencode($matches[2]),urlencode($matches[3]),
//            urlencode($matches[5]),urlencode($matches[6]),urlencode($matches[7]));
        $paras = [
                "project"=>$matches[1],
                "engine"=>$matches[2],
                "host"=>$matches[3],
                "user"=>$matches[4],
                "pass"=>$matches[5],
                "dbname"=>$matches[6]
        ];
        return $paras;

//        return [$matches[1],$cliCallPath,$httpCallPath];
    }

    // interactive mode
    $cnt = 0;
    do {
        $projName = readline(($cnt?"Project name cannot be empty. ":"")."Project name (alphanumeric): ");
        $cnt++;
    } while(!$projName);

    $read = readline("Database engine [mysqli]: ");
    $engine = $read===""?"mysqli":$read;
    if(!in_array($engine,$db_engines))
        die("\nError: Database engine '$engine' not supported\n");

    $read = readline("Database host (eg: localhost:3306) [localhost]: ");
    $host = $read===""?"localhost":$read;

    $user = readline("Database username: ");
    $pass = readline("Database password: ");
    $dbname = readline("Database name: ");

    $setupCallPath = sprintf("dbapi/ConfigGen/cli/%s/%s/%s/%s/%s",$engine,$host,$user,$pass,$dbname);

    return [$projName,$setupCallPath];
}

function handle_project_directory($configDir,$projName,$existingProject)
{
    $projPath = $configDir."/".$projName;
    $dirExists = is_dir($projPath);

    if(!$dirExists) {
        if(!mkdir($projPath))
            die("Could not create project directory '$projPath'");
        chmod($projPath,0777);

    }

    if($existingProject) {
        $read = readline("Project $projName already exists. Do you want to overwrite? ([Y]/N):");
        if (!in_array(strtolower($read), ["y", ""])) {
            throw new Exception("Setup canceled\n");
        }
    }


    return $projPath;
}

function generate_project_files($cli_args,$cmd,$projPath)
{

}


function http_gen_config($execPath)
{
    global $errorOutput,$ciConfigDst;
    $errorOutput = 0;
    $cfg = include $ciConfigDst;
    $path = $cfg["base_url"]."/index.php/".$execPath;

    set_error_handler(function($eee,$bbb) {
        global $errorOutput;
        if($eee) {
            $errorOutput = $eee;
        }
    });
    file_get_contents($path);
    restore_error_handler();

    return $errorOutput;
//    print_r("--",$output);
}

function cli_gen_config( $execPath)
{
    $cmd = "php ".__DIR__."/public/index.php $execPath";
//    echo $cmd;

    exec( $cmd,$output,$ret);
    print_r($output);
    return $ret;
}
/**
 * @param $configDir
 * @param $cli_args
 */
function createproject($configDir, $cli_args, $existing=false)
{
    global $ciConfigDst;
    // step 1: get project parameters
//    list($projName,$cliPath,$httpPath) = get_parameters($cli_args);
    $paras = get_parameters($cli_args);

    // step 2: handle project directory
    $projPath = handle_project_directory($configDir,$paras["project"],$existing);

    $execPath = sprintf("dbapi/ConfigGen/cli/%s/%s/%s/%s/%s/%s",$paras["engine"],$paras["host"],
        base64_encode($paras["user"]),base64_encode($paras["pass"]),base64_encode($paras["dbname"]),base64_encode($projPath));

    if(exec_enabled()) {
        $ret = cli_gen_config($execPath);
    }
    else {
        $ret = http_gen_config($execPath);
    }
    // step 3: generated project files

    // print_r($output);
    if($ret===0) {
        $cfg = include $ciConfigDst;
        if(isset($cfg["base_url"]))
            echo "\nAPI successfully created and available at:\n\t {$cfg["base_url"]}/v2/".$paras["project"]."\n\n";
        echo "Project files available at: \n\t$projPath\n\n";
        return;
    }

    // recursive_delete(realpath($projPath));
}

function exec_enabled() {
    $disabled = explode(',', ini_get('disable_functions'));
    return !in_array('exec', $disabled);
}

function recursive_delete($path) {
    if(is_dir($path)) {
        $dp = opendir($path);
        while ($fe=readdir($dp)){
            if(in_array($fe,[".",".."]))
                continue;
            recursive_delete($path."/$fe");
        }
        rmdir($path);
        return;
    }
    unlink($path);
}
