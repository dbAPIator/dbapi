<?php
namespace Apiator\DBApi;

//require_once(__DIR__."/../../../libraries/Response.php");

use Response;

/**
 * Class Data_model
 */
class Datamodel {
    private $valid = false;
    private $dataModel = null;

    private $default_resource_access_read = true;
    private $default_resource_access_update = true;
    private $default_resource_access_insert = true;
    private $default_resource_access_delete = true;

    private $default_field_access_insert = true;
    private $default_field_access_update = true;
    private $default_field_access_select = true;
    private $default_field_access_sort = true;
    private $default_field_access_search = true;


    function is_valid_model() {
        return $this->valid;
    }

    function get_dataModel()
    {
        return $this->dataModel;
    }

    function get_idfld($table) {
        return $this->dataModel[$table]["keyFld"];
    }

    function __construct($dataModel) {
        $ci = get_instance();

        if(($tmp=$ci->config->item("default_resource_access_read"))!==null)
            $this->default_resource_access_read = $tmp;
        if(($tmp=$ci->config->item("default_resource_access_insert"))!==null)
            $this->default_resource_access_insert = $tmp;
        if(($tmp=$ci->config->item("default_resource_access_update"))!==null)
            $this->default_resource_access_update = $tmp;
        if(($tmp=$ci->config->item("default_resource_access_delete"))!==null)
            $this->default_resource_access_delete = $tmp;

        if(($tmp=$ci->config->item("default_field_access_select"))!==null)
            $this->default_field_access_select = $tmp;
        if(($tmp=$ci->config->item("default_field_access_insert"))!==null)
            $this->default_field_access_insert = $tmp;
        if(($tmp=$ci->config->item("default_field_access_search"))!==null)
            $this->default_field_access_search = $tmp;
        if(($tmp=$ci->config->item("default_field_access_sort"))!==null)
            $this->default_field_access_sort = $tmp;
        if(($tmp=$ci->config->item("default_field_access_update"))!==null)
            $this->default_field_access_update = $tmp;

        $this->dataModel = $dataModel;
    }

    /**
     * check if $database exists and initializes the DB
     * @param array $structure
     * @return Datamodel|null
     */
    static function init($structure) {
        if(is_array($structure))
            return new Datamodel($structure);
        return null;
    }

    /**
     * get table fields
     * @param string $resourceName table nam
     * @return array|null
     */
    function getResourceFields($resourceName) {
        return $this->dataModel[$resourceName]["fields"];
    }

    /**
     * @param $resName
     * @param $fldName
     * @return bool
     * @throws \Exception
     */
    function field_is_selectable($resName, $fldName)
    {
        if(!isset($this->dataModel[$resName]["fields"][$fldName]))
            throw new \Exception("Invalid field $fldName (is_selectable)",400);


        return isset($this->dataModel[$resName]["fields"][$fldName]["select"]) ?
            $this->dataModel[$resName]["fields"][$fldName]["select"] :
            $this->default_field_access_select;
    }

    /**
     * @param $resName
     * @param $fldName
     * @return bool
     * @throws \Exception
     */
    function field_is_insertable($resName, $fldName)
    {
        //print_r($this->dataModel[$resName]);
        if(!isset($this->dataModel[$resName]["fields"][$fldName]))
            throw new \Exception("Invalid field $resName.$fldName (is_insertable)",400);

        return isset($this->dataModel[$resName]["fields"][$fldName]["insert"])?
            $this->dataModel[$resName]["fields"][$fldName]["insert"]:
            $this->default_field_access_insert;
    }

    /**
     * @param $resName
     * @param $fldName
     * @return bool
     * @throws \Exception
     */
    function field_is_updateable($resName, $fldName)
    {
        if(!isset($this->dataModel[$resName]["fields"][$fldName]))
            throw new \Exception("Invalid field $fldName (is_updateable)",400);

        return isset($this->dataModel[$resName]["fields"][$fldName]["update"])?
            $this->dataModel[$resName]["fields"][$fldName]["update"]:
            $this->default_field_access_update;
    }

