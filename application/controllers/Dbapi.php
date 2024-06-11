<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 10/18/18
 * Time: 12:05 PM
 */
require_once(APPPATH."libraries/HttpResp.php");
require_once(APPPATH."third_party/Apiator/Autoloader.php");
require_once (BASEPATH."/../vendor/autoload.php");
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


\Apiator\Autoloader::register();

/**
 * create_multiple_records
 * update_multiple_records
 * delete_multiple_records
 *
 * get_multiple_records
 * get_single_record
 *
 * create_single_record
 * update_single_record
 * delete_single_record
 * get_relationship
 * create_relationship
 * update_relationship
 * delete_relationship
 */

/**
 * Class Dbapi controller: translates API calls into SQL statements
 * @property CI_Config config
 * @property CI_Loader load
 * @property CI_Input input
 */
class Dbapi extends CI_Controller
{
    /**
     * @var CI_DB_pdo_driver
     */
    private $apiDb;

    /**
     * @var \Apiator\DBApi\Datamodel
     */
    private $apiDm;

    private $currentUserId;

    /**
     * @var array
     */
    private $apiSettings;

    /**
     * @var \Apiator\DBApi\Records
     */
    private $recs;
    private $deployment_type;
    /**
     * @var mixed
     */
    private $inputData;
    private $baseUrl;

    private $noLinksInOutput = false;
    /**
     * @var array
     */
    private $JsonApiDocOptions = [
        /**
         * @var bool when true do not include links in output
         */
        "nolinks"=>true,
        /**
         * @var bool
         */
        "minify"=>false
    ];

    /**
     * @var int set recursion level for creating new records
     * set to 0 to disable it
     */
    protected $insertMaxRecursionLevel=5;
    private $debug=false;
    /**
     * @var string
     */
    private $apiConfigDir;
    /**
     * @var array
     */
    private $errors;

    function get_max_insert_recursions()
    {
        return $this->insertMaxRecursionLevel;
    }

    function dummy()
    {
        try {
            $this->createMultipleRecords();
//            $this->updateMultipleRecords();
//            $this->deleteMultipleRecords();
            $this->getMultipleRecords();
            $this->getSingleRecord();
            $this->create_records();
//            $this->updateSingleRecord();
//            $this->deleteSingleRecord();
            $this->getRelationship();
//            $this->getRelated();
            $this->create_relationship();
            $this->update_relationship();
            $this->delete_relationship();
        }
        catch (Exception $e) {

        }
    }


    function __construct ()
    {
        parent::__construct();
        $this->config->load("apiator");

        $this->deployment_type = $this->config->item("deployment_type");

        $this->load->helper("my_utils");

        $this->load->config("errorscatalog");
        $this->errors = $this->config->item("errors");

        // TODO: implement CORS control
        //header("Access-Control-Allow-Origin: *");


        //$this->_init();
    }

    /**
     * do security checks like
     * - check if client IP is allowed
     * - check which client rules match and if req is allowed
     * - authenticate req based on JWT
     */
    private function  security_check() {
        $headers = getallheaders();

        // load default security
        $security = [];
        $data = @include $this->apiConfigDir."/security.php";
        $security = array_merge($security,$data ? $data : []);

        if(isset($headers["x-api-key"])) {
            $security = @include $this->apiConfigDir."/client/".$headers["x-api-key"].".php";
            if(!$security) {
                HttpResp::not_authorized(["error"=>"Invalid API key"]);
            }
        }

        // check if client is allowed (by IP)
        if(!find_cidr($_SERVER["REMOTE_ADDR"],@$security["from"])) {
            throw new Exception("IP ".$_SERVER["REMOTE_ADDR"]." not allowed",401);
        }

        // check rules
        $allow = (isset($security["default_policy"])? $security["default_policy"] : "accept") =="accept";
        foreach ((isset($security["rules"]) ? $security["rules"] : []) as $rule) {
            if(preg_match($rule[0],$_SERVER["REQUEST_METHOD"]) && preg_match($rule[1],$_SERVER["REQUEST_URI"])) {
                $allow = $rule[2] == "accept";
                break;
            }
        }

        if(!$allow) {
            throw new Exception("Not authorized due to access policies",401);
        }

        /*
         * Authenticate request based on JWT tokens
         */
        $auth = @include $this->apiConfigDir."/auth.php";
        if($auth && count($auth) && $security["public"]==false) {
            preg_match("/Bearer (.*$)/i",@$headers["Authorization"],$matches);
            $jwt = count($matches)==2 ? $matches[1] : $this->input->get("token");

            if(is_null($jwt) ) {
                HttpResp::not_authorized(['errors'=>[["message"=>"Public access is denied"]]]);
            }

            $payload = JWT::decode($jwt,new Key($auth["key"],$auth["alg"]));
//                return;
//                print_r($payload);
            if($payload->exp<time()) {
                throw new Exception("Token expired",401);
            }

            $this->currentUserId = $payload->unm;
            // @todo: get UserID to be used later
        }
    }

