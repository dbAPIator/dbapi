<?php
namespace Apiator\DBApi;

// @todo: https://jcs.org/2023/07/12/api
/**
 * @todo Log a unique id with every request
 * @todo Be descriptive in your error responses
 */


//require_once(__DIR__."/../../../libraries/Response.php");


require_once(__DIR__."/../../../libraries/Errors.php");
require_once(__DIR__.'/../../../libraries/RecordSet.php');

require_once (APPPATH."/third_party/JSONApi/Autoloader.php");

use http\Exception;
use JSONApi\Autoloader;
Autoloader::register();

use JSONApi\Document;
use Softaccel\Apiator\DBApi\DBAPIRequest;

//require_once(__DIR__."/../../../libraries/HttpResp.php");

/**
 * Class Records
 * core class for manipulating the records in the Database
 */
class Records {

    private $debug = true;
    /**
     * @var Datamodel $dm
     */
    private $dm;

    /**
     * @var \CI_DB_query_builder $dbdrv
     */
    private $dbdrv;
    private $maxNoRels=10;
    private $configDir;

    /**
     * Records constructor.
     * @param \CI_DB_query_builder $dbDriver
     * @param Datamodel $dataModel
     */
    function __construct($dbDriver,$dataModel,$apiConfigDir) {
        $this->dm = $dataModel;
        $this->dbdrv = $dbDriver;
        $instance = get_instance();
        $this->maxNoRels = $instance->config->item("inbound_relationships_page_size");
        $this->configDir = $apiConfigDir;
    }

    static function init($dbDriver,$dataModel,$apiConfigDir) {
        return new Records($dbDriver,$dataModel,$apiConfigDir);
    }




    /**
     * @param $filters
     * @param $resName
     * @return int|string
     * @throws \Exception
     */
    private function generate_where_sql($filters,$alias)
    {
        // todo: correct filtering.... after allowing searching by fields beloning to joined tables...
        $whereArr = [];
        foreach ($filters as $filter) {
            $whereArr[] = generate_where_condition((object) $filter,$alias);
        }
        return count($whereArr) ? implode(" AND ", $whereArr) : 1;
    }


    /**
     * @param \DBAPIRequest $request
     * @return array
     */
    function generate_select_sql_parts(&$request)
    {
        /**
         * @param \DBAPIRequest $req
         * @param $fields
         * @param $join
         * @param string $tableAlias
         * @param string $parentTableAlias
         * @param int $offset
         */


        $fields = [];
        $join = [];
        recursive_generate_select_and_joins($request, $fields, $join,$request->resourceName);
        return [$fields,$join];
    }

    /**
     * Retrieves records from a database.
     *
     * Inner workings:
     * 1. basic checks (resource exists & is readable)
     * 2. prepare query parameters
     * 3. find out total number of matched records
     * 4. prepare fields selection
     * 5. generate SQL parts
     * 6. create ORDER BY
     * 7. compile SELECT statement
     * 8. run query
     * 9. parse result
     *
     * @param \DBAPIRequest $request
     * @return \RecordSet
     * @throws \Exception
     * @todo: check filtering
     */
    function get_records(DBAPIRequest $request,$dbg=false)
    {
        //print_r($request);
        // check if it is allowed to read the resource data
        $resourceName = $request->resourceName;
        if(!$this->dm->resource_allow_read($resourceName))
            throw new \Exception("Not authorized to read from '$resourceName'",403);

        $whereStr = $this->generate_where_sql($request->filter,$request->resourceName);
        if(empty($whereStr)) {
            $whereStr = 1;
        }

        // if filter advanced is set, then append it to the current where
        if($request->filter_advanced) {
            $whereStr .= " AND ".$request->filter_advanced;
        }

        list($select,$join) = $this->generate_select_sql_parts($request);

        // extract total number of records matched by the query
        $countSql = "SELECT count(*) cnt FROM `$request->resourceName` "
            .implode(" ",$join)
            ." WHERE $whereStr";

        if($dbg) {
            echo $countSql."\n";
        }   

        $res = $this->dbdrv->query($countSql);
        $err=$this->dbdrv->error();
        if($err["code"]){
            throw new \Exception($err["message"]." on query: $countSql",500);
        }
        $row = $res->row();
        $totalRecs = $row->cnt*1;


        $recordSet = new \RecordSet($resourceName,[],$request->offset,$totalRecs);
        // no records found => return empty recordSet
        if($totalRecs==0)
            return $recordSet;

        // compile SELECT
        $mainSql = "SELECT ".join(",",$select)." FROM `$request->resourceName` "
            .implode(" ",$join)
            ." WHERE $whereStr"
            ." ORDER BY ".(count($request->sort) ? implode(",",$request->sort) : 1 )
            ." LIMIT {$request->offset},{$request->limit}";
//        echo $mainSql."\n";

        // run query
        /** @var \CI_DB_result $res */
        $res = $this->dbdrv->query($mainSql);
        $err=$this->dbdrv->error();
        if($err["code"]){
            throw new \Exception($err["message"],500);
        }
        //get_instance()->debug_log($mainSql);

        $rows = $res->result_array_num();

        // parse result
        $allRecs = [];
//        print_r($request);
        foreach ($rows as $row) {
            $newRec = $this->parse_result_row($request,$row,$allRecs);
            $recordSet->add_record($newRec);
        }


        return $recordSet;
    }

