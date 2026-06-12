<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Config $config
 * @property CI_Loader $load
 * @property CI_Input $input
 * @property Utilities $utilities
 */
trait DbapiMiscTrait
{
    function call_stored_procedure($configName,$procedureName)
    {
        $this->_init($configName);

        if($_SERVER["REQUEST_METHOD"]!=="POST")
            HttpResp::method_not_allowed();


        /**
         * @var \dbAPI\API\
         */
        $procedures = \dbAPI\API\StoredProcedures::init($this->apiDb,$this->apiDm);
        // print_r($this->input->post("args"));

        try {
            $data = $this->get_input_data(null,true);
            $result = $procedures->call($procedureName, $data);
            HttpResp::json_out(200,$result);
        } catch (Exception $e) {
            HttpResp::jsonapi_out(400,JSONApi\Document::from_exception($e->getCode(),$e));
        }


    }



    function test($type=null,$resId=null)
    {
        switch ($type) {
            case "dbins":
                /**
                 * @var CI_DB_driver $db
                 */
                $db = $this->load->database([
                    "dsn"=> "",
                    "hostname"=> "localhost",
                    "username"=> "root",
                    "password"=> "parola123",
                    "database"=> "realy_simple_db",
                    "dbdriver"=> "mysqli",
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
                ],true);

                $db->query("INSERT INTO test values(2,2,2) ON DUPLICATE KEY UPDATE dd=dd");
//                echo $db->affected_rows();
                break;
            default:
                $this->load->view("test");
        }

    }

    /**
     * @param int $module
     * @param int $message
     * @return bool|void
     */
    function debug_log($module=0,$message=0)
    {
        if(!$this->debug)
            return false;
        //print_r(debug_backtrace());
        //error_log(printf("[%s][%s][%s][%s][%d] %s\n",date("h:m:s.u"),__FILE__,__CLASS__,__FUNCTION__,__LINE__,$countSql), 3,$this->apiId);
    }


    /**
     * @param $configName
     * @param $resourceName
     * @param $recId
     * @param $relationName
     */
    private function publishWebhookEvent($configName, $resourceName, $webhooksConfig, $data=null)
    {

        $redisHost = $this->config->item("redis_host") ?? null;
        if(!$redisHost) return;
        
        
        $redisPort = $this->config->item("redis_port") ?? 6379;
        $redisPassword = $this->config->item("redis_password") ?? null;
        $redisUser = $this->config->item("redis_user") ?? null;

        self::log("[$configName/$resourceName] Publishing webhook event with data ".json_encode($data),LOG_INFO);
        try {
            $redis = new Redis();
            if($redisUser && $redisPassword) {
                $redis->auth($redisUser, $redisPassword);
            }
            elseif($redisPassword) {
                $redis->auth($redisPassword);
            }

            $redis->connect($redisHost, $redisPort);

            foreach ($webhooksConfig as $callbackUrl) {
                $message = [
                    'callback_url' => $callbackUrl["url"],
                    'method' => $callbackUrl["method"],
                    'headers' => json_encode($callbackUrl["headers"]),
                    'payload' => strtr($callbackUrl["body"],$data),
                ];

                $redis->xadd($this->config->item("redis_stream"), '*', $message);
                self::log("[$configName/$resourceName] Webhook event published with data ".json_encode($data),LOG_INFO);
                //print_r($message);
            }
        }
        catch (Exception $e) {
            self::log("[$configName/$resourceName] Error publishing webhook event with data ".json_encode($data).": ".$e->getMessage(),LOG_ERR);
        }
    }

    static function log($message,$level=LOG_INFO) {
        switch($level) {
            case LOG_INFO:
                error_log($message,3,"php://stdout");
                break;
            case LOG_ERR:
                error_log($message,3,"php://stderr");
                break;
        }
    }


    /**
     * @param $resourceName
     * @param $recId
     */
}