    /**
     * @param $resName
     * @param $fldName
     * @return bool
     * @throws \Exception
     */
    function field_is_sortable($resName, $fldName)
    {
//        print_r($this->dataModel[$resName]["fields"][$fldName]);
        if(!isset($this->dataModel[$resName]["fields"][$fldName]))
            throw new \Exception("Invalid field $fldName (is_sortable)",400);
        return isset($this->dataModel[$resName]["fields"][$fldName]["sortable"])?
            $this->dataModel[$resName]["fields"][$fldName]["sortable"]:
            $this->default_field_access_sort;
    }

    /**
     * @param $resName
     * @param $fldName
     * @return bool
     * @throws \Exception
     */
    function field_is_searchable($resName, $fldName)
    {
        if(!isset($this->dataModel[$resName]["fields"][$fldName]))
            throw new \Exception("Invalid field $resName.$fldName (is_searchable)",400);

        return isset($this->dataModel[$resName]["fields"][$fldName]["searchable"])?
            $this->dataModel[$resName]["fields"][$fldName]["searchable"]:
            $this->default_field_access_search;
    }




    /**
     * gets configuration of table or full model when no tableName is provided
     *
     * @param string $tableName table name
     * @return Object config model
     *
     */
    function get_config($tableName=null) {
        if(is_null($tableName))
            return $this->dataModel;

        return $this->dataModel[$tableName];
    }

    /**
     * return name of the field used as primary key
     * @param $resName
     * @return mixed
     */
    function getPrimaryKey($resName)
    {
        $keyFld =  isset($this->dataModel[$resName]["keyFld"])?$this->dataModel[$resName]["keyFld"]:null;
        return $keyFld;
    }



    /**
     * get relation target table name
     * @param string $tableName source table name
     * @param string $relationName relation name
     * @return string target table name or null when relation name not found
     */
    private function get_rel_target_tbl($tableName,$relationName) {
        if($this->is_valid_relation($tableName,$relationName))
            return $this->dataModel[$tableName]["relations"][$relationName]["table"];
        return null;
    }

    /**
     * @alias get_rel_target_tbl()
     * @param string $tableName
     * @param string $relationName
     * @return string
     */
    function get_relation_target_table($tableName,$relationName)  {
        return $this->get_rel_target_tbl($tableName,$relationName);
    }


    /**
     * get relation link table name
     * @param string $tableName source table name
     * @param string $relationName relation name
     * @return string|null
     */
    function get_rel_link_tlb($tableName,$relationName) {
        if($this->is_valid_relation($tableName,$relationName))
            return $this->dataModel[$tableName]["relations"][$relationName]["lnkTable"];
        return null;
    }


    /**
     * checks if resource exists
     * @param string $name
     * @return boolean true if exists, false otherwise
     */
    function resource_exists($name) {
        return array_key_exists($name,$this->dataModel);
    }

    /**
     * checks if table exists
     * @param string $name
     * @return boolean true if exists, false otherwise
     */
    function is_valid_view($name) {
        return array_key_exists($name,$this->dataModel) && $this->dataModel[$name]["type"]=="view";
    }


    /**
     * checks if relation is valid
     * @param string $tableName source table name
     * @param string $relationName relation nam
     * @return boolean true if exists, false otherwise
     */
    function is_valid_relation($tableName,$relationName) {
        return array_key_exists("relations",$this->dataModel[$tableName]) &&
            array_key_exists($relationName,$this->dataModel[$tableName]["relations"]);

    }

    /**
     * @param $table
     * @param $relationName
     * @param $relatedTable
     * @return bool
     */
    function is_valid_related_table($table,$relationName,$relatedTable) {
        if(!$this->is_valid_relation($table,$relationName))
            return false;
        return $this->dataModel[$table]["relations"][$relationName]["table"]==$relatedTable;
    }

    /**
     * validate field name against datamodel
     *
     * @param string $tableName
     * @param string $fieldName
     * @return bool
     */
    function is_valid_field($tableName, $fieldName) {
        return $this->resource_exists($tableName) && array_key_exists($fieldName,$this->dataModel[$tableName]["fields"]);
    }

    /**
     * @param $tableName
     * @return array
     */
    function get_key_flds($tableName) {
        $keys = [];
        foreach($this->dataModel[$tableName]["fields"] as $fldName=> $fldSpec)
            if($fldSpec["iskey"])
                $keys[] = $fldName;

        return $keys;
    }