    /**
     * @param \DBAPIRequest $request
     * @param array $row
     * @param array $allRecs
     * @return mixed|\stdClass
     * @throws \Exception
     */
    function parse_result_row($request,$row,&$allRecs){
        $recId = null;
        if($request->primaryKey) {
            $priKeyPos = $request->fieldsIndexes[$request->primaryKey];
            $fldRecPos = $request->selectFieldsOffset + $priKeyPos;
            $recId = $row[$fldRecPos];

            $objIdx = $request->resourceName."_".$recId;
            if(isset($allRecs[$objIdx])) {
                $rec = &$allRecs[$objIdx];
            }
        }

        // record not yet saved in allRecs -> need to extract it
        if(!isset($rec)){
            $rec = new \Record($request->resourceName,$recId ,[]);
            $relations = $this->dm->get_all_relations($request->resourceName);

            // process outbound (1:1) relations
            foreach ($request->fields as $idx=>$fieldName) {
                // set attribute when fld is not FK
                if(!isset($relations[$fieldName])) {
                    $rec->attributes->$fieldName = $row[$request->fieldsIndexes[$fieldName]+$request->selectFieldsOffset];
                    continue;
                }

                // fld is not requested to be included => create a simple Rec (no attr and rels)
                if(!isset($request->include[$fieldName])) {
                    $relRecId = $row[$request->fieldsIndexes[$fieldName]+$request->selectFieldsOffset];
                    $relationRecord = null;
                    if(!is_null($relRecId)) {
                        $relationRecord = new \Record($relations[$fieldName]["table"],$relRecId);
                    }

                    $rec->add_one2one_relation($fieldName,$relationRecord);
                    continue;
                }

                // fld is requested in the includes => parse result row to generate Recods and add is a relation
                $relationRecord = $this->parse_result_row($request->include[$fieldName],$row,$allRecs);
                if(is_null($relationRecord->id)) {
                    continue;
                }
                $rec->add_one2one_relation($fieldName,$relationRecord);


                // fld is requested to be included => it's data is included in the row => parse the row
                if(isset($request->include[$fieldName])) {
                    $relationRecord = $this->parse_result_row($request->include[$fieldName],$row,$allRecs);
                    if(is_null($relationRecord->id)) {
                        continue;
                    }
                    $rec->add_one2one_relation($fieldName,$relationRecord);
                    continue;
                }

                // fld is not requested to be included => create an simple Record
                $relRecId = $row[$request->fieldsIndexes[$fieldName]+$request->selectFieldsOffset];
                $relationRecord = null;
                if(!is_null($relRecId)) {
                    $relationRecord = new \Record($relations[$fieldName]["table"],$relRecId);
                }
                $rec->add_one2one_relation($fieldName,$relationRecord);
            }

            // process inbound (1:n) relations
            foreach ($relations as $relName=>$relSpec) {
                // skip 1:1 relations (already processed)
                if($relSpec["type"]=="outbound") {
                    continue;
                }

                // rel not requested to be included => create an empty array relation
                if(!isset($request->include[$relName])) {
                    $rec->add_one2many_relation($relName);
                    continue;
                }
//                echo "Process included 1:n $relName of $request->resourceName\n";
                // add "parent" condition to filter recs who "point" to the parent
                for($i=count($request->include[$relName]->filter)-1;$i>=0;$i--) {
                    if($request->include[$relName]->filter[$i]["left"]==$relSpec["field"]) {
                        array_splice($request->include[$relName]->filter,$i,1);
                    }
                }
                $request->include[$relName]->filter[] = [
                    "left" => $relSpec["field"],
                    "op" => "=",
                    "right" => $recId
                ];


                $recordSet = $this->get_records($request->include[$relName]);
                $rec->add_one2many_relation($relName,$recordSet);
            }

            if(isset($objIdx))
                $allRecs[$objIdx] = $rec;
        }

        return $rec;
    }

