<?php

require_once(__DIR__."/../libraries/Response.php");
//
///**
// * @param int $httpCode
// * @param string $payload
// * @param string $location
// * @param string $encoding
// * @return null
// */
//function http_respond($httpCode=200,$payload=null,$location=null,$encoding="application/json") {
//    http_response_code($httpCode);
//    header("Access-Control-Allow-Origin: *");
//    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");
//    header("Access-Control-Allow-Credentials: true");
//    header("Access-Control-Allow-Headers: origin, content-type, accept");
//    header("Cache-Control: no-cache, no-store, must-revalidate");
//    header("Pragma: no-cache");
//    header("Expires: 0");
//
//    if($location)
//        header("Location: $location");
//
//    if($httpCode!=array("204"))
//        header("Content-type: $encoding");
//
//    echo $payload;
//    die();
//}
//
//
//
///**
// * @param int $length
// * @return string
// */
//function generateRandomString($length = 10) {
//    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
//    $charactersLength = strlen($characters);
//    $randomString = '';
//    for ($i = 0; $i < $length; $i++) {
//        $randomString .= $characters[rand(0, $charactersLength - 1)];
//    }
//    return $randomString;
//}



function generate_where_str($where) {
    //print_r($where);
    // if element is not an object or left property of OBJ is not a field -> ignore -> return TRUE
    if(!is_object($where) || !property_exists($where,"left")){
        log_message("debug","invalid filter entry");
        return "FALSE";
    }

    //if($where->right=="") return "TRUE";

    $validOps = ["!=","=","<","<=",">",">=","><","~=","!~=","=~","!=~","<>","!><"];

    $where->right = $where->right=="NULL"?null:$where->right;

    switch($where->op) {
        case "><":
            $str = sprintf("%s.%s IN ('%s')",$where->left->alias,$where->left->field,str_replace(";","','",$where->right));
            break;
        case "!><":
            $str = sprintf("%s.%s NOT IN ('%s')",$where->left->alias,$where->left->field,str_replace(";","','",$where->right));
            break;
        case "~=":
            $str = sprintf("%s.%s LIKE ('%%%s')",$where->left->alias,$where->left->field,$where->right);
            break;
        case "!~=":
            $str = sprintf("%s.%s NOT LIKE ('%%%s')",$where->left->alias,$where->left->field,$where->right);
            break;
        case "=~":
            $str = sprintf("%s.%s LIKE ('%s%%')",$where->left->alias,$where->left->field,$where->right);
            break;
        case "!=~":
            $str = sprintf("%s.%s NOT LIKE ('%s%%')",$where->left->alias,$where->left->field,$where->right);
            break;
        case "~=~":
            $str = sprintf("%s.%s LIKE ('%%%s%%')",$where->left->alias,$where->left->field,$where->right);
            break;
        case "!~=~":
            $str = sprintf("%s.%s NOT LIKE ('%%%s%%')",$where->left->alias,$where->left->field,$where->right);
            break;
        case "=":
            if($where->right==="__NULL__") {
                $str = sprintf("%s.%s IS NULL",$where->left->alias,$where->left->field);
            }
            else {
                $str = sprintf("%s.%s %s %s",$where->left->alias,$where->left->field,$where->op,($where->right!==""?"'".$where->right."'":"NULL"));
            }
            break;
        case "!=":
            if($where->right==="__NULL__") {
                $str = sprintf("%s.%s IS NOT NULL",$where->left->alias,$where->left->field);
            }
            else {
                $str = sprintf("%s.%s %s %s",$where->left->alias,$where->left->field,$where->op,($where->right!==""?"'".$where->right."'":"NULL"));
            }
            break;

        default:
            if(in_array($where->op,$validOps))
                $str = sprintf("%s.%s %s %s",$where->left->alias,$where->left->field,$where->op,($where->right!==""?"'".$where->right."'":"NULL"));
            else
                $str = "TRUE";
    }

    return $str;
}
//
///**
// * @param $from
// * @param $joins
// * @param null $whereArr
// * @param null $orderByArr
// * @param null $offset
// * @param null $limit
// * @param null $groupBy
// * @return array
// */
//function render_select_query($from, $joins, $whereArr=null, $orderByArr=null, $offset=null, $limit=null,$groupBy=null) {
//    // prepare selected fields statement
//    $fieldsArr = [];
//    $fieldsArr[] = $from["table"].".".implode(",".$from["table"].".",$from["fields"]);
//
//
//    // prepare JOIN statement
//    $joinArr = [];
//    foreach ($joins as $fld=>$join) {
//        $fieldsArr[] = $join["alias"].".".implode(",".$join["alias"].".",$join["fields"]);
//        $joinArr[] = sprintf("LEFT JOIN %s AS %s ON %s=%s",$join["table"], $join["alias"], $join["left"], $join["right"]);
//    }
//
//    // prepare where statement
//    $sqlWhere = 1;
//    if(!empty($whereArr))
//        $sqlWhere = implode(" AND ",$whereArr);
//
//    // prepare order statement
//    $sqlOrder = "1";
//    if(!empty($orderByArr))
//        $sqlOrder = implode(", ",$orderByArr);
//
//    // prepare limit statement
//    $sqlLimit = "";
//    if(is_numeric($offset) && is_numeric($limit))
//        $sqlLimit = "LIMIT $offset,$limit";
//
//    $sqlGroup = ($groupBy)?"GROUP BY $groupBy":"";
//
//    $countSql = sprintf("SELECT count(*) as cnt FROM %s WHERE %s %s",
//        $from["table"],
//        //implode(" ", $joinArr),
//        $sqlWhere,
//        $sqlGroup);
//    $mainSql = sprintf("SELECT %s FROM %s %s WHERE %s %s ORDER BY %s %s",
//        implode(",", $fieldsArr),
//        $from["table"],
//        implode(" ", $joinArr),
//        $sqlWhere,
//        $sqlGroup,
//        $sqlOrder,
//        $sqlLimit
//    );
//
//    return array($countSql,$mainSql);
//}

