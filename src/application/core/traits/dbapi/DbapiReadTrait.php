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
trait DbapiReadTrait
{
    function get_records(string $configName, string $resourceName, string $recId=null,DBAPIRequest $request=null,bool $internal=false,bool $afterCreate=false)
    {
        // init DB connection & load config
        $this->_init($configName);
        // parse input paramas into request
        error_log("get_records: $resourceName afterCreate: $afterCreate\n");
        try {
            if(is_null($request))
                $request = $this->get_dbapi_request($resourceName);
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
        

        // validation
        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource 1 s $resourceName not found",404);

            if(!is_null($recId)) {
                $keyFld = $this->apiDm->get_primary_key($resourceName);
                if(is_null($keyFld))
                    throw new Exception("Request not supported. $resourceName does not have a primary key defined", 404);

                $request->resourceId = $recId;
                $request->add_filter_condition($keyFld, "=", $recId);
            }
        }

        catch (Exception $exception) {
            if($internal)
                throw $exception;
            else
                HttpResp::json_out($exception->getCode(), Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }

        // fetch records
        try {
            $result = $this->recs->get_records($request);
        }
        catch (Exception $exception) {
            if($internal)
                throw $exception;
            else
                HttpResp::json_out($exception->getCode(), Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
            die();
        }

        if(!is_null($recId)) {
            if(!$result->total) {
                if($internal)
                    return null;
                else 
                    HttpResp::not_found();
            }
            $result = $result->records[0];
        }
        

        if($internal)
            return $result;

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
                error_log("get_records asdasd: $resourceName afterCreate: $afterCreate\n");
                $this->out_jsonapi($result,$afterCreate ? 201 : 200);
        }
        return null;
    }

    static  private function record2csv($record,$fieldsNames,$relationsNames) {
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
    /**
     * @param string $resourceName
     * @param string $recId
     * @param DBAPIRequest $request
     * @param \dbAPI\API\Records $records
     * @param int $totalRecords
     * @param string|null $fileName
     * @throws \dbAPI\API\Exception
     */
    function out_csv(string $resourceName,string $recId,DBAPIRequest $request,\dbAPI\API\Records $records,int $totalRecords,string $fileName=null) {

        if(!is_null($recId) && !$totalRecords) {
            HttpResp::not_found();
        }

        
        $out = [];

        // extract fields
        $fieldsNames = [];
        $relationsNames = [];
        $fkFields = array_keys($this->apiDm->get_fk_fields($resourceName));
        foreach ($this->apiDm->get_config($resourceName)["fields"] as $fldName => $spec) {
            if (in_array($fldName, $fkFields, true)) {
                $relationsNames[] = $fldName;
            } else {
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
            $out[] = self::record2csv($record,$fieldsNames,$relationsNames);
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
     * @param string $resourceName
     * @param string $recId
     * @param DBAPIRequest $request
     * @param \dbAPI\API\Records $recordSet
     * @param int $totalRecords
     * @param string|null $fileName
     * @throws \dbAPI\API\Exception
     */
    function out_xls(string $resourceName, string $recId, DBAPIRequest $request, \dbAPI\API\Records $recordSet, int $totalRecords, string $fileName=null) {

        if(!extension_loaded('xlswriter')) {
            HttpResp::server_error("XLS extension not loaded. Please install php-xlswriter extension.");
            return;
        }

        if(!is_null($recId) && !$totalRecords) {
            HttpResp::not_found();
        }

        // extract fields
        $fieldsNames = [];
        $relationsNames = [];
        $fkFields = array_keys($this->apiDm->get_fk_fields($resourceName));
        foreach ($this->apiDm->get_config($resourceName)["fields"] as $fldName => $spec) {
            if (in_array($fldName, $fkFields, true)) {
                $relationsNames[] = $fldName;
            } else {
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
            $data[] = self::record2csv($record,$fieldsNames,$relationsNames);
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
    function out_jsonapi($data,$httpCode=200) {
//        print_r($data);
        $doc = Document::create($this->JsonApiDocOptions);

        // single record retrieval
        $doc->setData($data);
        if(get_class($data)=="RecordSet")
            $doc->setMeta(\JSONApi\Meta::factory(["offset"=>$data->offset,"totalRecords"=>$data->total]));
        HttpResp::json_out($httpCode, $doc->json_data());

    }


    /**
     * @param $configName
     * @param $procedureName
     */
}