    /**
     * @param $resName
     * @param $attributes
     * @return mixed
     * TODO: review this method
     * @throws \Exception
     */
    private function validate_insert_data($resName, $attributes) {
        $attributesNames = array_keys($attributes);

        foreach($this->dm->get_fields($resName) as $fldName=> $fldSpec) {
            if($fldSpec["required"] && is_null($fldSpec["default"]) && !in_array($fldName,$attributesNames))
                throw new \Exception("Required attribute '$fldName' of '$resName' not provided",400);

            // field not allowed to insert
            if(!$this->dm->field_is_insertable($resName,$fldName) && in_array($fldName,$attributesNames))
                throw new \Exception("Attribute '$resName/$fldName' not allowed to be inserted",400);
        }

        foreach($attributes as $attrName=> $attrVal) {
            $attrVal = $this->dm->is_valid_value($resName,$attrName,$attrVal);

            /**
             * TODO: instead of just checking if value is an object as exception when value type validation fails
             *       implement a proper mechanism inside the is_valid_value method
             */
            if(!is_object($attrVal)) {
                $attributes[$attrName] = $attrVal;
            }
        }
        return $attributes;
    }

    /**
     * create new Record
     * TODO: clarify best approach about what to return after inserting OK....
     *
     * @param $table
     * @param object $data data to be inserted
     * @param $watchDog
     * @param string $onDuplicate behaviour flags
     * @param String[] $fieldsToUpdate
     * @param $path
     * @param $includes
     * @return \Response
     * @throws \Exception
     */
    function insert($table, $data, $watchDog, $onDuplicate, $insertIgnore, $fieldsToUpdate, $path, &$includes) {

        if($watchDog==0) {
            throw new \Exception("Maximum recursion level has been reached. Aborting. Please review your nested data.",400);
        }

        //$table = $data->type;
//        if($data->type!=$table) {
//            throw new \Exception("Invalid data type '$data->type' for '$table'",400);
//        }

        // check if resource exists
        if(!$this->dm->resource_exists($table)) {
            throw new \Exception("Resource '$table'' not found",404);
        }

        // check if client is authorized to insert into resource
        if(!$this->dm->resource_allow_insert($table)) {
            throw new \Exception("Not authorized to insert into resource '$table'",401);
        }

        // validate attributes
        $attributes = $data->attributes;
        $relations = isset($data->relationships)?$data->relationships:[];
        $idFld = $this->dm->get_primary_key($table);

        $insertData = [];
        if(isset($data->id)) {
            $insertData[$idFld] = $data->id;
        }

        // collect attributes
        foreach($attributes as $fldName=>$value) {
            if(!$this->dm->field_is_insertable($table,$fldName)) {
                throw new \Exception("Not allowed to insert data in field '$fldName' of table '$table'",400);
            }
            $insertData[$fldName] = $value;
        }

        $one2nRelations = [];

        // iterate relations and insert recursive if it is the case
        foreach ($relations as $relName=>$relData) {
            // gets relationship config. Throws an error when relation is not valid
            $relSpec = $this->dm->get_relationship($table, $relName);

            if(empty($relData)) {
                continue;
            }


            // todo: implement full validation in input_validator and remove the code bellow
            if(!is_object($relData)) {
                throw new \Exception("Invalid relationship '$relName' data: invalid format ",400);
            }

            if(!isset($relData->data)) {
                throw new \Exception("Invalid relationship '$relName' data: 'data' property not set",400);
            }

            if(is_null($relData->data)) {
                continue;
            }

            // relation type vs data type: object for outbound relations; array for inbound relations
            if ($relSpec["type"]=="inbound" && !is_array($relData->data)) {
                throw new \Exception("Invalid 1:n relation '$relName' for '$table'",400);
            }

            if ($relSpec["type"]=="outbound" && !is_object($relData->data) ) {
                throw new \Exception("Invalid 1:1 relation '$relName' for '$table'",400);
            }


            // inbound relation (1:n) add to stack for later insert
            if(is_array($relData->data) && $relSpec["type"]=="inbound") {
                $one2nRelations[$relName] = [
                    "data"=>$relData->data,
                    "spec"=>$relSpec
                ];
                continue;
            }

            //////////////////////////////////////////////////
            // continue with outbound relation (1:1) processing
            // validate object structure
            ///////////////////////////////////////////////////

            if(!$this->dm->is_valid_field($table,$relName))
                throw new \Exception("Invalid 1:1 relation '$relName' for '$table'",400);

//            if(!isset($relData->data->type))
//                throw new \Exception("Invalid relationship data: missing '$relName' type",400);

            $fk = (object)$this->dm->get_outbound_relation($table,$relName);

            // todo: data obfuscation ...........
//            if($fk->table!==$relData->data->type)
//                throw new \Exception("Invalid relationship data: invalid type for relationship '$relName'",400);

            $newPath = $path==null?$relName:$path.".$relName";
            if(isset($relData->data->id)) {
                // related record exists already; just set the id and continue
                // still... it does not check if it actually exists... but on insert it will throw an error if ID is fake
                if(!in_array($newPath,$includes))
                    $includes[] = $newPath;
                $insertData[$relName] = $relData->data->id;
                continue;
            }

            // create 1:1 related record
            if(isset($relData->data->attributes)) {

                $insertData[$relName] = $this->insert($fk->table,$relData->data,$watchDog-1,$onDuplicate,$insertIgnore,$fieldsToUpdate,$newPath,$includes);
                if(!in_array($newPath,$includes)) {
                    $includes[] = $newPath;
                }
            }
        }

        $insertData = $this->validate_insert_data($table,$insertData);


//        // call oninsert hook
//        $tableConfig = $this->dm->get_config($table);
//        if(isset($tableConfig["oninsert"]) && is_callable($tableConfig["oninsert"])) {
//            $insertData = $tableConfig["oninsert"]($insertData,$tableConfig);
//        }


        // before insert hook
        $beforeInsert = @include($this->configDir."/hooks/".$table."/before.insert.php");
        if(is_callable($beforeInsert)) {
            $insertData = $beforeInsert($this, $insertData);
        }


        // check insert data for non-scalar values and throw error in case found
        foreach ($insertData as $key=>$value) {
            if($value!==null && !is_scalar($value)) {
                throw new \Exception("Invalid value for $key: ".json_encode($value));
            }
            $this->dbdrv->set($key,$value);
        }

        $insSql = $this->dbdrv->get_compiled_insert($table);



        if($insertIgnore) {
            $insSql = str_replace("INSERT INTO","INSERT IGNORE INTO",$insSql);
        }
//        echo $insSql;
        // todo: should put this in an external file: configure behaviour to update fields (database specific)
        switch ($onDuplicate) {
            case "update":
                if (empty($fieldsToUpdate[$table]))
                    break;

                $updStr = [];
                foreach ($fieldsToUpdate[$table] as $fld) {
                    if (!$this->dm->field_is_updateable($table, $fld)) {
                        throw new \Exception("ON DUPLICATE UPDATE failure: not allowed to update field '$fld'", 400);
                    }
                    $updStr[] = "`$fld`=VALUES(`$fld`)";
                }

                if(count($updStr))
                    $insSql .= " ON DUPLICATE KEY UPDATE " . implode(",", $updStr);
                break;
            case "ignore":
                if($idFld)
                    $insSql .= " ON DUPLICATE KEY UPDATE `$idFld`=`$idFld`";
                break;
            case "error":
                break;
            default:
                throw new \Exception("Invalid 'onduplicate' parameter value.");
        }

        // insert data in DB

        $this->dbdrv->db_debug = false;

        //echo $insSql;
        $res = $this->dbdrv->query($insSql);
        $err=$this->dbdrv->error();
        if($err["code"]){
            throw new \Exception($err["message"]." on query: $insSql",500);
        }

        //$this->dbdrv->db_debug = true;



        // retrieve resource ID (mysql specific)
        // todo: evaluate impact for other DB engines and implement
        $newRecId = $this->dbdrv->insert_id();


        if($this->dbdrv->affected_rows()!==1) {

        }

        if(!$newRecId && $this->dbdrv->affected_rows()===1 && is_scalar($insertData[$idFld])) {
            $newRecId = $insertData[$idFld];
        }

        if(!$newRecId) {
            $selSql = $this->dbdrv
                ->where($insertData)
                ->get_compiled_select($table);

            $q = $this->dbdrv->query($selSql);
            $err=$this->dbdrv->error();
            if($err["code"]){
                throw new \Exception($err["message"]." on query: $selSql",500);
            }

            $cnt = $q->num_rows();
            if($cnt > 1) {
                log_message("error", "More then one records returned on Insert new record: $insSql / $selSql");
                return null;
            }

            $newRecId = $q->row()->$idFld;
        }

        $afterInsert = @include($this->configDir."/hooks/".$table."/after.insert.php");
        if(is_callable($afterInsert))
            $afterInsert($this,$newRecId,$insertData);

        // create outbound relations
        if($newRecId && $one2nRelations) {
            foreach ($one2nRelations as $relName=>$relData) {
                $relationPeerTable = $relData["spec"]["table"];
                $relationPeerField = $relData["spec"]["field"];
                $rels = $relData["data"];

                $newPath = $path==null ? $relName : "$path.$relName";

                // iterate through data
                foreach ($rels as $relItem){
                    // check relation data type
                    $objType = $this->get_object_type($relItem);
                    $fkFld = $relationPeerField;

                    if(!$this->dm->resource_allow_update($relationPeerTable)) {
                        throw new \Exception("Not allowed to update relationship $relName on $table",403);
                    }

                    if(!in_array($newPath,$includes)) {
                        $includes[] = $newPath;
                    }

                    switch ($objType) {
                        // data is a resource indicator object = related record exist already
                        // => perform an update with the id of the newly created object for the FK field
                        case "ResourceIndicatorObject":
                            // update related record

                            $relItem->attributes = (object) [
                                $relationPeerField=>$newRecId
                            ];

                            $this->update_by_id($relationPeerTable,$relItem->id,$relItem);
                            break;
                            // data is of newResourceObject type => new related record must be created
                        case "newResourceObject":
                            // insert new related record

                            $relItem->attributes->$fkFld = $newRecId;

                            $this->insert($relationPeerTable,$relItem,$watchDog-1,$onDuplicate,$insertIgnore,$fieldsToUpdate,$newPath,$includes);
                            break;
                        case "DocumentObject":
                            break;
                        default:
                            throw new \Exception("Invalid '$relName' relationship data type ($objType) : ".json_encode($relItem),403);
                    }
                }
            }
        }

        return $newRecId;
    }


