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

        if (!$internal) {
            $outputFormat = $this->input->get("format");
            $outputFormat = $outputFormat && in_array($outputFormat, ["csv", "xls", "json"], true)
                ? $outputFormat
                : "json";
            // Tabular export: keep outbound (1:1) includes only — inbound (1:n) cannot flatten into a row.
            if (in_array($outputFormat, ["csv", "xls"], true) && !empty($request->include)) {
                $request->include = $this->filter_outbound_includes($request->include, $resourceName);
            }
        }
        

        // validation
        try {
            if(!$this->apiDm->resource_exists($resourceName))
                throw new Exception("Resource 1 s $resourceName not found",404);

            if(!is_null($recId)) {
                if (!$this->apiDm->has_primary_key($resourceName)) {
                    throw new Exception(
                        "GET by id is not supported for '$resourceName' (no primary key). Use list with filter instead.",
                        404
                    );
                }
                $keyFld = $this->apiDm->get_primary_key($resourceName);

                $request->resourceId = $recId;
                $request->add_filter_condition($keyFld, "=", $recId);
            }
        }

        catch (Exception $exception) {
            if($internal)
                throw $exception;
            else
                HttpResp::json_out(HttpResp::exceptionHttpStatus($exception->getCode()), Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
        }

        // fetch records
        try {
            $result = $this->recs->get_records($request);
        }
        catch (Exception $exception) {
            if($internal)
                throw $exception;
            else
                HttpResp::json_out(HttpResp::exceptionHttpStatus($exception->getCode()), Document::from_exception($this->JsonApiDocOptions,$exception)->json_data());
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
                $csvRecords = ($result instanceof \RecordSet) ? $result->records : [$result];
                $csvTotal = ($result instanceof \RecordSet) ? (int) $result->total : 1;
                $this->out_csv($resourceName, $recId ?? '', $request, $csvRecords, $csvTotal, $this->input->get("filename"));
                break;
            case "xls":
                $xlsRecords = ($result instanceof \RecordSet) ? $result->records : [$result];
                $xlsTotal = ($result instanceof \RecordSet) ? (int) $result->total : 1;
                $this->out_xls($resourceName, $recId ?? '', $request, $xlsRecords, $xlsTotal, $this->input->get("filename"));
                break;
            case "json":
                error_log("get_records asdasd: $resourceName afterCreate: $afterCreate\n");
                $this->out_jsonapi($result,$afterCreate ? 201 : 200);
        }
        return null;
    }

    /**
     * Drop inbound (1:n) includes; keep outbound (1:1), including nested outbound chains.
     *
     * @param array<string, DBAPIRequest> $includes
     * @return array<string, DBAPIRequest>
     */
    private function filter_outbound_includes(array $includes, string $resourceName): array
    {
        $out = [];
        foreach ($includes as $relName => $inclReq) {
            try {
                $rel = $this->apiDm->get_relationship($resourceName, $relName);
            } catch (Exception $e) {
                continue;
            }
            if (($rel["type"] ?? "") !== "outbound") {
                continue;
            }
            if (!empty($inclReq->include) && is_array($inclReq->include)) {
                $inclReq->include = $this->filter_outbound_includes($inclReq->include, $rel["table"]);
            }
            $out[$relName] = $inclReq;
        }
        return $out;
    }

    /**
     * @return list<array{header:string,path:list<string>}>
     */
    private function csv_column_specs(string $resourceName, DBAPIRequest $request): array
    {
        $selectedFields = $request->exportFields ?? $this->apiDm->get_selectable_fields($resourceName);

        $specs = [];
        foreach ($selectedFields as $fldName) {
            $specs[] = ["header" => $fldName, "path" => [$fldName]];
        }
        if (!empty($request->include) && is_array($request->include)) {
            $this->append_include_csv_columns($specs, [], $request->include);
        }
        return $specs;
    }

    /**
     * @param list<array{header:string,path:list<string>}> $specs
     * @param list<string> $pathPrefix
     * @param array<string, DBAPIRequest> $includes
     */
    private function append_include_csv_columns(array &$specs, array $pathPrefix, array $includes): void
    {
        foreach ($includes as $relName => $inclReq) {
            $relPath = array_merge($pathPrefix, [$relName]);
            $exportFields = $inclReq->exportFields ?? $this->apiDm->get_selectable_fields($inclReq->resourceName);
            foreach ($exportFields as $fld) {
                $fieldPath = array_merge($relPath, [$fld]);
                $specs[] = [
                    "header" => implode(".", $fieldPath),
                    "path" => $fieldPath,
                ];
            }
            if (!empty($inclReq->include) && is_array($inclReq->include)) {
                $this->append_include_csv_columns($specs, $relPath, $inclReq->include);
            }
        }
    }

    /**
     * @param list<string> $path
     * @return mixed
     */
    static private function csv_cell_value($record, array $path)
    {
        $current = $record;
        $last = count($path) - 1;
        for ($i = 0; $i <= $last; $i++) {
            if ($current === null) {
                return null;
            }
            $key = $path[$i];
            if ($i < $last) {
                $current = (isset($current->relationships) && array_key_exists($key, $current->relationships))
                    ? $current->relationships[$key]
                    : null;
                continue;
            }
            if (isset($current->relationships) && array_key_exists($key, $current->relationships)) {
                $rel = $current->relationships[$key];
                if ($rel === null) {
                    return null;
                }
                if (is_object($rel) && !($rel instanceof \RecordSet) && property_exists($rel, "id")) {
                    return $rel->id;
                }
                return null;
            }
            return $current->attributes->$key ?? null;
        }
        return null;
    }

    /**
     * @param list<array{header:string,path:list<string>}> $columnSpecs
     * @return list<mixed>
     */
    static private function record_values($record, array $columnSpecs): array
    {
        $rec = [];
        foreach ($columnSpecs as $spec) {
            $rec[] = self::csv_cell_value($record, $spec["path"]);
        }
        return $rec;
    }

    /**
     * RFC 4180 CSV field: wrap in double quotes; internal quotes doubled.
     */
    static private function csv_field($value): string
    {
        if ($value === null) {
            $value = '';
        } elseif (!is_string($value)) {
            $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * @param list<mixed> $fields
     */
    static private function csv_line(array $fields): string
    {
        return implode(',', array_map([self::class, 'csv_field'], $fields));
    }

    /**
     * @param list<array{header:string,path:list<string>}> $columnSpecs
     */
    static private function record2csv($record, array $columnSpecs): string
    {
        return self::csv_line(self::record_values($record, $columnSpecs));
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
    function out_csv(string $resourceName,string $recId,DBAPIRequest $request,$records,int $totalRecords,string $fileName=null) {

        if($recId !== '' && !$totalRecords) {
            HttpResp::not_found();
        }

        
        $out = [];
        $columnSpecs = $this->csv_column_specs($resourceName, $request);
        $headers = array_column($columnSpecs, "header");

        $includeTHead = $this->input->get("includetablehead");
        if ($includeTHead !== "false") {
            $out[] = self::csv_line($headers);
        }


        foreach ($records as $record) {
            $out[] = self::record2csv($record, $columnSpecs);
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

        $columnSpecs = $this->csv_column_specs($resourceName, $request);
        $headers = array_column($columnSpecs, "header");

        $xls = new \Vtiful\Kernel\Excel(["path"=>"/tmp"]);
        $this->load->helper('string');
        $fileName = $fileName ? $fileName : random_string();
        $xlsFile = $xls->fileName($fileName,$resourceName);

        $includeTHead = $this->input->get("includetablehead");
        if($includeTHead && $includeTHead=="true") {
            $xlsFile->header($headers);
        }

        $data = [];
        foreach ($recordSet as $record) {
            $data[] = self::record_values($record, $columnSpecs);
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