/**
 * extracts include from GET
 * @param CI_Input $input
 * @return array
 */
function get_include($input)
{
    if(empty($input->get("include")))
        return [];
    return explode(",",$input->get("include"));
}

///**
// * extracts fields from GET
// * @param CI_Input $input
// * @param string $defaultTable
// * @return array
// */
//function get_fields($input,$defaultTable)
//{
//    $fields = [];
//    if(empty($input->get("fields")))
//        return $fields;
//
//    $tmp = explode(",",$input->get("fields"));
//    for($i=0;$i<count($tmp);$i++) {
//        $t = explode(".",$tmp[$i]);
//        if(count($t)==1) {
//            $fields[$defaultTable][] = $tmp[$i];
//        }
//        elseif(count($t)==2) {
//            $fields[$t[0]][] = $t[1];
//        }
//    }
//
//    return $fields;
//}

///**
// * @param CI_Input $input
// * @param $defaultTable
// * @return array|null
// */
//function get_updatefields($input,$defaultTable) {
//    if(empty($input->get("update")))
//       return null;
//
//    $update = [];
//
//    foreach(explode(",",$input->get("update")) as $fld) {
//        $pair = explode(".",$fld);
//        $tbl = count($pair)==1?$defaultTable:$pair[0];
//        $fld = count($pair)==1?$pair[0]:$pair[1];
//        if(!isset($update[$tbl]))
//            $update[$tbl] = [];
//        if(!in_array($fld,$update[$tbl]))
//            $update[$tbl][] = $fld;
//    }
//    return $update;
//}

/**
 * extracts filters from GET
 * @param $rawFilterStr
 * @param string $defaultTable
 * @return array
 */
function get_filter($filters, $defaultTable)
{
    if(!is_array($filters)) {
        $filters = [
            $defaultTable => $filters
        ];
    }

    $parsedFilters = [];
    foreach ($filters as $table=>$filter) {
        $parsedFilters[$table] = [];
        // split string by comma and process each segment
        foreach(explode(",",$filter) as $idx=>$item) {
            $where = null;
            // regexp search to identify
            preg_match("/([\w\-\$]+)(\.([\w\-\$]+))?(\!?[\=\<\>\~]+)(.*)/",$item,$m);


            if(!empty($m)) {
                $alias = empty($m[3]) ? $table : $m[1];
                $fieldName = empty($m[3]) ? $m[1] : $m[3];
                $parsedFilters[$table][$alias.".".$fieldName.rand(0,1000)] = (object) [
                    "left"=>(object) [
                        "alias"=>$alias,
                        "field"=>$fieldName
                    ],
                    "op"=>$m[4],
                    "right"=>$m[5]
                ];
            }
        }
    }


//    print_r($filters);
//    if($_GET['dbg']) print_r($filters);
    return $parsedFilters;
}



/**
 * @param string $str
 * @param string $defaultTable
 * @return array
 */
function getSort($str, $defaultTable)
{
    // generate sort array
    if(!is_string($str))
        return [];
    $arr = explode(",",$str);
    foreach($arr as $item) {
        $dir = substr($item,0,1)=="-"?"DESC":"ASC";
        $fld = $dir=="DESC"?substr($item,1):$item;
        $tmp = explode(".",$fld);
        $sort[] = (object) [
            "alias" => count($tmp)>1?$tmp[0]:$defaultTable,
            "fld" => count($tmp)>1?$tmp[1]:$fld,
            "dir" => $dir
        ];
    }
    //print_r($sort);
    return $sort;
}


/**
 * @param $input
 * @param $defaultTableName
 * @return array
 */
function getFieldsToUpdate($input, $defaultTableName)
{
    $updateFields = [];

    $fldsFromQuery = explode(",",$input->get("update"));
    for($i=0;$i<count($fldsFromQuery);$i++) {
        $tmp = explode(".", $fldsFromQuery[$i]);
        if(count($tmp)==1) {
            $tbl = $defaultTableName;
            $fld = $tmp[0];
        }
        else
            list($tbl,$fld) = [$tmp];

        if(!$fld)
            continue;

        if(!isset($updateFields[$tbl]))
            $updateFields[$tbl] = [];
        $updateFields[$tbl][] = $fld;
    }
    return $updateFields;
}