    /**
     * @param $table
     * @param $attributes
     * @param $paras
     * @return mixed
     * @throws \Exception
     */
    function update_attributes_by_filter($table, $attributes, $paras)
    {
        if(array_key_exists("custom_where",$paras))
            $where = $paras["custom_where"];
        else
            $where = $this->generate_where_sql($paras["filter"]);

        return $this->update_attributes($table,$attributes,$where);
    }

    /**
     * @param $table
     * @param $attributes
     * @param $where
     * @return mixed
     * @throws \Exception
     */
    function update_attributes($table, $attributes, $where)
    {

//        print_r([$table,$attributes,$where]);
//        $config = $this->dm->get_config($table);
        foreach ($attributes as $key=>$val) {
            if(!$this->dm->is_required($table,$key) && !$val) {
                $attributes[$key] = null;
            }
        }

        // before insert hook
        $beforeUpdate = @include($this->configDir."/hooks/".$table."/before.update.php");
        if(is_callable($beforeUpdate))
            $attributes = $beforeUpdate($this,$where,$attributes);

        // configure query
        $updateSql = $this->dbdrv
            ->where($where)
            ->set($attributes)
            ->get_compiled_update($table);


        $this->dbdrv->query($updateSql);
        $err=$this->dbdrv->error();
        if($err["code"]){
            throw new \Exception($err["message"]." on query: $updateSql",500);
        }

        // perform update

        // after insert hook
        $afterUpdate = @include($this->configDir."/hooks/".$table."/after.update.php");
        if(is_callable($afterUpdate))
            $afterUpdate($this,$where,$attributes);

        return $this->dbdrv->affected_rows();
    }