    /**
     * @param $resName
     * @param $relName
     * @return Response
     * @throws \Exception
     */
    function get_outbound_relation($resName, $relName) {

        if(!$this->resource_exists($resName))
            throw new \Exception("Invalid resource $resName",400);

        if(!isset($this->dataModel[$resName]["relations"][$relName]))
            throw new \Exception("Invalid relationship name '$relName'",400);

        if($this->dataModel[$resName]["relations"][$relName]["type"]!=="outbound")
            throw new \Exception("Invalid outbound relationship '$relName'",400);

        return $this->dataModel[$resName]["relations"][$relName];
    }

    /**
     * @param $table
     * @param $relName
     * @return mixed
     * @throws \Exception
     */
    function get_relationship($table,$relName)
    {
        if (!$this->resource_exists($table))
            throw new \Exception("Invalid resource $table", 400);
        if (!isset($this->dataModel[$table]["relations"])
            || !isset($this->dataModel[$table]["relations"][$relName]))
            throw new \Exception("Relationship $relName of $table not found",404);

        return $this->dataModel[$table]["relations"][$relName];
    }

    function is_required($resName,$field) {
        $field = $this->dataModel[$resName]["fields"][$field];
//        print_r($field);
        return $field["required"];
    }

    /**
     * type validation & type casting of proposed value against field type
     *
     * @param string $tableName table name
     * @param string $fieldName field name
     * @param mixed $value value to be validated
     * @return mixed
     * @throws \Exception
     */
    function is_valid_value($tableName,$fieldName,$value) {

        $mysqlTypes = [
            "numeric"=>[
                "int","tinyint","smallint","mediumint","int","bigint","decimal","float","double","real","bit","boolean","serial"
            ],
            "date"=>[
                "date","datetime","timestamp","time","year"
            ],
            "string"=>[
                "char","varchar","tinytext","text","mediumtext","longtext",
                "binary","varbinary","tinyblob","mediumblob","blob","longblob","enum","set"
            ],
            "spatial"=>[
                "geometry","point","linestring","polygon","multipoint","multilinestring","multipolygon","geometrycollection"
            ],
            "json"=>["json"]
        ];
        // $boolValid = array("1"=>true,"0"=>false,1=>true,0=>false,true=>true,false=>false,"true"=>true,"false"=>false);

        if(!$this->is_valid_field($tableName,$fieldName))
            throw new \Exception("Invalid field $tableName.$fieldName",400);

        $fields = $this->getResourceFields($tableName);

        if($value==="") {
            if(in_array($fields[$fieldName]["type"]["proto"],$mysqlTypes["numeric"]))
                $value = null;
            elseif (in_array($fields[$fieldName]["type"]["proto"],$mysqlTypes["date"]))
                $value = null;
        }

        // ToDO: implement length check
        // $length = property_exists($fields->$fieldName->type,"length") ? $fields->$fieldName->type->length : null;

        if(!$fields[$fieldName]["required"] && $value=="")
            return null;

        //print_r($value);

        if(is_object($value)) {
            if (array_key_exists("foreignKey", $fields[$fieldName])
                && $fields[$fieldName]["foreignKey"]["table"] == $value->data->type) {
                return $value;
            }
            else
                throw new \Exception("Invalid object as field value for  $tableName.$fieldName",400);

        }

        switch($fields[$fieldName]["type"]["proto"]) {

            // numeric types
            case "smallint":
            case "mediumint":
            case "int":
            case "bigint":
            case "decimal":
            case "tinyint":
                if(preg_match("/^-?\d+$/",$value)) {
                    return $value*1;
                }
                break;
            case "bit":
                if(preg_match("/^[0,1]+$/",$value)) {
                    return floatval($value);
                }
                break;
            case "real":
            case "double":
            case "float":
                if(preg_match("/^-?\d+(\.\d+)?$/",$value)) {
                    return $value*1;
                }
                break;
            case "boolean":
                if(is_bool($value)) {
                    return boolval($value);
                }

                $boolmap = ["true"=>true,"1"=>true,"0"=>false,"false"=>false,1=>true,0=>false];
                if(isset($boolmap[$value]))
                    return $boolmap[$value];
                break;
            case "serial":
                if(is_numeric($value)) {
                    return $value;
                }
                break;
            // DATE & TIME
            case "datetime":
                if(preg_match("/^\d{4}-\d{1,2}-\d{1,2}( (\d{1,2}(:\d{1,2}(:\d{1,})?)?)?){0,1}$/",$value))
                    return $value;
                break;
            case "date":
                if(preg_match("/^\d{4}-\d{2}-\d{2}$/",$value))
                    return $value;
                break;
            case "timestamp":
                if(preg_match("/^\d{4}-\d{1,2}-\d{1,2}( (\d{1,2}(:\d{1,2}(:\d{1,})?)?)?){0,1}$/",$value))
                    return $value;
                break;
            case "time":
                if(preg_match("/^-?\d{2,3}:\d{2}:\d{2}$/i",$value))
                    return $value;
                break;
            case "year":
                if(is_numeric($value) && ($value*1)<9999)
                    return $value;
                break;
            case "json":
                json_decode($value);
                if(json_last_error()===JSON_ERROR_NONE) {
                    return $value;
                }
                break;
            // TEXT
            case "char":
                return $value;
            case "varchar":
                return $value;
            case "tinytext":
                return $value;
            case "text":
                return $value;
            case "mediumtext":
                return $value;
            case "longtext":
                return $value;
            case "binary":
                return $value;
            case "varbinary":
                return $value;
            case "tinyblob":
                return $value;
            case "mediumblob":
                return $value;
            case "blob":
                return $value;
            case "longblob":
                return $value;
            // SET
            case "set":
                if(in_array($value,$fields[$fieldName]["type"]["vals"]))
                    return $value;
                break;
            case "enum":
                if(in_array($value,$fields[$fieldName]["type"]["vals"]))
                    return $value;
                break;
            default:
                throw new \Exception("Invalid type in configuration file for $fieldName",500);

        }
//        die("---------");
        // check if field is required but value is null and set error message accordingly
        if($fields[$fieldName]["required"] && is_null($value)) {
            $msg = "Field $tableName.$fieldName is required";
        }
        else {
            $msg = "Invalid type ".$fields[$fieldName]["type"]["proto"]." of value '$value' for field $tableName.$fieldName";
        }

        throw new \Exception($msg,400);
    }


