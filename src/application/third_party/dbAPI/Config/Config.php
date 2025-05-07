<?php


namespace dbAPI\Config;


class Config
{
    private $apiName;
    private $configDir;
    private $errorsCatalog;
    private $input;
    function __construct($apiName,$configDir,$errorsCatalog,$input)
    {
        $this->apiName = $apiName;
        $this->configDir = "$configDir/$apiName";
        $this->errorsCatalog = $errorsCatalog;
        if(!is_dir($this->configDir)) {
            throw new \Exception($this->errorsCatalog["config"]["api_not_found"]);
        }
        $this->input = $input;
        $this->authorize_config_update($apiName);
    }


   
    /**
     * @param $config
     * @return mixed
     * @todo to implement
     */
    private function sanitize_auth_config($config)
    {
        return $config;
    }

    /**
     * @param $apiName
     * @return bool|mixed
     * @throws \dbAPI\API\Exception
     */
    function update_auth($apiName)
    {

        $authFilePath = "$this->configDir/auth.php";

        $authData = $this->sanitize_auth_config($this->get_input_data());

        $oldAuth = @include $authFilePath;
        $oldAuth = is_array($oldAuth) ? $oldAuth : [];

        // @todo: validate auth input
        $defaultAuth = [
            "key" => $oldAuth["key"] ?? md5(random_string() . time()),
            "alg" => 'HS256',
            "validity" => 3600
        ];

        $authData = smart_array_merge_recursive($oldAuth, $authData);
        $authData = smart_array_merge_recursive($defaultAuth, $authData);
        $this->save_config($authFilePath, $authData);
        return $authData;
    }

    /**
     * @param $apiName
     */
    function replace_auth($apiName)
    {
        $this->authorize_config_update($apiName);

        $authFilePath = "$this->configDir/auth.php";
        $authData = $this->sanitize_auth_config($this->get_input_data()) ?? [];

        $defaultAuth = [
            "key" => md5(random_string() . time()),
            "alg" => 'HS256',
            "validity" => 3600
        ];


        $authData = smart_array_merge_recursive($defaultAuth, $authData);
        $this->save_config($authFilePath, $authData);

        if (!$this->input->get("include") || $this->input->get("include") !== "key")
            $authData["key"] = "**********";

        return $authData;
    }


    /**
     * @param $apiName
     */
    function get_auth($apiName)
    {
        $auth = @include "$this->configDir/auth.php";
        if (!$auth) {
            return [];
        }


        if (!$this->input->get("include") || $this->input->get("include") !== "key")
            $auth["key"] = "**********";

        return $auth;

    }


    /**
     * @param $apiName
     */
    function get_security($apiName)
    {
        return @include "$this->configDir/security.php";
    }

    /**
     * Update global security settings
     * @param $apiName
     */
    function update_security($apiName)
    {
        $secFilePath = "$this->configDir/security.php";
        $security = @include $secFilePath;
        $data = $this->get_input_data();
        $security = array_merge($security, $data);

        $this->save_config($secFilePath, $security);
        return $security;
    }


    /**
     * @param $apiName
     */
    function get_clients($apiName)
    {
        $path = "$this->configDir/clients";
        $d = opendir("$path/clients");
        $clients = [];
        while ($e = readdir($d)) {
            if (in_array($e, [".", ".."])) continue;
            $clients[] = explode(".", $e)[0];
        }
        return $clients;
    }

    /**
     * @param $apiName
     * @param $apiKey
     */
    function create_client($apiName)
    {
        $this->authorize_config_update($apiName);

        $apiKey = guidv4();
        $default_config = ["default_policy" => "accept"];
        $config = $this->get_input_data();

        if ($this->input->get("includemyself")) {
            if (!is_array($config["from"]))
                $config["from"] = [@$config["from"]];
            $config["from"][] = $_SERVER["REMOTE_ADDR"];
        }

        $config = array_merge($default_config, $config);

        $this->save_config("$this->configDir/clients/$apiKey.php", $config);

        return ["key" => $apiKey];
    }


    /**
     * @param $apiName
     * @param $apiKey
     */
    function get_client($apiName, $apiKey)
    {
        $this->authorize_config_update($apiName);

        $path = "$this->configDir/clients/$apiKey.php";
        if (!file_exists($path)) {
            throw new \Exception("API Client not found");
        }
        return include $path;
    }