    /**
     * @param string $table
     * @param string $id
     * @param array $attributes
     * @return string mixed
     * @throws \Exception
     */
    private function update_attributes_by_id($table, $id, $attributes)
    {
        $priKey = $this->dm->get_primary_key($table);

        $keyFields = $this->dm->get_key_flds($table);

        $whereArr = array();

        // check for duplicates on key fields
        // @todo: contemplate if this code is really needed. Maybe a simple capture of the mysql error should do the job
        // build where part with key fields
        foreach($attributes as $name=>$value) {
            if(in_array($name,$keyFields)) {
                $whereArr[] = "$name='$value'";
            }
        }

        // run the query
        if(count($whereArr)) {
            $sql = "SELECT * FROM $table WHERE $priKey!='$id' AND (".implode(" OR ",$whereArr).")";

            $res = $this->dbdrv->query($sql);
            $err=$this->dbdrv->error();
            if($err["code"]){
                throw new \Exception($err["message"]." on query: $sql",500);
            }

            if($res->num_rows()) {
                throw new \Exception("Duplicate key fields for $table/$id: ".json_encode($attributes),409);
            }
        }

        if(!$this->update_attributes($table,$attributes,[$priKey=>$id]))
            return  null;

        if(isset($attributes[$priKey]))
            return $attributes[$priKey];

        return $id;

    }

