<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use dbAPI\API\DBAPIRequest;
use JSONApi\Document;

/**
 * @property CI_Config $config
 * @property CI_Loader $load
 * @property CI_Input $input
 * @property Utilities $utilities
 */
trait DbapiRelationsTrait
{
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
    function delete_related($configName,$resourceName, $recId, $relationName, $relRecId)
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
                Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }

        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource $resourceName not found",404);

            $rel = $this->apiDm->get_relationship($resourceName,$relationName);
        }
        catch (Exception $exception) {
            HttpResp::json_out($exception->getCode(),
                Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
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
            HttpResp::json_out($exception->getCode(), Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
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

        $paras = $this->get_req_parameters($rel["table"]);
        $this->update_where($configName,$rel["table"],$paras);
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
    function get_related_2nd($configName,$parent,$parentRecId,$parentRelName,$parentRelRecId,$relName,$relRecId=null,$internal=false) {
        $this->_init($configName);
        print_r(func_get_args());


    }

    /**
     * fetch related resource(s)
     * @param $configName
     * @param string $parentResource parent record resource type
     * @param string $recId parent record ID
     * @param string $relationName related resource name
     * @param null $relRecId
     * @return void|null|\dbAPI\API\Records
     * @throws Exception
     */
    function get_related($configName, $parentResource, $recId, $relationName, $relRecId=null,$interal=false)
    {
        $this->_init($configName);

        try {
            $relationship = $this->apiDm->get_relationship($parentResource, $relationName);
        }
        catch (Exception $exception) {
            $doc = Document::from_exception($this->JsonApiDocOptions,$exception);
            if($interal) {
                throw $exception;
            }
            else {
                HttpResp::json_out($exception->getCode(), $doc->json_data());
            }
        }


        try {
            $request = $this->get_dbapi_request($relationship["table"]);

        }
        catch (Exception $exception) {
            if($interal) {
                throw $exception;
            }
            else {
                HttpResp::exception_out($exception);
            }
        }

        if($relationship["type"]=="outbound") {
            $tmpReq = new DBAPIRequest($parentResource,1);
            $fkFldName = dbapi_outbound_local_column($relationName, $relationship);
            $tmpReq->add_field($fkFldName)->add_filter($this->apiDm->get_primary_key($parentResource)."=$recId");
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
                    HttpResp::error_out_json("related foreign key data $fkFldName".($relRecId ? "/$relRecId" : "")." of $parentResource/$recId not found",404);
                }
            }
            $fkRecId = $rec->records[0]->relationships[$fkFldName]->id;

            if($interal) {
                return $this->get_records($configName,$relationship["table"],$fkRecId,null,null,true);
            }
            else {
                $this->get_records($configName,$relationship["table"],$fkRecId);
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
                $request = $this->get_dbapi_request($relationship["table"]);
        }
        catch (Exception $exception) {
            var_dump($exception->getTraceAsString());
            HttpResp::exception_out($exception);
        }
        $request->add_filter("{$relationship['field']}=$recId");
//        print_r($request);
        if($interal) {
            return $this->get_records($configName,$relationship["table"],$relRecId,$request,true);
        }
        else {
            $this->get_records($configName,$relationship["table"],$relRecId,$request);
        }

    }

    /**
     * Normalizing input data means compressing the relationship object by placing the data content directly inside the relationship object
     * eg: relaName.data.obj => relname.obj
     * @param $obj
     * @return mixed
     */
}