    /**
     * reads API configuration file, connects to the database and initializes the DataModel (structure)
     * initializes internal objects:
     * - apiDm: DataModel
     * - apiDb: database connection
     */
    private function _init($configName)
    {
        if($this->apiConfigDir)
            return;

//        $this->apiConfigDir = $this->config->item("api_config_dir")($configName);
        $this->apiConfigDir = $this->config->item("configs_dir")."/$configName";

        $this->baseUrl = $this->config->item("base_url")."/v2";
        $this->JsonApiDocOptions["baseUrl"] = $this->baseUrl;

        if(!is_dir($this->apiConfigDir)) {
            // API Not found
            // TODO: log to applog (API not found)
            HttpResp::exception_out(new Exception("Invalid API config dir $this->apiConfigDir",500));
        }

        try{
            $this->security_check();
        }
        catch (Exception $e) {
            HttpResp::exception_out($e);
        }


        // load structure
        $structure = @include($this->apiConfigDir."/structure.php");
        if(!$structure) {
            // Invalid API config
            // TODO: log error: wrong api config
            HttpResp::exception_out(new Exception("Invalid API configuration",404));
        }

        // load connection
        $dbConf = @include($this->apiConfigDir."/connection.php");
        if(!isset($dbConf)) {
            HttpResp::server_error("Invalid database config");
        }


        // load permissions
        // todo: depending on the API client, load the appropriate permissions file
        $apiKey = $this->input->get("api_key")?$this->input->get("api_key"):$this->input->server("HTTP_X_API_KEY");
        if(empty($apiKey)) {
            $profileFIle = "/profiles/default.php";
        }
        else {
            $profileFIle = "/clients/$apiKey.php";
        }

        $permissions = [];
        /** @noinspection PhpIncludeInspection */
//        $permissions = require($apiConfigDir.$profileFIle);
//        if(!isset($permissions)) {
//            HttpResp::server_error("Invalid API permissions");
//        }

        // todo configure settings
        $settings = [];
        // $settings = require($apiConfigDir."/settings.php");
        //if(!isset($settings)) HttpResp::server_error("Invalid API settings");

        $apiCfg = array_merge_recursive($permissions,$structure);

//        error_reporting(0);
//        $dbConf["db_debug"] = FALSE;

        /**
         * @var CI_DB_pdo_driver db
         */
        $db = $this->load->database($dbConf,TRUE);

        if($db->error()["code"]!==0) {
            // TODO log DB connection failed
            HttpResp::service_unavailable(["errors"=>[["message"=>"Could not connect to database"]]]);
        }

        // initializes DM with structure fetched from $apiCfg
        $dm = Apiator\DBApi\Datamodel::init($apiCfg);
        if(!$dm) {
            // TODO log wrong config file
            HttpResp::server_error("Invalid API datamodel");
        }

        $this->apiDb = $db;
        $this->apiDm = $dm;
        $this->apiSettings = $settings;

        // initialize recs
        $this->recs = \Apiator\DBApi\Records::init($this->apiDb,$this->apiDm,$this->apiConfigDir);
        if(!$this->recs) {
            // TODO log unable to initialize records navigator class
            HttpResp::server_error("Invalid API config");
        }
    }

    /**
     * debug function: shows datamodel
     * final
     */
    function dm()
    {
        HttpResp::json_out(200,$this->apiDm->get_dataModel());
    }

    /**
     * @param $configName
     */
    function base($configName) {
        $this->_init($configName);
        HttpResp::json_out(200,["message"=>"'$configName' REST API ready to serve "]);
    }

    /**
     * generates OpenAPI swagger file in JSON format
     * final
     */
    function swagger($configName)
    {

        $this->_init($configName);
        $this->load->config("apiator");
        $this->load->helper("swagger");

        $openApiSpec = generate_swagger(
            $_SERVER["SERVER_NAME"],
            $this->apiDm->get_dataModel(),
            $this->baseUrl."/$configName",
            "$configName Spec",
            "$configName spec",
            "$configName",
            "test@user.com");
        HttpResp::json_out(200,$openApiSpec);
    }

    /**
     * Parses input data depending on the Content-Type header and returns it. When invalid content type returns null
     * @return mixed|null
     * @throws Exception
     */
    private function get_input_data($input=null,$no_validation=false)
    {
        if(!is_null($input)) {
            return  $input;
        }

        if(!isset($_SERVER["CONTENT_TYPE"])) {
            throw new Exception("Missing Content-Type",400);
        }

        $contentType = explode(";",$_SERVER["CONTENT_TYPE"]);
        $inputData = "";

        if(in_array("application/x-www-form-urlencoded",$contentType)) {
            $inputData = json_decode(json_encode($this->input->post()));
        }
        elseif(in_array("application/vnd.api+json",$contentType)) {
            $inputData = json_decode($this->input->raw_input_stream);
        }
        elseif(in_array("application/json",$contentType)) {
            $inputData = json_decode($this->input->raw_input_stream);
        }
        else {
            $inputData = json_decode($this->input->raw_input_stream);
//            throw new Exception("Invalid Content-Type",400);
        }
        if($no_validation) {
            return $inputData;
        }
        validate_body_data($inputData);
        return $inputData;
    }

    /**
     * Update multiple records with a single call
     * @param $configName
     * @param $resourceName
     * @param null $paras
     * @throws Exception
     * @todo to be implemented
     */
    function updateWhere($configName,$resourceName,$paras=null)
    {
        $this->_init($configName);

        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource $resourceName not found",404);

            $inputData = $this->get_input_data();
        }
        catch (Exception $e) {
            HttpResp::json_out(
                $e->getCode(),
                JSONApi\Document::error_doc($this->JsonApiDocOptions, JSONApi\Error::from_exception($e) )->json_data()
            );
        }

        if(count(get_object_vars($inputData->data->attributes))===0) {
            $doc = JSONApi\Document::create(["baseUrl" => ""])->setData(null)->setMeta(JSONApi\Meta::factory(["total"=>0]));
            HttpResp::json_out(
                200,
                $doc->json_data()
            );
        }


        if(is_null($paras))
            $paras = $this->getQueryParameters($resourceName);

        if(!$paras["filter"] && !$paras["where"]) {
            $e = new Exception("No filtering condition provided",400);
            HttpResp::json_out(
                $e->getCode(),
                JSONApi\Document::error_doc($this->JsonApiDocOptions, JSONApi\Error::from_exception($e) )->json_data()
            );
        }

