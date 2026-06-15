<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Config $config
 * @property CI_Loader $load
 * @property CI_Input $input
 * @property Utilities $utilities
 */
trait DbapiDiscoveryTrait
{
    function dm()
    {
        HttpResp::json_out(200,$this->apiDm->get_dataModel());
    }

    /**
     * @param string $configName
     */
    function base(string $configName) {
        $this->_init($configName);
        HttpResp::json_out(200,["message"=>"'$configName' REST API ready to serve "]);
    }


    /**
     * outputs OpenAPI swagger file in JSON format
     * @param string $configName
     */
    function swagger(string $configName)
    {
        $this->_init($configName);
        $this->load->helper("swagger");

        $spec = read_api_openapi_spec($this->apiConfigDir);
        if ($spec === null) {
            HttpResp::exception_out(new Exception(
                "OpenAPI spec not found. Rebuild the API schema to generate openapi.json.",
                404
            ));
        }
        HttpResp::json_out(200, with_api_openapi_servers_url($spec, $configName));
    }

    /**
     * Parses input data depending on the Content-Type header and returns it. When invalid content type returns null
     * @param array|object|null $input
     * @param bool $no_validation
     * @return array|mixed
     * @throws Exception
     */
    function index()
    {
        HttpResp::json_out(200,[
            "meta"=>[
                "baseUrl"=>"https://".$_SERVER["SERVER_NAME"]."/v2"
            ],
            "jsonapi"=>[
                "version"=>"1.1"
            ]
        ]);
    }

    /**
     * TODO: properly implement this method
     * returns appropriate headers according to respective request and security settings
     */
    function options()
    {
        //echo "options";
        HttpResp::init()
            //->header("Access-Control-Allow-Origin: *")
            ->header("Access-Control-Allow-Methods: PUT, PATCH, POST, GET, OPTIONS, DELETE")
            //->header("Access-Control-Allow-Headers: *")
            ->output();
    }

}
