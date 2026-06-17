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
/**
 * Build a nested tree from comma-separated dot-notation include paths.
 *
 * @return array<string, array>
 */
function parse_include_tree(string $include): array
{
    $tree = [];
    foreach (array_filter(array_map('trim', explode(',', $include))) as $path) {
        $node = &$tree;
        foreach (explode('.', $path) as $part) {
            if (!isset($node[$part])) {
                $node[$part] = [];
            }
            $node = &$node[$part];
        }
    }
    return $tree;
}

/**
 * @return list<string>
 */
function flatten_include_paths(array $tree, string $prefix = ''): array
{
    $paths = [];
    foreach ($tree as $rel => $children) {
        $path = $prefix === '' ? $rel : "$prefix.$rel";
        $paths[] = $path;
        if ($children) {
            $paths = array_merge($paths, flatten_include_paths($children, $path));
        }
    }
    return $paths;
}

function merge_include_for_resource(array &$inputs, string $fqResName, array $paths): void
{
    if (!$paths) {
        return;
    }
    $value = implode(',', $paths);
    if (!isset($inputs['include'])) {
        $inputs['include'] = [];
    }
    if (!empty($inputs['include'][$fqResName])) {
        $inputs['include'][$fqResName] .= ',' . $value;
    } else {
        $inputs['include'][$fqResName] = $value;
    }
}

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