        //echo json_encode($paras,JSON_PRETTY_PRINT);
        $affectedRows = $this->recs->update_attributes_by_filter($resourceName,$inputData->data->attributes,$paras);
        if(!$affectedRows) {
            // todo: update set
            $doc = JSONApi\Document::create(["baseUrl"=>""])->setData(null)->setMeta(JSONApi\Meta::factory(["total"=>0]));
            HttpResp::json_out(
                200,
                $doc->json_data()
            );
        }
        $this->get_records($configName,$resourceName,null,$paras);
    }

    /**
     * Update multiple records of different types with a single call
     * @param $configName
     * @param $resourceName
     * @param null $inputData
     * @throws Exception
     * @todo to be implemented
     */
    function updateMultipleRecords($configName,$resourceName,$inputData=null)
    {
        $this->_init($configName);

        // todo: finish it

        // & validate it
        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource $resourceName not found",404);

            if(!$inputData)
                $inputData = $this->get_input_data();
        }
        catch (Exception $exception) {
            $errors = JSONApi\Error::from_exception($exception);
            HttpResp::json_out(400,
                JSONApi\Document::error_doc($this->JsonApiDocOptions,$errors)->json_data()
            );
        }

        $maxBulkUpdateRecords = $this->config->item("bulk_update_limit");
        $maxBulkUpdateRecords = $maxBulkUpdateRecords?$maxBulkUpdateRecords:10;
        $updateRecords = $inputData->data;

        $ids = [];
        $exceptions = [];
        foreach ($updateRecords as $idx=>$itemData) {
            if(!isset($itemData->id))
                continue;

            try {
                $ids[] = $this->update_single_record($configName,($itemData->type ? $itemData->type  : $resourceName), $itemData->id,(object) ["data"=>$itemData]);
            }
            catch (Exception $e) {
                $exceptions[] = new Exception("Failed to update record ID: $itemData->id: ".$e->getMessage(),$e->getCode());
            }

            $maxBulkUpdateRecords--;
            if($maxBulkUpdateRecords==0) {
                $exceptions[] = new Exception("Maximum number of records to bulk update reached: "
                    .$this->config->item("bulk_update_limit"), 400);
            }
        }

        $_GET["filter"] = "id><".implode(";",$ids);
        $qp = $this->getQueryParameters($resourceName);
        $qp["paging"] = [
            $resourceName => [
                "offset" => 0
            ]
        ];

