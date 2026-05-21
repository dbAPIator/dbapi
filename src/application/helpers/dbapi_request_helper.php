<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use dbAPI\API\DBAPIRequest;

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
 * shorthand method to fetch some of the GET parameters and set them to the request object
 * It applies to most of the request parameters with some exceptions
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