    /**
     * @param string $table
     * @param string $id
     * @param array $relationships
     */
    function updateRelations($table, $id, $relationships) {

    }

    /**
     * update Record
     * @param $table
     * @param $id
     * @param $resourceData
     * @return string
     * @throws \Exception
     */
    function update_by_id($table, $id, $resourceData) {

        if(!$this->dm->get_primary_key($table))
            throw new \Exception("Update by ID not allowed: table '$table' does not have primary key/unique field.",500);

        // extract 1:1 relation data and insert (or update)
        if(isset($resourceData->relationships)) {
            foreach ($resourceData->relationships as $relName => $relData) {
                $relData = $relData->data;
                $relSpec = $this->dm->get_relationship($table, $relName);

                if ($relSpec["type"] === "outbound") {
//                    log_message("debug",print_r($resourceData,true));
                    if (!isset($resourceData->attributes))
                        $resourceData->attributes = new \stdClass();

                    if($relData === null) {
                        $resourceData->attributes->$relName = null;
                        continue;
                    }
                    if (!isset($relData->type)) {
                        $relData->type = $relSpec["table"];
                    }
//                        throw new \Exception("Invalid empty data type for relation '$relName' of record ID $id of type $table", 400);

                    if (isset($relData->id) && $relData->id !== null) {
                        $this->update_by_id($relData->type, $relData->id, $relData);
                        $resourceData->attributes->$relName = $relData->id;
                        continue;
                    }

                    if ($relData->type !== $relSpec["table"])
                        throw new \Exception("Invalid data type for relation '$relName' of record ID $id of type $table", 400);

                    $includes = [];
                    //                echo "inserting";
                    //                print_r($relData);
                    $flds = $this->dm->get_fields($relData->type);
                    array_splice($flds, array_search( $this->dm->get_primary_key($relData->type), $flds),1);
                    $resourceData->attributes->$relName = $this->insert($relData->type, $relData, get_instance()->get_max_insert_recursions(),
                        "update", false, $flds, null, $includes);
                    continue;
                }

                if ($relSpec["type"] === "inbound") {
                    if(!is_array($relData)) {
                        throw new \Exception("Invalid relation data '$relName' of record ID $id of type $table: not an array",400);
                        
                    }
                    foreach ($relData as $item) {
                        $this->update_by_id($item->type,$item->id,$item);
                    }
                }
            }
        }


        if(isset($resourceData->attributes) && count(get_object_vars($resourceData->attributes))) {
            $resourceData->attributes = $this->dm->validate_object_attributes($table, $resourceData->attributes, "upd");
            return $this->update_attributes_by_id($table,$id,$resourceData->attributes);
        }

        // todo: update 1:n relationships
//        if()



        return  $id;
    }