    /**
     * checks if
     * @param string $resName
     * @param array $relations
     * @return array
     */
    function validate_object_relationships($resName, $relations) {

        // iterate through object properties
        foreach($relations as $relName=> $relData) {

            // is it a valid relation?
            if($this->is_valid_relation($resName,$relName)) {

                // validate $relData & $relData->data to be objects
                if(!is_object($relData) || !property_exists($relData,"data")) {
                    unset($relations->$relName);
                    break;
                }

                $relData = is_object($relData->data)?array($relData->data):(is_array($relData->data)?$relData->data:false);
                if($relData===false) {
                    unset($relations->$relName);
                    break;
                }
                foreach($relData as $idx=>$rel) {
                    if(property_exists($rel,"type") && property_exists($rel,"id")) {
                        if($this->is_valid_related_table($resName,$relName,$rel->type)) {
                            break;
                        }
                    }
                    unset($relations->$relName->data[$idx]);
                }
            }
        }

        return array(true,$relations);
    }


    /**
     * Validates if a field is foreignKey
     * @param string $resName
     * @param string $field
     * @return bool
     */
    function is_fk_field($resName, $field) {
        return array_key_exists("foreignKey",$this->dataModel[$resName]["fields"][$field]);
    }


    /**
     * @param $resName
     * @param $attributes
     * @param string $operation
     * @return mixed
     * TODO: review this method
     * @throws \Exception
     */
    function validate_object_attributes($resName, $attributes, $operation="ins") {

        if(!$this->resource_exists($resName))
            throw new \Exception("table '$resName' not found",400);

        if(is_object($attributes))
            $attributes = (array) $attributes;
        //if(!isset($data->attributes)) throw new

        $attributesNames = array_keys($attributes);

        foreach($this->dataModel[$resName]["fields"] as $fldName=> $fldSpec) {
            if($fldSpec["required"] && is_null($fldSpec["default"]) && !in_array($fldName,$attributesNames) && $operation=="ins")
                throw new \Exception("Required attribute '$fldName' of '$resName' not provided",400);

            // field not allowed to insert
            if(in_array($fldName,$attributesNames) && !$this->field_is_insertable($resName,$fldName) && $operation=="ins")
                throw new \Exception("Attribute '$fldName' not allowed to be inserted",400);

            // field not allowed to update
            if(in_array($fldName,$attributesNames) && !$this->field_is_updateable($resName,$fldName) && $operation=="upd")
                throw new \Exception("Attribute '$fldName' not allowed to be updated",400);

        }

        foreach($attributes as $attrName=> $attrVal) {
            // todo: validate when value is null against allow null
            $attrVal = $this->is_valid_value($resName,$attrName,$attrVal);

            /**
             * TODO: instead of just checking if value is an object as exception when value type validation fails
             *       implement a proper mechanism inside the is_valid_value method
             */

            if(!is_object($attrVal))
                $attributes[$attrName] = $attrVal;
        }
        return $attributes;
    }