    /**
     * @param $apiName
     * @param $apiKey
     */
    function delete_client($apiName, $apiKey)
    {
        $this->authorize_config_update($apiName);

        $path = "$this->configDir/clients/$apiKey.php";
        if (!file_exists($path)) {
            throw new \Exception("API Client not found");
        }
        if (unlink($path))
            return true;
        else
            throw new \Exception("Could not delete client");
    }

    private  function ip_in_cidr($ip, $cidr)
    {
        if (strpos($cidr, '/') === false) $cidr .= '/32';
        [$subnet, $mask] = explode('/', $cidr);
        return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
    }

  
    private function IP_is_allowed($security)
    {
        // check if IP is allowed
        $acl = $security["config_allow_from"] ?? $security["config"]["from"] ?? [];
        $allowed = false;
        foreach ($acl as $rule) {
            if (!in_array($rule["action"], ["allow", "deny"])) continue;
            if ($this->ip_in_cidr($_SERVER["REMOTE_ADDR"], $rule["ip"])) {
                if ($rule["action"] == "allow") {
                    $allowed = true;
                    break;
                } else {
                    throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["access"]["ip_not_authorized"]);
                }
            }
        }

        if (!$allowed)
            throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["access"]["ip_not_authorized"]);
    }

    private function authorize_config_update($apiName)
    {

        $security = require "$this->configDir/security.php";

        // check secret
        $secret = $this->headers["x-api-key"] ?? $this->headers["X-Api-Key"] ?? $this->input->get("xApiKey") ?? null;
        if (!$secret || $secret !== $security["secret"]) {
            throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["access"]["api_config_secret_not_authorized"]);
        }

        $this->IP_is_allowed($security);

    }

    private function authorize_dbapi_config()
    {
        $secret = $this->headers["x-api-key"] ?? $this->headers["X-Api-Key"] ?? $this->input->get("xApiKey") ?? null;

        // check secret
        if (!$secret || $secret !== $this->config->item("config_api_secret")) {
            throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["access"]["secret_not_authorized"]);
        }

    }

    /**
     * @return mixed
     */
    private function get_input_data()
    {
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? explode(";", $_SERVER["CONTENT_TYPE"])[0] : null;
        switch ($contentType) {
            case "multipart/form-data":
                return $_POST;
                break;
            case "application/json":
                return json_decode($this->input->raw_input_stream, JSON_OBJECT_AS_ARRAY);
                break;
            default:
                throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["input"]["invalid_content_type"]);
        }
    }

    private function api_exists($apiName)
    {
        $path = "$this->configDir/$apiName";
        return is_dir($path);
    }

    /**
     * @param $apiName
     */
    function create_api()
    {

        $this->authorize_dbapi_config();
        $data = $this->get_input_data();

        if (!$data || !is_array($data)) {
            throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["input"]["invalid_input_data"]);
        }
        if (!isset($data["name"])) {
            throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["config"]["db_name_not_provided"]);
        }
        $apiName = $data["name"];
        if ($this->api_exists($apiName)) {
            throw \dbAPI\API\Exception::from_error_catalog($this->errorsCatalog["config"]["api_exists"]);
        }

        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            throw \dbAPI\API\Exception::from_error_catalog(["error" => "Method not allowed"]);
        }


        $conn = [
            "dbdriver" => "mysqli",
            "hostname" => "localhost",
            "username" => "",
            "password" => "",
            "database" => ""
        ];


        if (!isset($data["connection"])) {
            HttpResp::json_out(406, ["error" => "Connection parameters not provided"]);
        }

        $conn = array_merge($conn, $data["connection"]);

        // Check if host is reachable before attempting database connection
        // die("123");
        if (!$this->is_host_port_available($conn['hostname'], 3306)) {
            // http_response_code(500);
            // header('Content-Type: application/json');
            // echo json_encode(["error" => "Cannot connect to database host: {$conn['hostname']} on port 3306"]);
            // die();
            HttpResp::json_out(406, ["error" => "Cannot connect to database host: {$conn['hostname']} on port 3306"]);
        }

        try {
            if (isset($data["create"]) && isset($data["create"]["sql"]) && $data["create"]["sql"]) {

                $tmpConn = $conn;
                unset($tmpConn["database"]);

                $db = $this->load->database($tmpConn, true);


                $dbforge = $this->load->dbforge($db, true);

                $this->load->dbutil($db);

                $dbExists = $this->dbutil->database_exists($conn['database']);

                $dropBeforeCreate = isset($data["create"]["drop_before_create"]) && $data["create"]["drop_before_create"];
                if ($dbExists) {
                    // database exists
                    if ($dropBeforeCreate) {
                        if (@$dbforge->drop_database($conn['database'])) {
                            $dbforge->create_database($conn['database']);
                            $db->close();
                            $db = @$this->load->database($conn, true);
                        } else {
                            throw new Exception("Could not drop database {$conn['database']}");
                        }
                    } else {
                        throw new Exception("Database already exists. Will not drop it.");
                    }

                } else {
                    $dbforge->create_database($conn['database']);
                    $db->close();
                    $db = @$this->load->database($conn, true);
                }


                $createSql = preg_replace("/CREATE DATABASE.*?;/i", "", json_decode($data["create"]["sql"]));

                $db->trans_start();

                $statements = explode(';', $createSql);
                foreach ($statements as $sql) {
                    // Unescape any escaped strings before executing query
                    $sql = trim($sql);
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
            $path = $this->config->item("configs_dir") . "/$apiName";

            $structure = $this->generate_config($data["connection"], $path);

            if (!is_dir($path) && !@mkdir($path))
                throw new Exception("Could not create config directory {$path}");
        } catch (Exception $exception) {
            //print_r($exception);
            //die("123");
            //HttpResp::json_out(500,["errors"=>[["messagesss"=>$exception->getMessage()]]]);
            HttpResp::exception_out($exception);
        }


        $auth = [];
        if (isset($data["authentication"]) && is_array($data["authentication"])) {
            $auth = $data["authentication"];
            $auth["key"] = md5(random_string() . time());
            $auth["alg"] = 'HS256';
            $auth["validity"] = 3600;
            $auth["allowGuest"] = true;
            $auth["defaultAction"] = "allow";
            $auth["guestRules"] = [];
        }


        $security = [
            "default_policy" => "allow",
            "from" => ["0.0.0.0/0", "::/0"],
            "config_allow_from" => ["0.0.0.0/0", "::/0"],
        ];
        if (isset($data["security"]) && is_array($data["security"])) {
            $security = array_merge($security, $data["security"]);
        }
        $security["secret"] = guidv4();


        mkdir("$path/clients");
        $this->save_config("$path/structure.php", $structure);
        chmod("$path/structure.php", 0600);
        $this->save_config("$path/connection.php", $conn);
        chmod("$path/connection.php", 0600);
        $this->save_config("$path/patch.php", []);
        chmod("$path/patch.php", 0600);
        $this->save_config("$path/auth.php", $auth);
        chmod("$path/auth.php", 0600);
        $this->save_config("$path/security.php", $security);
        chmod("$path/auth.php", 0600);

        HttpResp::json_out(201, ["result" => $security["secret"]]);

    }

    function list_apis()
    {
        $this->authorize_dbapi_config();

        $authFilePath = $this->config->item("configs_dir");
        $dir = @opendir($authFilePath);
        if (!$dir) {
            HttpResp::server_error(["error" => "Invalid configs directory"]);
        }
        $entries = [];
        while ($entry = readdir($dir)) {
            if (in_array($entry, [".", ".."]) || is_file($entry)) continue;
            $entries[] = $entry;
        }
        HttpResp::json_out(200, $entries);
    }

    /**
     * triggers the regeneration of the
     * @param $apiName
     * @throws Exception
     */
    function regen($apiName)
    {
        $this->authorize_config_update($apiName);

        $authFilePath = $this->config->item("configs_dir") . "/$apiName";
        $conn = require "$authFilePath/connection.php";

        $oldStructure = require_once("$authFilePath/structure.php");
        try {
            $newStructure = $this->generate_config($conn, $authFilePath);
        } catch (Exception $exception) {
            HttpResp::exception_out($exception);
        }


        foreach (array_keys($newStructure) as $resourceName) {
            // copy hooks from old structure to new structure
            if (isset($oldStructure[$resourceName]) && isset($oldStructure[$resourceName]["hooks"])) {
                $newStructure[$resourceName]["hooks"] = $oldStructure[$resourceName]["hooks"];
            }
        }

        $this->save_config("$authFilePath/structure.php", $newStructure);

        $this->get_structure($apiName);
    }

    /**
     * @param $apiName
     */
    function get_structure($apiName)
    {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir") . "/$apiName/structure.php");
        //print_r($structure);
        HttpResp::json_out(200, $structure);
    }

    private function validate_connection_config($config)
    {
        return $config;
    }

    /**
     * @param $apiName
     */
    function get_connection($apiName)
    {
        $this->authorize_config_update($apiName);
        $connection = require_once($this->config->item("configs_dir") . "/$apiName/connection.php");
        $connection["password"] = "***********";
        unset($connection["dbdriver"]);
        HttpResp::json_out(200, $connection);
    }

    /**
     * Checks if a host is up and a port is open
     * @param string $host The hostname or IP address
     * @param int $port The port number
     * @param int $timeout Connection timeout in seconds
     * @return bool True if host is up and port is open, false otherwise
     */
    private function is_host_port_available($host, $port, $timeout = 3)
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    function update_connection($apiName)
    {
        $this->authorize_config_update($apiName);
        $connection = require_once($this->config->item("configs_dir") . "/$apiName/connection.php");
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


    function get_endpoints($apiName)
    {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir") . "/$apiName/structure.php");
        //print_r($structure);
        HttpResp::json_out(200, array_keys($structure));
    }

    function get_endpoint_structure($apiName, $endpointName)
    {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir") . "/$apiName/structure.php");
        if (isset($structure[$endpointName]))
            HttpResp::json_out(200, $structure[$endpointName]);
        else
            echo "asdas" && $this->not_found();
    }

    /**
     * @param $apiName
     * @param $endpointName
     * @todo: to implement update_endpoint_structure
     */
    function update_endpoint_structure($apiName, $endpointName)
    {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir") . "/$apiName/structure.php");
        if (isset($structure[$endpointName]))
            HttpResp::json_out(200, $structure[$endpointName]);
        else
            echo "asdas" && $this->not_found();
    }

    /**
     * @param $apiName
     * @param $endpointName
     * @todo  to implement replace_endpoint_structure
     */
    function replace_endpoint_structure($apiName, $endpointName)
    {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir") . "/$apiName/structure.php");
        if (isset($structure[$endpointName]))
            HttpResp::json_out(200, $structure[$endpointName]);
        else
            echo "asdas" && $this->not_found();
    }


    /**
     * @param $apiName
     */
    function replace_structure($apiName)
    {
        $this->authorize_config_update($apiName);

        $new_structure = $this->get_input_data();
        if (is_null($new_structure)) {
            HttpResp::json_out(400, ["error" => "Invalid input data"]);
        }


        $conn = require_once($this->config->item("configs_dir") . "/$apiName/connection.php");
        // get natural structure
        $db_struct = DBWalk::parse($this->load->database($conn, true), $conn['database'])['structure'];
        // compute difference
        $diff = compute_struct_diff($db_struct, $new_structure);
        echo json_encode($diff);

        // save patch file
        $this->save_config($this->config->item("configs_dir") . "/$apiName/patch.php", $diff);

        //$newStruct = smart_array_merge_recursive($db_struct,$diff);

        //save structure
        $this->save_config($this->config->item("configs_dir") . "/$apiName/structure.php", $new_structure);

        HttpResp::json_out(200, $new_structure);
    }

    function patch_structure($apiName)
    {
        $this->authorize_config_update($apiName);

        $data = $this->get_input_data();;
        if (!$data) {
            HttpResp::json_out(400, ["error" => "Invalid input data"]) && die();
        }
        $conn = require_once($this->config->item("configs_dir") . "/$apiName/connection.php");
        $structure = DBWalk::parse($this->load->database($conn, true), $conn['database'])['structure'];
        $patch = @include $this->config->item("configs_dir") . "/$apiName/patch.php";
        $patch = $patch ? $patch : [];
        $newStruct = smart_array_merge_recursive($structure, $patch);
        $newStruct = smart_array_merge_recursive($newStruct, $data);

        // compute difference
        $diff = compute_struct_diff($structure, $newStruct);
        // create patch file
        if (count($diff)) {
            $patchFileName = $this->config->item("configs_dir") . "/$apiName/patch.php";
            $this->save_config($patchFileName, $diff);
        }
        $structFileName = $this->config->item("configs_dir") . "/$apiName/structure.php";
        $newStruct = smart_array_merge_recursive($structure, $diff);
        $this->save_config($structFileName, $newStruct);
        HttpResp::json_out(200, $newStruct);
    }

    function get_hooks($apiName, $resourceName = null)
    {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir") . "/$apiName/structure.php");
        $hooks = [];
        if ($resourceName) {
            if (isset($structure[$resourceName]["hooks"])) {
                HttpResp::json_out(200, $structure[$resourceName]["hooks"]);
            } else {
                HttpResp::json_out(200, []);
            }
        } else {
            foreach ($structure as $resourceName => $resource) {
                if (isset($resource["hooks"])) {
                    $hooks[$resourceName] = $resource["hooks"];
                }
            }
            HttpResp::json_out(200, $hooks);
        }
    }

    function update_hooks($apiName, $resourceName, $hookName)
    {
        $this->authorize_config_update($apiName);
        $structure = require_once($this->config->item("configs_dir") . "/$apiName/structure.php");
        $hooks = $this->get_input_data();
        if (!is_array($hooks)) {
            HttpResp::json_out(400, ["error" => "Invalid input data"]);
        }
        foreach ($hooks as $hookName => $hook) {
            if (!in_array($hookName, ["create", "update", "delete"])) {
                HttpResp::json_out(400, ["error" => "Invalid webhook event"]);
            }
            if (!is_array($hook)) {
                HttpResp::json_out(400, ["error" => "Invalid webhook data"]);
            }
            if (!isset($hook["url"]) || !preg_match("/^https?:\/\/[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)$/", $hook["url"])) {
                HttpResp::json_out(400, ["error" => "Invalid webhook URL"]);
            }

            if (isset($hook["method"])) {
                if (!in_array($hook["method"], ["GET", "POST"])) {
                    HttpResp::json_out(400, ["error" => "Invalid webhook method"]);
                }
            } else {
                $hook["method"] = "POST";
            }

            if (isset($hook["headers"])) {
                if (!is_array($hook["headers"])) {
                    HttpResp::json_out(400, ["error" => "Invalid webhook headers"]);
                }
                foreach ($hook["headers"] as $headerName => $headerValue) {
                    if (!is_string($headerName) || !is_string($headerValue)) {
                        HttpResp::json_out(400, ["error" => "Header name and value must be strings"]);
                    }
                    if (!preg_match("/^[a-zA-Z0-9\-]+$/", $headerName)) {
                        HttpResp::json_out(400, ["error" => "Invalid header name format"]);
                    }
                    if (strlen($headerValue) > 1024) {
                        HttpResp::json_out(400, ["error" => "Header value too long"]);
                    }
                }
            }

            $structure[$resourceName]["hooks"][$hookName] = $hook;
        }
        try {
            $this->save_config($this->config->item("configs_dir") . "/$apiName/structure.php", $structure);
        } catch (Exception $e) {
            HttpResp::exception_out($e);
        }
        HttpResp::json_out(200, $structure[$resourceName]["hooks"]);
    }

    private function save_config($fileName, $data)
    {
        try {
            $res = file_put_contents($fileName, to_php_code($data, true));
            if ($res === false) {
                throw new Exception("Could not save config");
            }
            opcache_invalidate($fileName, true);
        } catch (Exception $e) {
            HttpResp::exception_out($e);
        }
    }

    /**
     * @param $apiName
     */
    function patch($apiName)
    {
        $this->authorize_config_update($apiName);
    }

    /**
     * @param $apiName
     */
    function delete_api($apiName)
    {
        $this->authorize_config_update($apiName);
        try {
            if ($this->input->get("delete_db") == "true") {
                $conn = require_once($this->config->item("configs_dir") . "/$apiName/connection.php");
                $db = $this->load->database($conn, true);
                /**
                 * @var CI_DB_forge $dbforge
                 */
                $dbforge = $this->load->dbforge($db, true);
                if (!$dbforge->drop_database($conn['database'])) {
                    $db->close();
                    throw new Exception("Could not drop database {$conn['database']}");
                } else {
                    $db->close();
                }
            }

            remove_dir_recursive($this->config->item("configs_dir") . "/$apiName");
            HttpResp::no_content();
        } catch (Exception $exception) {
            HttpResp::exception_out($exception);
        }


    }
}