//        print_r($qp);
        $this->get_records($configName,$resourceName,null,$qp);


        $doc = \JSONApi\Document::create($this->JsonApiDocOptions,[]);

        if(count($exceptions)) {
            foreach ($exceptions as $exception) {
                $doc->addError(\JSONApi\Error::from_exception($exception));
            }
        }
        //print_r($doc);
        HttpResp::jsonapi_out(200,$doc);
    }


    /**
     * @param $configName
     * @param $resourceName
     * @throws Exception
     * @todo to be implemented
     */
    function deleteMultipleRecords($configName, $resourceName)
    {

        $this->_init($configName);


        // check if table exists
        if (!$this->apiDm->resource_exists($resourceName)) {
            HttpResp::not_found();
            exit();
        }

        $paras = $this->getQueryParameters($resourceName);
//        print_r($paras);
        if(!$paras["filter"])
            HttpResp::method_not_allowed();
        try {
            $this->recs->delete_by_where($resourceName,$paras["filter"]);
            HttpResp::no_content();
        }
        catch (Exception $exception) {
            $doc = \JSONApi\Document::not_found($this->JsonApiDocOptions, "Not found", 404);
            HttpResp::json_out(404, $doc->json_data());
        }

    }


    /**
     * update one record
     * @param $configName
     * @param string $resourceName
     * @param string $recId
     * @param null $updateData
     * @return Exception|string
     * @throws Exception
     * @todo validate it
     */
    function update_single_record($configName, $resourceName, $recId, $updateData=null)
    {
        $this->_init($configName);

        $internal = !is_null($updateData);

        // validation section
        try {
            if(!$internal) {
                $updateData = $this->get_input_data();
            }

            $updateData = $updateData->data;

            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource '$resourceName' not found",404);

            if($updateData->type && $resourceName!==$updateData->type) {
                throw new Exception("Object type mismatch; '$updateData->type' instead of '$resourceName' ", 400);
            }

            if("".$recId!=="".@$updateData->id)
                throw new Exception("Record ID mismatch $recId vs $updateData->id",400);

            $resKeyFld = $this->apiDm->get_primary_key($resourceName);
            if(!$resKeyFld)
                throw new Exception("Cannot update by id: resource $resourceName is not configured with a primary key",400);


        }
        catch (Exception $e) {
            if($internal) throw $e;

            HttpResp::json_out($e->getCode(),
                JSONApi\Document::error_doc($this->JsonApiDocOptions, JSONApi\Error::from_exception($e) )->json_data()
            );
        }


        $this->apiDb->trans_begin();

        // perform update
        try {
            $this->recs->update_by_id($resourceName, $recId, $updateData);

            $this->apiDb->trans_commit();


            if($internal)
                return $recId;


            $this->get_records($configName,$resourceName,$recId);

        }
        catch (Exception $exception) {
            $this->apiDb->trans_rollback();
            if($internal) // bubble up error to higher level
                throw $exception;
            HttpResp::jsonapi_out($exception->getCode(),\JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception));
        }

        return $recId;
    }


    /**
     * extracts query parameters and returns them as an array:
     * - include: comma separated list of related resources to include
     * - fields[resourceName]: comma separated list of fields to include from the specified resourceName
     * - filter: filtering criteria @todo write more details
     * - page[offset]: page offset
     * - page[limit]: page size
     * - sort: comma separated list of sorting conditions
     * - onduplicate: parameter describing the behaviour when a duplicate key occurs when inserting (or updating); possible values: ignore, update, error
     * - update: comma separated list of fields to update when onduplicate=update.
     * - _jwt
     * - api_key
     * @return array
     *
     * @
     */
    private function getQueryParameters($resName,$input=null)
    {
        if(is_null($input))
            $input = $this->input;

        $queryParas = $input->get();

        // get include
        if($input->get("include")) {
            $queryParas["includes"] = $input->get("include");
        }

        if($input->get("filter_advanced")) {
            $this->load->helper("where");
            $queryParas["custom_where"] = $input->get("filter_advanced");
            if(!is_array($queryParas["custom_where"])) {
                $queryParas["custom_where"] = [
                    $resName => $queryParas["custom_where"]
                ];
            }
        }

        // get sparse fieldset fields
        if($flds = $input->get("fields")) {
            if(is_array($flds))
                $queryParas["fields"] = $flds;
        }

        $queryParas["paging"] = [];

        // get paging fieldset fields
        $paging = $input->get("page");
        
        if(isset($paging[0])) {
            $paging[$resName]  = $paging[0];
            if(isset($paging[1])) {
                $paging[$resName] = array_merge($paging[$resName],$paging[1]);
            }
        }

        if(is_array($paging))
            $queryParas["paging"] = $paging;

        if(!isset($queryParas["paging"][$resName])) {
            $queryParas["paging"][$resName] = [];
        }

        foreach ($queryParas["paging"] as $key=>$val) {
            if(isset($val["limit"]) && !preg_match("/^\d+$/",$val["limit"])) {
                unset($queryParas["paging"][$key]["limit"]);
            }

            if(isset($val["offset"]) && !preg_match("/^\d+$/",$val["offset"])) {
                unset($queryParas["paging"][$key]["offset"]);
            }
        }

        if(!isset($queryParas["paging"][$resName]))
            $queryParas["paging"][$resName] = ["offset"=>0];

        // get filter
        if($filterStr = $input->get("filter")) {
            $queryParas["filter"] = get_filter($filterStr, $resName);
        }

        // get sort
        if($sortQry = $input->get("sort"))
            $queryParas["order"] = getSort($sortQry,$resName);

        // get onduplicate behaviour and fields to update
        if($ondupe = $input->get("onduplicate")) {
            if(!in_array($ondupe,["update","ignore","error"]))
                $ondupe = "error";
            $queryParas["onduplicate"] = $ondupe;

            $updateFields = $input->get("update");
            if($ondupe=="update" && $updateFields && is_array($updateFields)) {
                $queryParas["update"] = $updateFields;
            }
        }
        return $queryParas;
    }


    /**
     * Process request GET parameters and build the structure to be used for xtracting the data
     * @param string $fqResName fully qualified resource name
     * @param array|null $inputs
     * @return DBAPIRequest
     * @throws \Apiator\DBApi\Exception
     */
    function process_query_parameters(string $fqResName, &$inputs=null) {
        $tmp = explode("/",$fqResName);
        $simpleName = array_pop($tmp);

        $request = new DBAPIRequest($simpleName,$this->config->item("default_page_size"));

        // fetch include
        set_para("include", $inputs,$fqResName, $request);
        if($request->include) {
            $request->include = explode(",",$request->include);
            foreach ($request->include as $key=>$relName) {
                $request->include[$relName] = $this->process_query_parameters("$fqResName/$relName",$inputs);
                unset($request->include[$key]);
            }
        }

        // fetch filter and validate
        set_para("filter",$inputs,$fqResName, $request,$inputs);
        if(is_string($request->filter)) {
            $tmp = explode(",",$request->filter);
            $request->filter = [];
            foreach ($tmp as $expression) {
                $request->add_filter($expression);
            }
        }

        set_para("fields",$inputs,$fqResName, $request,$inputs);
        if(is_string($request->fields)) {
            $request->fields = explode(",",$request->fields);
        }

        if(isset($inputs["page"])) {
            if(isset($inputs["page"][$fqResName])) {
                if(isset($inputs["page"][$fqResName]["offset"])) {
                    if(is_numeric($inputs["page"][$fqResName]["offset"]))
                        $request->offset = $inputs["page"][$fqResName]["offset"];
                    else
                        throw new \Apiator\DBApi\Exception("Invalid page offset value".
                            "for resource $request->resourceName",400);
                }
                if(isset($inputs["page"][$fqResName]["limit"])) {
                    if(is_numeric($inputs["page"][$fqResName]["limit"]))
                        $request->limit = $inputs["page"][$fqResName]["limit"];
                    else
                        throw new \Apiator\DBApi\Exception("Invalid page offset value".
                            "for resource $request->resourceName",400);
                }
            }
        }

        set_para("sort",$inputs,$fqResName, $request,$inputs);
        if(is_string($request->sort)) {
            $request->sort = explode(",",$request->sort);
        }

        set_para("filter_advanced",$inputs,$fqResName, $request,$inputs);

        set_para("update", $inputs,$fqResName, $request,);
        if(is_string($request->update)) {
            $request->update = explode(",",$request->update);
        }

        $request->insertignore = @$inputs["insertignore"] === "true";

        if($request->update) {
            $request->onduplicate = "update";
        }
        else {
            set_para("onduplicate", $inputs, $fqResName,$request, function($value) {return in_array($value,["ignore","error","update"]);});
        }

        return $request;

    }

    /**
     * @param DBAPIRequest $request
     * @param DBAPIRequest $parent
     * @return mixed
     * @throws \Apiator\DBApi\Exception
     */
    function attach_configuration_2_request($request, $parent=null, $relName=null) {

        if(is_null($parent)) {
            // for top level resource check if is valid resource
//            $request->config = $this->apiDm->get_config($request->resourceName);
            if(!$this->apiDm->is_valid_resource($request->resourceName)) {
                throw new \Apiator\DBApi\Exception("Resource not found $request->resourceName",404);
            }
        }
        else {
            // for included resource get valid relationship
            $rel = $this->apiDm->get_relationship($parent->resourceName, $relName);
            $request->resourceName = $rel["table"];
            $request->relSpec = $rel;
        }


        // validate fields
        foreach ($request->fields as $fld) {
            if(!$this->apiDm->is_valid_field($request->resourceName,$fld)) {
                throw new \Apiator\DBApi\Exception("Invalid field $fld of $request->resourceName for sparse field selection",404);
            }
        }

        // if no sparse field selection, get all fields to
        if(!count($request->fields)) {
            $request->fields = $this->apiDm->get_selectable_fields($request->resourceName);
        }


        $request->primaryKey = $this->apiDm->get_primary_key($request->resourceName);
        // add primary key fld if not already present in fields list
        if(!in_array($request->primaryKey,$request->fields)) {
            $request->fields[] = $request->primaryKey;
        }

        foreach ($request->fields as $idx=>$fldName) {
            $request->fieldsIndexes[$fldName] = $idx;
        }

        foreach ($request->sort as $key=>$sortOption) {
            if(!preg_match("/^(\-?)(\w+)$/",$sortOption,$matches)) {
                throw new \Apiator\DBApi\Exception("Invalid ordering expresion $sortOption of $request->resourceName to be used for ordering the results",404);
            }

            if(!$this->apiDm->is_valid_field($request->resourceName,$matches[2])) {
                throw new \Apiator\DBApi\Exception("Invalid field $sortOption of $request->resourceName to be used for ordering the results",404);
            }
            $request->sort[$key] = "`{$matches[2]}`".($matches[1] ? " DESC" : "");
        }

        foreach ($request->include as $relName=>$inclReq) {
            $rel = $this->attach_configuration_2_request($inclReq,$request,$relName);
            if(is_null($rel))
                throw new \Apiator\DBApi\Exception("Relationship $relName of $request->resourceName not found",404);
            else
                $request->include[$relName] = $rel;
        }

        return $request;
    }

    /**
     * @param $resourceName
     * @return DBAPIRequest|mixed
     * @throws \Apiator\DBApi\Exception
     */
    private function get_request($resourceName) {
        $inputs = $this->input->get();

        foreach (["filter","sort","includes","page","onduplicate","update","fields","insertignore"] as $param) {
            if(!isset($inputs[$param]))
                continue;
            if(empty($inputs[$param]))
                continue;
            if(!is_array($inputs[$param])){
                $inputs[$param] = [
                    $resourceName => $inputs[$param]
                ];
            }
        }
        $request = $this->process_query_parameters($resourceName,$inputs);
        $request = $this->attach_configuration_2_request($request);

        return $request;
        // resolve resourceNames to table names (for relations)

    }


    /**
     * get records from table or from view identified by $resourceName
     * @param $configName
     * @param $resourceName
     * @param string|null $recId
     * @param array|null $queryParameters
     * @return RecordSet|Record|null
     * @throws Exception
     */