    /**
     * delete Record id $id from $database/$table
     *
     * @param string $tableName
     * @param string $recId
     *
     * @return bool
     * @throws \Exception
     */
    function delete_by_id($tableName, $recId) {
        // check if resource exists
        if(!$this->dm->resource_exists($tableName))
            throw new \Exception("Resource '$tableName' not found",404);

        if(!$this->dm->resource_allow_delete($tableName))
            throw new \Exception("Not authorized to delete from $tableName",401);

        $idFld = $this->dm->get_primary_key($tableName);

        $this->dbdrv->where("$idFld in ('$recId')");
        $this->dbdrv->delete($tableName);

        $err=$this->dbdrv->error();
        if($err["code"]){
            throw new \Exception($err["message"]." on delete record $tableName:$recId",500);
        }
        if($this->dbdrv->affected_rows()) {
            return true;
        }
        throw new \Exception("Record not found",404);
    }

    /**
     * @param $tableName
     * @param $recId
     * @return int
     */
    function delete_record_by_id($tableName,$recId) {
        $idFld = $this->dm->get_primary_key($tableName);
        $this->dbdrv->where($idFld, $recId)->delete($tableName);
        return $this->dbdrv->affected_rows();
    }
    /**
     * @param string $tableName
     * @param array $where
     * @return bool
     * @throws \Exception
     */
    function delete_by_where($tableName, $filter)
    {
        // check if resource exists
        if(!$this->dm->resource_exists($tableName))
            throw new \Exception("Resource '$tableName' not found",404);

        if(!$this->dm->resource_allow_delete($tableName))
            throw new \Exception("Not authorized to delete from $tableName",401);

        echo "------------------\n";
//        print_r($filter);
        echo $tableName;
        $where = $this->generate_where_sql($filter[$tableName],$filter[$tableName]);

        $this->dbdrv->where($where);
        $this->dbdrv->delete($tableName);
        $err=$this->dbdrv->error();
        if($err["code"]){
            throw new \Exception($err["message"]." on delete by where from $tableName",500);
        }
        if($this->dbdrv->affected_rows())
            return true;

        throw new \Exception("Records not found",404);
    }

    /**
     * @param $obj
     * @return string
     * @throws \Exception
     */
    private function get_object_type($obj)
    {
        if(!is_object($obj))
            return "NoObject";

        if(property_exists($obj,"data"))
            return "DocumentObject";

//        if(property_exists($obj,"type"))
//            return "UnknownObject";

        if(property_exists($obj,"id"))
            return property_exists($obj,"attributes")?"ResourceObject":"ResourceIndicatorObject";
        else
            return property_exists($obj,"attributes")?"newResourceObject":"InvalidObject";
    }

}