/**
 * @param $arr
 * @return mixed
 */
function cleanUpArray($arr) {
    if(is_object($arr))
        $arr = (array) $arr;
    foreach ($arr as $key=>$val) {
        if(is_object($val))
            $val = (array) $val;
        if(is_array($val))
            if(count($val))
                $arr[$key] = cleanUpArray($val);
            else
                unset($arr[$key]);


    }
    return $arr;
}


function validate_body_data($inputData) {

    if(!is_object($inputData))
        throw new Exception("Invalid input data: should be an object",400);

    if(!property_exists($inputData,"data"))
        throw new Exception("Invalid input data: root object should have a 'data' member",400);

    $type = is_object($inputData->data)?"o":(is_array($inputData->data)?"a":"s");
    switch($type) {
        case "o";
            is_valid_resource_object($inputData->data);
            break;
        case "a":
            foreach ($inputData->data as $item) {
                is_valid_resource_object($item);
            }
            break;
        default:
            throw new Exception("Invalid input data: root object member 'data' should an array or an object",400);
    }

}

///**
// * validates $data as a JSON API document
// * @param $data
// * @param array $requiredAttributes
// * @throws Exception
// */
//function validatePostData($data, $requiredAttributes=[]) {
//    if(!is_object($data))
//        throw new Exception("Invalid top level data: not an object",400);
//    if(!property_exists($data,"data"))
//        throw new Exception("Invalid input object: missing 'data' property",400);
//
//    if(!in_array(gettype($data),["array","object"])) {
//        throw new Exception("Invalid data. Must be array or object", 400);
//    }
//
//    $entries = !is_array($data->data)?[$data->data]:$data->data;
//
//    if($requiredAttributes!==[])
//        foreach($entries as $entry) {
//            isValidPostDataEntry($entry,$requiredAttributes);
//        }
//
//}

///**
// * validates $data as a JSON API document
// * @param $data
// * @param array $requiredAttributes
// * @throws Exception
// */
//function validatePostDataArray($data, $requiredAttributes=[]) {
//    if(!is_object($data))
//        throw new Exception("Invalid top level data: not an object",400);
//
//    if(!property_exists($data,"data"))
//        throw new Exception("Invalid input object: missing 'data' property",400);
//
//    if(gettype($data)!=="array") {
//        throw new Exception("Invalid data. Must be an array", 400);
//    }
//
//    if($requiredAttributes!==[]) {
//        $entries = $data->data;
//
//        foreach ($entries as $entry) {
//            isValidPostDataEntry($entry, $requiredAttributes);
//        }
//    }
//}

///**
// * validates if $entry is a valid entry structure inside an JSONApi object
// * does not valid contents against the prototype
// * @param object $entry
// * @param array $requiredAttributes
// * @throws Exception
// */
//function isValidPostDataEntry($entry, $requiredAttributes=[]) {
//    if(!is_object($entry))
//        throw new Exception("Data entry not an object",400);
//
//    foreach ($requiredAttributes as $attr=>$attrType) {
//        if(!isset($entry->$attr))
//            throw new Exception("Resource object missing '$attr' property",400);
//
//        if(!is_null($attrType) && !in_array(gettype($entry->$attr),$attrType)) {
//            //echo "..".gettype($entry->$attr)."--".implode(",",$attrType)."--";
//            throw new Exception("Resource object attribute '$attr' is not a valid '" .
//                implode(", ", $attrType) . "", 400);
//        }
//
//    }
//}

///**
// * generates a cryptographical strong random string
// * @param int $len bytes length
// * @return string
// */
//function unique_id($len)
//{
//    return bin2hex(openssl_random_pseudo_bytes($len));
//}



///**
// * @param $obj
// * @throws \Exception
// */
function is_valid_resource_object($obj) {
    if(!is_object($obj)) {
        throw new \Exception("Invalid resource: must be an object",400);
    }

    if(property_exists($obj,"id") && empty($obj->id)) {
        throw new \Exception("Invalid object ID: cannot be null/empty",400);
    }

    if(property_exists($obj,"attributes") && !is_object($obj->attributes)) {
        throw new \Exception("Invalid attributes member: must be an object",400);
    }

    if(property_exists($obj,"relationships") && !is_object($obj->relationships)) {
        throw new \Exception("Invalid relationships member: must be an object", 400);
    }

    if(!property_exists($obj,"relationships"))
        return;

    foreach ($obj->relationships as $key=>$relData) {
        if(!is_object($obj)) {
            throw new \Exception("Invalid relationship resource: must be an object",400);
        }

        if(!property_exists($obj,"data")) {
            continue;
        }

        if(is_object($obj->data)) {
            is_valid_resource_object($obj->data);
            continue;
        }

        if(is_array($obj->data)) {
            foreach ($obj->data as $relData) {
                is_valid_resource_object($relData);
            }
            continue;
        }

        throw new \Exception("Invalid relationship data member: not an object or array",400);
    }
}