//    function getRecords($configName, $resourceName, $recId=null, $fields=[],$filter=[],$includes=[],$sort=[],$paging=[],$options=[])
    function get_records($configName, $resourceName, $recId=null, $request=null,$internal=false)
    {
        // init DB connection & load config
        $this->_init($configName);
        // parse input paramas into request

        try {
            if(is_null($request))
                $request = $this->get_request($resourceName);
        }
        catch (Exception $exception) {
            if($internal)
                throw $exception;
            else
                HttpResp::exception_out($exception);
        }

        if(!is_null($recId)) {
            $request->offset = 0;
        }
//        log_message("info",json_encode($request,JSON_PRETTY_PRINT));

//        echo(json_encode($request,JSON_PRETTY_PRINT));

        // validation
        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource 1 s $resourceName not found",404);

            if(!is_null($recId)) {
                $keyFld = $this->apiDm->get_primary_key($resourceName);
                if(is_null($keyFld))
                    throw new Exception("Request not supported. $resourceName does not have a primary key defined", 404);

                $request->resourceId = $recId;
                $request->filter[] = [
                    "left"=>$keyFld,
                    "op"=>"=",
                    "right"=>$recId
                ];
            }
        }

        catch (Exception $exception) {
            if($internal)
                throw $exception;
            else
                HttpResp::json_out($exception->getCode(),\JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }

//        print_r($request);
        // fetch records
        try {
            $result = $this->recs->get_records($request);
//            print_r($result);
        }
        catch (Exception $exception) {
            if($internal)
                throw $exception;
            else
                HttpResp::json_out($exception->getCode(),\JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
            die();
        }

        if(!is_null($recId)) {
            if(!$result->total) {
                if($internal) return null;
                else HttpResp::not_found();


            }
            $result = $result->records[0];
        }

        if($internal) return $result;

        $outputFormat = $this->input->get("format");
        $outputFormat = $outputFormat && in_array($outputFormat,["csv","xls","json"]) ? $outputFormat : "json";


        switch ($outputFormat) {
            case "csv":
                $this->out_csv($resourceName,$recId,$request,$result,$this->input->get("filename"));
                break;
            case "xls":
                $this->out_xls($resourceName,$recId,$request,$result,$this->input->get("filename"));
                break;
            case "json":
                $this->out_jsonapi($result);
        }
    }

    /**
     * @param $resourceName
     * @param $recId
     * @param $queryParameters
     * @param $records
     * @param $totalRecords
     * @throws \Apiator\DBApi\Exception
     */
    function out_csv($resourceName,$recId,$request,$records,$totalRecords,$fileName=null) {

        if(!is_null($recId) && !$totalRecords) {
            HttpResp::not_found();
        }

        function record2csv($record,$fieldsNames,$relationsNames) {
            $rec = [];
            foreach ($fieldsNames as $fldName) {
                $rec[] = $record->attributes->$fldName;
            }

            foreach ($relationsNames as $relName) {
                $rec[] = empty($record->relationships->$relName->data)?null:$record->relationships->$relName->data->id;
            }

            $str =  '"'.implode('","',$rec).'"';
            return $str;
        }
        $out = [];

        // extract fields
        $fieldsNames = [];
        $relationsNames = [];
        foreach ($this->apiDm->get_config($resourceName)["fields"] as $fldName=>$spec) {
            if(isset($spec["foreignKey"])) {
                $relationsNames[] = $fldName;
            }
            else {
                $fieldsNames[] = $fldName;
            }
        }

        $includeTHead = $this->input->get("includetablehead");
        if($includeTHead && $includeTHead=="true") {
            $tmp = $fieldsNames;
            array_splice($tmp,-1,0,$relationsNames);
            $out[] = '"'.implode('","',$tmp).'"';
        }

        $fieldsNames = $request->fields ?   explode(",",$request->fields   )  : $fieldsNames;


        foreach ($records as $record) {
            $out[] = record2csv($record,$fieldsNames,$relationsNames);
        }
        $out = implode("\n",$out);
//        HttpResp::csv_out(200,implode("\n",$out));
        $fileName = $fileName ? $fileName : $resourceName;

        HttpResp::instance()
            ->header('Content-Disposition: attachment; filename="'.$fileName.'.csv"')
            ->header('Content-Type: text/csv"')
            ->response_code(200)
            ->body($out)
            ->output();
    }

    /**
     * @param $resourceName
     * @param $recId
     * @param $recordSet
     * @param $totalRecords
     * @throws \Apiator\DBApi\Exception
     */
    function out_xls($resourceName, $recId, $request, $recordSet, $totalRecords, $fileName=null) {

        if(!class_exists("\Vtiful\Kernel\Excel")) {
            HttpResp::server_error("XLS extension not loaded. Please contact the server administrator");
        }

        if(!is_null($recId) && !$totalRecords) {
            HttpResp::not_found();
        }

        function record2csv($record,$fieldsNames,$relationsNames) {
            $rec = array_values(get_object_vars($record->attributes));

            $rec = [];
            foreach ($fieldsNames as $fldName) {
                $rec[] = $record->attributes->$fldName;
            }

            foreach ($relationsNames as $relName) {
                $rec[] = empty($record->relationships->$relName->data)?null:$record->relationships->$relName->data->id;
            }

            return $rec;
        }

        // extract fields
        $fieldsNames = [];
        $relationsNames = [];
        foreach ($this->apiDm->get_config($resourceName)["fields"] as $fldName=>$spec) {
            if(isset($spec["foreignKey"])) {
                $relationsNames[] = $fldName;
            }
            else {
                $fieldsNames[] = $fldName;
            }
        }
        $xls = new \Vtiful\Kernel\Excel(["path"=>"/tmp"]);
        $this->load->helper('string');
        $fileName = $fileName ? $fileName : random_string();
        $xlsFile = $xls->fileName($fileName,$resourceName);

        $includeTHead = $this->input->get("includetablehead");
        if($includeTHead && $includeTHead=="true") {
            $header = $fieldsNames;
            array_splice($header, -1, 0, $relationsNames);
            $xlsFile->header($header);
        }

        $data = [];

        foreach ($recordSet as $record) {
            $data[] = record2csv($record,$fieldsNames,$relationsNames);
        }
        $xlsFile->data($data)->output();
        $out = file_get_contents("/tmp/$fileName");
        unlink("/tmp/$fileName");

        $fileName = $fileName ? $fileName : $resourceName;

        HttpResp::instance()
            ->header('Content-Disposition: attachment; filename="'.$fileName.'.xls"')
            ->header('Content-Type: application/vnd.ms-excel"')
            ->response_code(200)
            ->body($out)
            ->output();
    }


    /**
     * @param $recId
     * @param DBAPIRequest $request
     * @param $queryParameters
     * @param RecordSet|Record $data
     * @param $totalRecords
     * @throws Exception
     */
    function out_jsonapi($data) {
//        print_r($data);
        $doc = \JSONApi\Document::create($this->JsonApiDocOptions);

        // single record retrieval
        $doc->setData($data);
        if(get_class($data)=="RecordSet")
            $doc->setMeta(\JSONApi\Meta::factory(["offset"=>$data->offset,"totalRecords"=>$data->total]));
        HttpResp::json_out(200, $doc->json_data());

    }


    /**
     * @param $configName
     * @param $procedureName
     */
    function callStoredProcedure($configName,$procedureName)
    {
        $this->_init($configName);

        if($_SERVER["REQUEST_METHOD"]!=="POST")
            HttpResp::method_not_allowed();


        /**
         * @var \Apiator\DBApi\
         */
        $procedures = \Apiator\DBApi\Procedures::init($this->apiDb,$this->apiDm);
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
    function updateRelationships($configName,$resourceName, $recId, $relationName)
    {
        $this->_init($configName);

    }


    /**
     * @param $configName
     * @param $resourceName
     * @param $recId
     * @param $relationName
     * @param $relRecId
     */
    function deleteRelated($configName,$resourceName, $recId, $relationName, $relRecId)
    {

        $this->_init($configName);
        try {
            $rec = $this->get_related($configName,$resourceName,$recId,$relationName,$relRecId,true);
            if($rec==null) {
                HttpResp::not_found();
            }
        }
        catch (Exception $exception) {
            HttpResp::json_out($exception->getCode(),
                \JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }

        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource $resourceName not found",404);

            $rel = $this->apiDm->get_relationship($resourceName,$relationName);
        }
        catch (Exception $exception) {
            HttpResp::json_out($exception->getCode(),
                \JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }

//        $_GET["filter"] = $this->apiDm->get_idfld($rel["table"])."=".$relRecId.",".$rel["field"]."=".$recId;
//        $paras = $this->getQueryParameters($rel["table"]);


        try {

            if($this->recs->delete_record_by_id($rel["table"],$relRecId))
                HttpResp::no_content(204);
            else
                HttpResp::server_error("The record was not deleted due to unknown reasons");
//            $this->recs->deleteByWhere($rel["table"],$paras["filter"]);
        }
        catch (Exception $exception) {
            HttpResp::json_out($exception->getCode(),\JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }

    }


    /**
     * Updates related records
     * When relation is one2many it will perform a bulk update on all related records
     * @param $configName
     * @param $resourceName
     * @param $recId
     * @param $relationName
     * @param $relRecId
     * @throws Exception
     */
    function update_related($configName, $resourceName, $recId, $relationName, $relRecId=null)
    {
        $this->_init($configName);

        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource $resourceName not found",404);

            $rel = $this->apiDm->get_relationship($resourceName,$relationName);
        }
        catch (Exception $e) {
            HttpResp::json_out($e->getCode(),
                JSONApi\Document::error_doc($this->JsonApiDocOptions, JSONApi\Error::from_exception($e) )->json_data()
            );
        }

        if($relRecId) {
            $this->update_single_record($configName,$rel["table"], $relRecId);
            return;
        }

        if(!array_key_exists("filter",$_GET))
            $_GET["filter"] = "";
        $_GET["filter"] .= sprintf(",%s=%s",$rel['field'],$recId);

        $paras = $this->getQueryParameters($rel["table"]);
        $this->updateWhere($configName,$rel["table"],$paras);
    }

    /**
     * @param $configName
     * @param $resourceName
     * @param $recId
     * @param $relationName
     * @throws Exception
     */
    function create_related($configName, $resourceName, $recId, $relationName)
    {
        $this->_init($configName);

        $rel = $this->apiDm->get_relationship($resourceName,$relationName);
        if(!$rel)
            HttpResp::not_found("RecordID $recId of $resourceName not found");

        try {
            $inputData = $this->get_input_data();
        }
        catch (Exception $e) {
            HttpResp::json_out(
                $e->getCode(),
                JSONApi\Document::error_doc($this->JsonApiDocOptions, JSONApi\Error::from_exception($e) )->json_data()
            );
        }
        $fldName = $rel["field"];

        if(is_object($inputData->data)) {

            if(!isset($inputData->data->attributes)) {
                $inputData->data->attributes = new stdClass();
            }

            $inputData->data->attributes->$fldName = $recId;

//            $fld = $rel["field"];
            $this->create_records($configName,$rel["table"],$inputData);
            return;
        }

        if(is_array($inputData->data)) {
            for($i=0;$i<count($inputData->data);$i++) {
                if(!isset($inputData->data[$i]->attributes)) {
                    $inputData->data[$i]->attributes = new stdClass();
                }
                $inputData->data[$i]->attributes->$fldName = $recId;
            }

            $this->create_records($configName,$rel["table"],$inputData);
        }


        $e = new Exception("Invalid input data.\nExpected to be an object.");
        HttpResp::json_out(
            400,
            JSONApi\Document::error_doc($this->JsonApiDocOptions, JSONApi\Error::from_exception($e) )->json_data()
        );





    }

    /**
     * fetch related resource(s)
     * @param $configName
     * @param string $parentResource parent record resource type
     * @param string $recId parent record ID
     * @param string $relationName related resource name
     * @param null $relRecId
     * @return void|null
     * @throws Exception
     */
    function get_related($configName, $parentResource, $recId, $relationName, $relRecId=null,$interal=false)
    {
        $this->_init($configName);

        try {
            $relSpec = $this->apiDm->get_relationship($parentResource, $relationName);
        }
        catch (Exception $exception) {
            $doc = \JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception);
            if($interal) {
                throw $exception;
            }
            else {
                HttpResp::json_out($exception->getCode(), $doc->json_data());
            }
        }


        try {
            $request = $this->get_request($relSpec["table"]);
        }
        catch (Exception $exception) {
            if($interal) {
                throw $exception;
            }
            else {
                HttpResp::exception_out($exception);
            }

        }

        if($relSpec["type"]=="outbound") {
            $tmpReq = new DBAPIRequest($parentResource,1);
            $fkFldName = $relSpec["fkfield"];
            $tmpReq->add_field($fkFldName)->add_filter("$fkFldName=$recId");

            if(is_null($relRecId))
                $tmpReq->add_filter("$fkFldName!=__NULL__");
            else
                $tmpReq->add_filter("$fkFldName=$relRecId");

            $rec = $this->recs->get_records($tmpReq);
            if($rec->total==0) {
                if($interal) {
                    return null;
                }
                else {
                    HttpResp::error_out_json("relation $fkFldName".($relRecId ? "/$relRecId" : "")." of $parentResource/$recId not found",404);
                }
            }
            $fkRecId = $rec->records[0]->relationships[$fkFldName]->id;

            if($interal) {
                return $this->get_records($configName,$relSpec["table"],$fkRecId,null,null,true);
            }
            else {
                $this->get_records($configName,$relSpec["table"],$fkRecId);
            }
        }


        $tmpReq = new DBAPIRequest($parentResource,1);
        $idFld = $this->apiDm->get_idfld($parentResource);
        $tmpReq->add_field($idFld)
            ->add_filter("$idFld=$recId");

        $rec = $this->recs->get_records($tmpReq);
        if($rec->total==0) {
            HttpResp::error_out_json("Resource ID $recId of type $parentResource not found",404);
        }

        try {
            if(is_null($request))
                $request = $this->get_request($relSpec["table"]);
        }
        catch (Exception $exception) {
            var_dump($exception->getTraceAsString());
            HttpResp::exception_out($exception);
        }
        $request->add_filter("{$relSpec['field']}=$recId");
//        print_r($request);
        if($interal) {
            return $this->get_records($configName,$relSpec["table"],$relRecId,$request,true);
        }
        else {
            $this->get_records($configName,$relSpec["table"],$relRecId,$request);
        }

    }

    private function normalize_object($obj)
    {
        if(isset($obj->relationships)) {
            if(isset($obj->relationships->data)) {
                $obj->relationships = $obj->relationships->data;
            }

            foreach ($obj->relationships as $relName=>$relData) {
                $obj->relationships->$relName = $this->normalize_input_data($relData);
            }
        }
        return $obj;
    }

    private function normalize_input_data($data) {
        if(!$data)
            return $data;

        $resp = $data->data;
        if(is_object($resp)) {
            $resp = $this->normalize_object($resp);
        }

        if(is_array($resp)) {
            for($i=0;$i<count($resp);$i++) {
                $resp[$i] = $this->normalize_object($resp[$i]);
            }
        }
        return $resp;
    }

    /**
     * Insert records recursively
     * @param $configName
     * @param $resourceName
     * @param null $input
     * @return null
     * TODO: add some limitation for maximum records to insert at a time
     * @throws Exception
     */
    public function create_records($configName, $resourceName, $input=null)
    {
        $this->_init($configName);

        // get input data
        try {
            $input = $this->get_input_data($input);
        }
        catch (Exception $e) {
            HttpResp::json_out(
                $e->getCode(),
                JSONApi\Document::error_doc($this->JsonApiDocOptions, JSONApi\Error::from_exception($e) )->json_data()
            );
        }


        if(is_null($input)) {
            HttpResp::json_out(400,
                \JSONApi\Document::error_doc($this->JsonApiDocOptions, [
                    \JSONApi\Error::factory(["title" => "Empty input data not allowed", "code" => 400])
                ])->json_data()
            );
        }

        // configure onDuplicate behaviour
        $onDuplicate = $this->input->get("onduplicate");
        if(!in_array($onDuplicate,["update","ignore","error"])) {
            $onDuplicate = "error";
        }
        $insertIgnore = $this->input->get("insertignore");
        if($insertIgnore=="true") {
            $insertIgnore  = true;
        }
        // configure fields to be updated when onduplicate is set to "update"
        $updateFields = [];
        if($onDuplicate=="update") {
            $updateFields = getFieldsToUpdate($this->input,$resourceName);
            if(!count($updateFields))
                $onDuplicate = null;
        }

        // starts transaction
        $this->apiDb->trans_begin();

        // prepare data
        $singleInsert = !is_array($input->data);

        $entries = $singleInsert?[$input->data]:$input->data;

        $includes = get_include($this->input);
        $newRecIds = [];
        foreach($entries as $entry) {
            try {
                // todo: what happens when the records are not uniquely identifiable? think about adding an extra behavior
                $recId = $this->recs->insert($resourceName, $entry, $this->insertMaxRecursionLevel,
                    $onDuplicate, $insertIgnore, $updateFields,null,$includes);
                $newRecIds[] = $recId;
            }
            catch (Exception $exception)
            {

//                var_dump($exception);
                $this->apiDb->trans_rollback();
                $respHttpCode = 500;
                switch ($exception->getCode()) {
                    case 1062:
                        $respHttpCode = 409;
                        break;
                }
                HttpResp::json_out($respHttpCode,\JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
            }
        }



        $this->apiDb->trans_commit();
        //return [$insertedRecords,$totalRecords];
        try {
            $request = $this->get_request($resourceName);
        }
        catch (Exception $exception) {
            var_dump($exception->getTraceAsString());
            HttpResp::exception_out($exception);
        }
        if($singleInsert) {
            $this->get_records($configName,$resourceName,array_pop($newRecIds));
            return ;
        }

        $request->add_filter($this->apiDm->get_primary_key($resourceName)."><".implode(";",$newRecIds));

        $this->get_records($configName,$resourceName,null,$request);
    }

    /**
     * @param $resourceName
     * @param $recId
     */
    function deleteSingleRecord($configName, $resourceName, $recId)
    {
        $this->_init($configName);
        try {
            $this->recs->delete_by_id($resourceName, $recId);
            HttpResp::no_content(204);
        }
        catch (Exception $exception) {
            HttpResp::json_out($exception->getCode(),\JSONApi\Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }
    }



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

    private function createMultipleRecords()
    {


    }
}

/**
 * @param $str
 * @return string|string[]
 */
function custom_where($str) {
    $expr = [];
    $start = 0;
    for($i=0;$i<strlen($str);$i++) {
        if(in_array(substr($str,$i,2),["&&","||"])){
            $expr[] = [
                "type"=>"expr",
                "expr"=>substr($expr,$start,$i-$start)
            ];
        }
    }
    $str = urldecode($str);
    $str = str_replace("&&", "' AND ",$str);
    $str = str_replace("||", "' OR ",$str);
    return $str;
}


/**
 * @param $ip
 * @param $ranges
 * @return bool
 */
function find_cidr($ip, $ranges)
{

    if(!is_array($ranges)) {
        return false;
    }
    foreach($ranges as $range)
    {
        if(cidr_match($ip, $range))
        {
            return true;
        }
    }
    return false;
}
function cidr_match($ip, $range){
    list ($subnet, $bits) = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask; // in case the supplied subnet was not correctly aligned
    return ($ip & $mask) == $subnet;
}


/**
 * shorthand method to fetch some of the GET parameters and set them to the request object
 * It applies only to most of the request parameters with some exceptions
 * the passed parameter must be a hash with keys being the resource name for which the parameter applies
 * @param string $param
 * @param array $inputs
 * @param string $resourceName
 * @param DBAPIRequest $request
 */
function set_para(string $param, array &$inputs, string $resourceName, DBAPIRequest &$request,$default=null) {
    if(!isset($inputs[$param])) {
        return;
    }

    if(!is_array($inputs[$param])) {
        $inputs[$param] = [
            $resourceName => $inputs[$param]
        ];
    }
    if(!isset($inputs[$param][$resourceName])) {
        return;
    }

    $request->$param = $inputs[$param][$resourceName];
    unset($inputs[$param][$resourceName]);
}

class DBAPIRequest {
    /**
     * resource name
     * @var string
     */
    public $resourceName;    // resource name to be fetched (can be name of a table/view or relation name)

    /**
     * record ID
     * @var string|null
     */
    public $resourceId = null;

    /**
     * @var string|null
     */
    public $primaryKey = null;

    /**
     * filter conditions
     * @var array|string
     */
    public $filter = [];                     // filters (where)

    /**
     * array of fields to include
     * @var array|string
     */
    public $fields = [];                     // fields to fetch

    public $fieldsIndexes = [];
    /**
     * array of includes
     * @var DBAPIRequest[]
     */
    public $include = [];                    // relations to include

    /**
     * pagging parameter
     * @var array
     */
    public $page = [
        "offset"=>0,
        "limit"=>10
    ];

    public $offset=0;
    public $limit=10;

    /**
     * array of sort options
     * @var array|string
     */
    public $sort = [];                       // sort options

    /**
     * customer where query
     * @var string|null
     */
    public $filter_advanced = null;



    /**
     * sets on duplicate behaviour: ignore, update, error
     * @var string|null
     */
    public $onduplicate = "error";

    /**
     * array of fields to update when onduplicate=update
     * @var array|string
     */
    public $update = [];

    /**
     * if true insert ignore will be applied on inserts
     * @var boolean
     */
    public $insertignore = false;

    /**
     * relationship type [inbound|outbound]
     * @var array|null
     */
    public $relSpec = null;

    public $selectFieldsOffset;

    /**
     * @param $fldName
     * @return $this
     */
    public function &add_field($fldName) {
        $this->fields[] = $fldName;
        $this->fieldsIndexes[$fldName] = count($this->fields)-1;
        return $this;
    }

    /**
     * @param $expression
     * @return $this
     * @throws \Apiator\DBApi\Exception
     */
    public function &add_filter($expression) {

        if(!$expression)
            return $this;
        $validOps = ["!=","=","<","<=",">",">=","><","~=","!~=","~=~","!~=~","=~","!=~","<>","!><"];

        if(!preg_match("/(\w+)([\>\<\=\~\!]+)(.*)/",$expression,$matches)) {
            throw new \Apiator\DBApi\Exception("Invalid filtering expression $expression ".
                "for resource $this->resourceName",400);
        }

        if(!in_array($matches[2],$validOps)) {
            throw new \Apiator\DBApi\Exception("Invalid comparison operator in".
                " filtering expression $expression for resource $this->resourceName",400);
        }

        $this->filter[] = [
            "left"=>$matches[1],
            "op"=>$matches[2],
            "right"=>$matches[3],
        ];
        return $this;
    }

    function __construct($resourceName,$defaultPageSize)
    {
        $this->resourceName = $resourceName;
        $this->page["limit"] = $defaultPageSize;
    }
}