function generate_where_condition($where, $alias) {
    // if element is not an object or left property of OBJ is not a field -> ignore -> return TRUE
    if(!is_object($where) || !property_exists($where,"left")){
        log_message("debug","invalid filter entry");
        return "FALSE";
    }


    $validOps = ["!=","=","<","<=",">",">=","><","~=","!~=","=~","!=~","<>","!><"];

    $where->right = $where->right=="NULL"?null:$where->right;

    switch($where->op) {
        case "><":
            $str = sprintf("`%s`.`%s` IN ('%s')",$alias,$where->left,str_replace(";","','",$where->right));
            break;
        case "!><":
            $str = sprintf("`%s`.`%s` NOT IN ('%s')",$alias,$where->left,str_replace(";","','",$where->right));
            break;
        case "~=":
            $str = sprintf("`%s`.`%s` LIKE ('%%%s')",$alias,$where->left,$where->right);
            break;
        case "!~=":
            $str = sprintf("`%s`.`%s` NOT LIKE ('%%%s')",$alias,$where->left,$where->right);
            break;
        case "=~":
            $str = sprintf("`%s`.`%s` LIKE ('%s%%')",$alias,$where->left,$where->right);
            break;
        case "!=~":
            $str = sprintf("`%s`.`%s` NOT LIKE ('%s%%')",$alias,$where->left,$where->right);
            break;
        case "~=~":
            $str = sprintf("`%s`.`%s` LIKE ('%%%s%%')",$alias,$where->left,$where->right);
            break;
        case "!~=~":
            $str = sprintf("`%s`.`%s` NOT LIKE ('%%%s%%')",$alias,$where->left,$where->right);
            break;
        case "=":
            if($where->right==="__NULL__") {
                $str = sprintf("`%s`.`%s` IS NULL",$alias,$where->left);
            }
            else {
                $str = sprintf("`%s`.`%s` %s %s",$alias,$where->left,$where->op,($where->right!==""?"'".$where->right."'":"NULL"));
            }
            break;
        case "!=":
            if($where->right==="__NULL__") {
                $str = sprintf("`%s`.`%s` IS NOT NULL",$alias,$where->left);
            }
            else {
                $str = sprintf("`%s`.`%s` %s %s",$alias,$where->left,$where->op,($where->right!==""?"'".$where->right."'":"NULL"));
            }
            break;

        default:
            if(in_array($where->op,$validOps))
                $str = sprintf("`%s`.`%s` %s %s",$alias,$where->left,$where->op,($where->right!==""?"'".$where->right."'":"NULL"));
            else
                $str = "TRUE";
    }

    return $str;
}

function recursive_generate_select_and_joins(&$req, &$fields, &$join, $tableAlias, $parentTableAlias="",$offset=0)
{
    $req->selectFieldsOffset = $offset;
    foreach ($req->fields as $fld) {
        $fields[] = "`$tableAlias`.`$fld`";
    }

    // n:1 relationship -> skip
//    echo "reqqqq....\n";
//    print_r($req);
    if($req->relSpec && $req->relSpec["type"]=="outbound") {
//        echo "join....\n";
        $join[] = "LEFT JOIN `$req->resourceName` AS `$tableAlias` ".
            "ON `$tableAlias`.`{$req->relSpec['field']}`=`$parentTableAlias`.`{$req->relSpec['fkfield']}`";
    }

    foreach ($req->sort as $idx=>$sort) {
        $req->sort[$idx] = "`$tableAlias`.$sort";
    }


    $offset += count($req->fields);
    foreach ($req->include as $relName=>$relReq) {
        if($relReq->relSpec["type"]=="outbound") {
            $offset += recursive_generate_select_and_joins($relReq, $fields, $join,
                $tableAlias . "_" . $relName, $tableAlias, $offset);
        }
    }
    return count($req->fields);
}