    /**
     * validate if field is key field
     * @param string $resName resource name
     * @param string $fieldName field name
     * @return bool
     */
    function is_key_field($resName, $fieldName) {
        $res = $this->is_valid_field($resName,$fieldName);
        if(!$res)
            return false;

        return $this->dataModel->$resName->fields->$fieldName->iskey;
    }

    /**
     * @param $tableName
     * @return array
     * @throws \Exception
     */
    public function get_selectable_fields ($tableName)
    {
        if(!isset($this->dataModel[$tableName]))
            throw new \Exception("Invalid table $tableName",404);

        $fields = [];
        foreach ($this->dataModel[$tableName]["fields"] as $fldName=>$fldSpec) {
            if($this->field_is_selectable($tableName,$fldName)) {
                $fields[] = $fldName;
            }
        }

        return $fields;
    }

    /**
     * @param $tableName
     * @param $relName
     * @return mixed
     * @throws \Exception
     */
    public function get_inbound_relation ($tableName,$relName)
    {
        //echo "$tableName $relName";
        if(!isset($this->dataModel[$tableName]["relations"]) || !isset($this->dataModel[$tableName]["relations"][$relName]))
            return null;

        if($this->dataModel[$tableName]["relations"][$relName]["type"]=="inbound")
            return $this->dataModel[$tableName]["relations"][$relName];
        return null;

    }

    /**
     * @param $resName
     * @return bool
     */
    public function resource_allow_read($resName)
    {

        $res =  isset($this->dataModel[$resName]["read"]) ?
            $this->dataModel[$resName]["read"] :
            $this->default_resource_access_read;
//        echo $this->default_resource_access_read;
        return  $res;
    }

    /**
     * @param $resName
     * @return bool
     */
    public function resource_allow_insert ($resName)
    {
        return isset($this->dataModel[$resName]["insert"]) ?
            $this->dataModel[$resName]["insert"] :
            $this->default_resource_access_insert;

    }

    /**
     * @param $resourceName
     * @return bool
     */
    public function resource_allow_update ($resourceName)
    {
        return isset($this->dataModel[$resourceName]["update"]) ?
            $this->dataModel[$resourceName]["update"] :
            $this->default_resource_access_update;
    }

    /**
     * @param $resName
     * @return bool
     */
    public function resource_allow_delete ($resName)
    {
        return isset($this->dataModel[$resName]["delete"]) ?
            $this->dataModel[$resName]["delete"] :
            $this->default_resource_access_delete;
    }

    /**
     * @param $resName
     * @return array
     */
    public function get_fk_fields ($resName)
    {
        $fks = [];
//        foreach ($this->dataModel[$resName]["relations"] as $relName=>$relSpec) {
//            if($relSpec["type"]==="outbound")
//                $fks[$]
//        }
        foreach ($this->dataModel[$resName]["fields"] as $fieldName=>$fieldSpec) {
            if(isset($fieldSpec["foreignKey"]))
                $fks[$fieldName] = $fieldSpec["foreignKey"];
        }
        return $fks;
    }

    /**
     * @param $tableName
     * @return null
     */
    public function get_inbound_relations ($tableName)
    {
        if(!isset($this->dataModel[$tableName]["relations"])) {
            return [];
        }
        return array_filter($this->dataModel[$tableName]["relations"],
            function($item) {
                return $item["type"]=="inbound";
            }
        );
    }


}

function array_key_exists_and_has_value($array,$key,$value) {
    return array_key_exists($key,$array) && $array[$key] = $value;
}