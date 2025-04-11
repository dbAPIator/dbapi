<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 9/11/19
 * Time: 9:03 AM
 */
global $_schemas;
$_schemas = [
    "onUpdatePara"=>[
        "enum"=>["update","ignore","abort"],
        "maxItems"=>1,
        "default"=>"ignore"
    ],
    "GenericResourceObject"=> [
        "type"=> "object",
        "required"=> [
            "id",
            "type"
        ],
        "properties"=> [
            "id"=> [
                "type"=> "string"
            ],
            "type"=> [
                "type"=> "string"
            ],
            "attributes"=> [
                "type"=> "object"
            ],
            "relationships"=> [
                "type"=> "object"
            ],
            "meta"=> [
                "type"=> "object"
            ],
            "links"=> [
                "type"=> "object"
            ]
        ],
    ],
    "GenericErrorObject"=> [
        "type"=> "object",
        "properties"=> [
            "id"=> [
                "type"=> "string"
            ],
            "status"=> [
                "type"=> "string"
            ],
            "code"=> [
                "type"=> "string"
            ],
            "title"=> [
                "type"=> "string"
            ],
            "detail"=> [
                "type"=> "string"
            ],
            "source"=> [
                "type"=> "string"
            ],
            "meta"=> [
                "type"=> "object"
            ]
        ]
    ],
    "GenericResourceIdentifierObject"=>[
        "type"=>"object",
        "properties"=>[
            "id"=>[
                "type"=>"string"
            ],
            "type"=>[
                "type"=>"string"
            ]
        ],
        "required"=>["id","type"]
    ]
];

$recursionLevel = 10;

/********************************
 * path: /
 ********************************/


/**
 * @param $resName
 * @param $resSpec
 * @param $path
 * @param $fields
 * @param $includes
 * @param $dm
 * @param $recursionLevel
 */
function search_recursive($resName,$resSpec,$path,&$fields,&$includes,&$dm,$recursionLevel)
{
    if($recursionLevel--<0)
        return;

    if(isset($fields[$resName]))
        return;

    foreach ($resSpec["fields"] as $fldName => $fldSpec) {
        if (!isset($fldSpec["select"]) || $fldSpec["select"])
            $fields[$resName][] = $fldName;
    }

    if(!isset($resSpec["relations"]))
        return;

    foreach ($resSpec["relations"] as $relName => $relSpec) {
        if (!isset($relSpec["select"]) || $relSpec["select"])
            $fields[$resName][] = $relName;
    }

    foreach ($resSpec["relations"] as $relName => $relSpec) {
        if (isset($relSpec["select"]) && $relSpec["select"]) {
            $newPath = array_merge($path,[$relName]);
            $newPathStr = implode(".",$newPath);
            if(!in_array($newPathStr,$includes)) {
                $includes[] = $newPathStr;
                search_recursive($relSpec["table"],$dm[$relSpec["table"]],$newPath,$fields,$includes,$dm,$recursionLevel);
            }
        }
    }

}


/**
 * @return array
 */
function create_multiple_records()
{
    // todo
    return  [
        "summary" => "Bulk create records",
        "description" => "Returns created records",
        "operationId"=>"create_multiple_records",
        "tags"=>["_bulk"],
        "parameters" => [
            [
                "name"=>"Content-type",
                "in"=>"header",
                "required"=>true,
                "schema"=>[
                    "type"=>"string",
                    "default"=>"application/vnd.api+json"
                ]
            ],
            [
                "name"=>"onduplicate",
                "in"=>"query",
                "description"=>"Select behaviour when a duplicate key conflict occurs. Possible options:\n- update: update certain fields \n- ignore: do nothing and \n ",
                "required"=>false,
                "schema"=>[
                    "\$ref"=> "#/components/schemas/onUpdatePara"
                ]
            ],
            [
                "name"=>"update",
                "in"=>"query",
                "description"=>"Comma separated list of fields to update when parameter onduplicate=update",
                "required"=>false,
                "explode"=>false,
                "schema"=>[
                    "type"=>"string"
                ]
            ]
        ],
        "responses"=>[
            "201"=>[
                "description"=>"",
                "headers"=>[
                    "Location"=>[
                        "schema"=>[
                            "type"=>"string"
                        ]
                    ]
                ],
                "content"=>[
                    "application/json"=>[
                        "schema"=>[
                            "type"=>"array",
                            "items"=>[
                                "\$ref"=>"#/components/schemas/GenericResourceObject"
                            ]
                        ]
                    ]
                ]
            ],
            "403"=>[
                "description"=>""
            ],
            "409"=>[
                "description"=>""
            ],

        ],
        "requestBody"=>[
            "description"=>"Array of objects to be created as records",
            "content"=>[
                "application/json"=>[
                    "schema"=>[
                        "type"=>"object",
                        "properties"=>[
                            "data"=>[
                                "type"=>"array",
                                "items"=>[
                                    "\$ref"=>"#/components/schemas/GenericResourceObject"
                                ],
                                "minItems"=>1
                            ]
                        ],
                        "required"=>["data"]
                    ]
                ]
            ]
        ]
    ];
}


/**
 * @return array
 */
function update_multiple_records()
{
    // todo
    return [
        "summary" => "Bulk update records",
        "description" => "",
        "operationId" => "update_multiple_records",
        "tags"=>["_bulk"],
        "requestBody"=>[
            "description"=>"Bulk update records by providing an array of resource objects",
            "content"=>[
                "application/json"=>[
                    "schema"=>[
                        "type"=>"object",
                        "properties"=>[
                            "data"=>[
                                "type"=>"array",
                                "items"=>[
                                    "\$ref"=>"#/components/schemas/GenericResourceObject"
                                ],
                                "minItems"=>1

                            ]
                        ],
                        "required"=>["data"]
                    ]
                ]
            ]
        ],
        "parameters" => [
            [
                "name"=>"Content-type",
                "in"=>"header",
                "required"=>true,
                "schema"=>[
                    "type"=>"string",
                    "default"=>"application/vnd.api+json"
                ]
            ],

        ],
        "responses"=> [
            "200"=>[
                "description"=>"todo"
            ]
        ]
    ];
}

/**
 * @return array
 */
function delete_multiple_records()
{
    // todo
    return [
        "summary" => "Bulk delete records",
        "description" => "",
        "operationId" => "delete_multiple_records",
        "tags"=>["_bulk"],
        "requestBody"=>[
            "description"=>"Bulk delete records by providing an array of resource identifier objects",
            "content"=>[
                "application/json"=>[
                    "schema"=>[
                        "type"=>"object",
                        "properties"=>[
                            "data"=>[
                                "type"=>"array",
                                "items"=>[
                                    "\$ref"=>"#/components/schemas/GenericResourceIdentifierObject"
                                ],
                                "minItems"=>0

                            ]
                        ],
                        "required"=>["data"]
                    ]
                ]
            ]
        ],
        "parameters" => [],
        "responses"=> [
            "200"=>[
                "description"=>"todo"
            ]
        ]
    ];
}


/**
 * create 3 schema components to be used as references along the spec:
 * - resourceObject - specifies the structure of a JSONAPI Resource Object (https://jsonapi.org/format/#document-resource-objects) as when originating on the server
 * - newResourceObject - specifies the structure of a JSONAPI Resource Object as when originating from client (eg: in case of create). In this case the field ID is not required
 * - resourceIdentifierObject - specifies the structure of a JSONAPI Resourcer Identifier Object (https://jsonapi.org/format/#document-resource-identifier-objects)
 * @param $resName
 * @param $resSpec
 */
function add_components($resName, $resSpec)
{
    global  $_schemas;

    $labelResObj = "{$resName}ResourceObject";
    $labelResIdfObj = "{$resName}ResourceIdentifierObject";
    $labelNewResObj = "{$resName}NewResourceObject";

    if(!isset($components[$labelResObj])) {

        // create resourceIndicatorObject component
        if(isset($resSpec["relations"]) && count($resSpec["relations"]))
            $_schemas[$labelResIdfObj] = [
                "type"=>"object",
                "properties"=>[
                    "id"=>[
                        "type"=>"string"
                    ],
                    "type"=>[
                        "type"=>"string",
                        "enum"=>[$resName]
                    ]
                ],
                "required"=>["id","type"]
            ];

        // extract attributes
        $attrs = [];
        $reqAttrs = [];
        foreach ($resSpec["fields"] as $fldName=>$fldSpec) {
            if(!isset($fldSpec["type"])) {
//                echo $resName;
//                print_r($resSpec);
//                die();
                continue;
            }
            $attrs[$fldName] = typeMap($fldSpec["type"]);
            if($fldSpec["required"])
                $reqAttrs[] = $fldName;
        }

        // extract relationships
        $rels = [];
        if(isset($resSpec["relations"])) {
            foreach ($resSpec["relations"] as $relName=>$relSpec) {
                // defaults to outbound relation


                if($relSpec["type"]=="inbound")
                    $rel = [
                        "type"=>"array",
                        "items"=>[
                            "\$ref"=>"#/components/schemas/".$relSpec["table"]."ResourceIdentifierObject"
                        ],
                        "required"=>["id","type"]
                    ];
                else
                    $rel = [
                        "\$ref"=>"#/components/schemas/".$relSpec["table"]."ResourceIdentifierObject"
                    ];


                $rels[$relName] = $rel;
            }
        }


        // create basic resourceObject
        $resObj = [
            "type" => "object",
            "properties"=>[
                "id"=>[
                    "type"=>"string"
                ],
                "type"=>[
                    "type"=>"string",
                    "enum"=>[$resName]
                ],
                "links"=>[
                    "type"=>"object"
                ]
            ],
            "required"=>["id","type"]
        ];

        // add relationships of resourceObject
        if(count($rels)) {
            $resObj["properties"]["relationships"] = [
                "type"=>"object",
                "properties"=>$rels
            ];
        }

        // set attributes of resourceObject
        if(count($attrs)) {
            $resObj["properties"]["attributes"] = [
                "type" => "object",
                "properties" => $attrs
            ];
        }

        // duplicate resourceObject to newResourceObject
        $newResObj = $resObj;

        if(count($reqAttrs))
            $newResObj["properties"]["attributes"]["required"] = $reqAttrs;

        $newResObj["required"] = ["type","attributes"];

        $_schemas[$labelNewResObj] = $newResObj;
        $_schemas[$labelResObj] = $resObj;
    }
}

/********************************
 * path: /resourceName
 ********************************/
/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function get_multiple_records($resourceName,$resourceSpecifications,$dataModel)
{
    // todo
    $data = [
        "summary" => "Get '$resourceName' records list",
        "description" => "",
        "operationId" => $resourceName."_get_multiple_records",
        "parameters" => [],
        "tags"=>[$resourceName],
        "responses"=> [

        ]
    ];

    add_components($resourceName,$resourceSpecifications);
    //add_component_new_resource_object($resourceName,$resourceSpecifications);


    $data["responses"]["200"]= [
        "description"=> "Return records list of type $resourceName",
        "content"=>[
            "application/json"=>[
                "schema"=> [
                    "type"=> "object",
                    "properties"=> [
                        "data"=> [
                            "type"=>"array",
                            "items"=>[
                                "\$ref"=>"#/components/schemas/{$resourceName}ResourceObject"
                            ]
                        ],
                        "meta"=> [
                            "required"=> [
                                "total"
                            ],
                            "type"=> "object",
                            "properties"=> [
                                "offset"=> [
                                    "type"=> "integer",
                                    "default"=>0
                                ],
                                "total"=> [
                                    "type"=> "integer"
                                ]
                            ]
                        ],
                        "includes"=> [
                            "type"=> "array",
                            "items"=> [
                                "\$ref"=>"#/components/schemas/GenericResourceObject",
                                //"\$ref"=>"#/components/schemas/ResourceObject",
                            ]
                        ],
                    ],
                    "required"=>["data"]
                ]
            ]
        ]
    ];

    $data["parameters"][] = [
        "name" => "filter",
        "in" => "query",
        "required" => false,
        "explode"=>false,
        "schema"=>[
            "type"=>"string"
        ],
        "description" => "Comma separated list of filter criteria. 
        A filtering criteria is expressed as follows: fieldName [operator] value, where operator can be:
        - ! negates the operator which follows. Not to be used alone\n- = equals\n- ~= begins with
        - =~ ends with\n- ~=~ contains\n- &lt; smaller then\n- &lt;= equal or smaller then\n- &gt; bigger then\n- &gt;= equal or bigger then\n- &gt;&lt; in list. In this case the value should be a list of semicolon separated values\n\nPossible values for fieldName: id, title, content, creation_date, author, category, public",
        "example" => ""
    ];

    $data["parameters"][] = [
        "name" => "page[offset]",
        "in" => "query",
        "required" => false,
        "schema"=>[
            "type"=>"integer"
        ],
        "description" => "Page offset",
        "example" => "10"
    ];


    $data["parameters"][] = [
        "name" => "page[limit]",
        "in" => "query",
        "required" => false,
        "schema"=>[
            "type"=>"integer"
        ],
        "description" => "Maximum number of records to include",
        "example" => "10"
    ];



    $fields =[];
    $includes = [];
    global $recursionLevel;
    search_recursive($resourceName,$resourceSpecifications,[],$fields,$includes,$dataModel,$recursionLevel);


    //print_r($fields);

    if(count($fields)) {
        foreach ($fields as $fieldsResName=>$fieldsNames) {
            $fields[$fieldsResName] = array_values(array_unique($fieldsNames));
            $sss = [
                "name" => "fields[$fieldsResName]",
                "in" => "query",
                "required" => false,
                "explode"=>false,
                "schema"=>[
                    "type"=>"array",
                    "items"=>[
                        "type"=>"string",
                        "enum"=>array_values(array_unique($fields[$fieldsResName]))
                    ]
                ],
                "description" => "Comma separated list of '$resourceName' field names when 'include' parameter contains a relation of type '$resourceName'",
                //"example" => join(",",$values)
            ];
            //print_r($sss);
            $data["parameters"][] = $sss;
        }
    }


    if(count($includes)) {
        $data["parameters"][] = [
            "name" => "include",
            "in" => "query",
            "required" => false,
            "explode"=>false,
            "schema"=>[
                "type"=>"array",
                "items"=>[
                    "type"=>"string",
                    "enum"=>$includes
                ]
            ],
            "description" => "Comma separated list of relationships to include. See example for list of valid values",
            //"example" => implode(", ",$includes)
        ];
    }


    $data["parameters"][] = [
        "name" => "sort",
        "in" => "query",
        "required" => false,
        "explode"=>false,
        "schema"=>[
            "type"=>"array",
            "items"=>[
                "type"=>"string",
                "enum"=>array_merge(
                    $fields[$resourceName],
                    array_map(
                        function($item){
                            return "-".$item;
                        },
                        $fields[$resourceName]
                    )
                )
            ]
        ],
        "description" => "Comma separated list of relationships to include. See example for list of valid values",
        //"example" => implode(", ",$includes)
    ];
    return $data;
}

/**
 * @param $type
 * @return array
 */

function typeMap($type)
{
    $mysqlTypes = [
        "int"=>[
            "type"=>"integer"
        ],
        "varchar"=>[
            "type"=>"string"
        ],
        "enum"=> [
            "type"=>"string",
        ],
        "set"=> [
            "type"=>"string",
        ],
        "date"=>[
            "type"=>"string"
        ],
        "tinyint"=>[
            "type"=>"boolean"
        ],
        "datetime"=>[
            "type"=>"string"
        ],
        "float"=>[
            "type"=>"number"
        ],
        "double"=>[
            "type"=>"number"
        ],
        "bigint"=>[
            "type"=>"integer"
        ],
        "text"=>[
            "type"=>"string"
        ],
        "decimal"=>[
            "type"=>"number"
        ],
        "tinytext"=>[
            "type"=>"string"
        ],
        "timestamp"=>[
            "type"=>"string"
        ],
        "time"=>[
            "type"=>"string"
        ],
        "bit"=>[
            "type"=>"string"
        ]
    ];
    $res = isset($mysqlTypes[$type["proto"]]) ? $mysqlTypes[$type["proto"]] : ["type"=>"random_".$type["proto"]];
    if(in_array($type["proto"],["enum","set"])) {
        $res["enum"] = $type["vals"];
    }
    return $res;
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @return array
 */
function create_single_record($resourceName,$resourceSpecifications)
{
    // todo
    $data = [
        "summary" => "Create single record of type $resourceName",
        "description" => "",
        "operationId" => $resourceName."_create_single_record",
        "parameters" => [
            [
                "name"=>"Content-type",
                "in"=>"header",
                "required"=>true,
                "schema"=>[
                    "type"=>"string",
                    "default"=>"application/vnd.api+json"
                ]
            ]
        ],
        "tags"=>[$resourceName],
        "responses"=> [
            "200"=>[
                "description"=>"",
                "content"=>[
                    "application/json"=>[
                        "schema"=> [
                            "type"=>"object",
                            "properties"=>[
                                "data"=>[
                                    "type"=>"object",
                                    "properties"=>[
                                        "id"=>[
                                            "type"=>"string",
                                        ],
                                        "type"=>[
                                            "type"=>"string"
                                        ],
                                        "attributes"=>[
                                            "type"=>"object",
                                            "properties"=> new stdClass()
                                        ]
                                    ],
                                    "required"=>["id","type"]
                                ]
                            ],
                            "required"=>["data"]
                        ]
                    ]
                ]
            ]
        ]
    ];
    $attrs = &$data["responses"]["200"]["content"]["application/json"]["schema"]["properties"]["data"]["properties"]["attributes"]["properties"];
    foreach ($resourceSpecifications["fields"] as $field=>$fieldSpec) {
        if(!isset($fieldSpec["type"]))
            continue;
        $attrs->$field = typeMap($fieldSpec["type"]);
    }

    return $data;
}



/********************************
 * path: /resourceName/$id
 ********************************/
/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function get_single_record($resourceName,$resourceSpecifications,$dataModel)
{
    // todo
    $data = [
        "summary" => "Get '$resourceName' records list",
        "description" => "",
        "operationId" => $resourceName."_get_single_record",
        "parameters" => [],
        "tags"=>[$resourceName],
        "responses"=> []
    ];

    $data["parameters"][] = [
        "name"=>$resourceSpecifications["keyFld"],
        "in"=>"path",
        "description"=>"Field which uniquely identifies the retrieved record",
        "required"=>true,
        "schema"=>[
            "type"=>"string"
        ]
    ];

    $fields =[];
    $includes = [];
    global $recursionLevel;
    search_recursive($resourceName,$resourceSpecifications,[],$fields,$includes,$dataModel,$recursionLevel);


    //print_r($fields);

    if(count($fields)) {
        foreach ($fields as $fieldsResName=>$fieldsNames) {
            $fields[$fieldsResName] = array_values(array_unique($fieldsNames));
            $sss = [
                "name" => "fields[$fieldsResName]",
                "in" => "query",
                "required" => false,
                "explode"=>false,
                "schema"=>[
                    "type"=>"array",
                    "items"=>[
                        "type"=>"string",
                        "enum"=>array_values(array_unique($fields[$fieldsResName]))
                    ]
                ],
                "description" => "Comma separated list of '$resourceName' field names when 'include' parameter contains a relation of type '$resourceName'",
                //"example" => join(",",$values)
            ];
            //print_r($sss);
            $data["parameters"][] = $sss;
        }
    }


    if(count($includes)) {
        $data["parameters"][] = [
            "name" => "include",
            "in" => "query",
            "required" => false,
            "explode"=>false,
            "schema"=>[
                "type"=>"array",
                "items"=>[
                    "type"=>"string",
                    "enum"=>$includes
                ]
            ],
            "description" => "Comma separated list of relationships to include. See example for list of valid values",
            //"example" => implode(", ",$includes)
        ];
    }

    global $_schemas;

    add_components($resourceName,$resourceSpecifications);


    $data["responses"]["200"]= [
        "description"=> "Return record of type $resourceName identified by ID",
        "content"=>[
            "application/json"=>[
                "schema"=> [
                    "type"=> "object",
                    "properties"=> [
                        "data"=> [
                            "\$ref"=>"#/components/schemas/{$resourceName}ResourceObject"
                        ],
                        "includes"=> [
                            "type"=> "array",
                            "items"=> [
                                "\$ref"=>"#/components/schemas/GenericResourceObject"
                            ]
                        ]
                    ],
                    "required"=>["data"]
                ]
            ]
        ]
    ];
    return $data;
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function update_single_record($resourceName,$resourceSpecifications,$dataModel)
{
    // todo
    $data = [
        "summary" => "Update single record of type $resourceName",
        "description" => "",
        "tags"=>[$resourceName],
        "operationId" => $resourceName."_update_single_record",
    ];
    $data["parameters"]= [
        [
            "name"=>"Content-type",
            "in"=>"header",
            "required"=>true,
            "schema"=>[
                "type"=>"string",
                "default"=>"application/vnd.api+json"
            ]
        ],
        [
            "name"=>$resourceSpecifications["keyFld"],
            "in"=>"path",
            "description"=>"Field which uniquely identifies the retrieved record",
            "required"=>true,
            "schema"=>[
                "type"=>"string"
            ]
        ],
        [
            "name"=>"onduplicate",
            "in"=>"query",
            "description"=>"Select behaviour when a duplicate key conflict occurs. Possible options:\n- update: update certain fields \n- ignore: do nothing and \n ",
            "required"=>false,
            "schema"=>[
                "\$ref"=> "#/components/schemas/onUpdatePara"
            ]
        ],
        [
            "name"=>"update",
            "in"=>"query",
            "description"=>"Comma separated list of fields to update when parameter onduplicate=update",
            "required"=>false,
            "explode"=>false,
            "schema"=>[
                "type"=>"string"
            ]
        ]
    ];

    $data["requestBody"] = [
        "description"=> "Record to be updated",
        "content"=> [
            "application/json"=>[
                "schema"=>[
                    "type"=> "object",
                    "properties"=>[
                        "data"=>[
                            "\$ref"=> "#/components/schemas/{$resourceName}ResourceObject",
                        ]
                    ],
                    "required"=>["data"]
                ]
            ]
        ]
    ];

    $data["responses"]["200"]= [
        "description"=> "Return record of type $resourceName identified by ID",
        "content"=>[
            "application/json"=>[
                "schema"=> [
                    "type"=> "object",
                    "properties"=> [
                        "data"=> [
                            "\$ref"=>"#/components/schemas/{$resourceName}ResourceObject"
                        ],
                        "includes"=> [
                            "type"=> "array",
                            "items"=> [
                                "\$ref"=>"#/components/schemas/GenericResourceObject"
                            ]
                        ]
                    ],
                    "required"=>["data"]
                ]
            ]
        ]
    ];


    return $data;
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function delete_single_record($resourceName,$resourceSpecifications,$dataModel)
{
    // todo
    $data = [
        "summary" => "Delete single record of type $resourceName",
        "description" => "",
        "tags"=>[$resourceName],
        "operationId" => $resourceName."_delete_single_record",
        "parameters" => [],
        "responses"=> [
            "204"=> [
                "description"=> "Record successfully deleted"
            ],
            "404"=>[
                "description"=> "Record not found"
            ]
        ]
    ];

    $data["parameters"]= [
        [
            "name"=>$resourceSpecifications["keyFld"],
            "in"=>"path",
            "description"=>"Field which uniquely identifies the retrieved record",
            "required"=>true,
            "schema"=>[
                "type"=>"string"
            ]
        ]
    ];
    return $data;
}

/********************************
 * path: /resourceName/$id/__relationships/$relation
 ********************************/
/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function get_relationships($resourceName,$resourceSpecifications,$relationshipName)
{
    //echo  $resourceName.".".$relationshipName."\n";
    //print_r($resourceSpecifications["relations"]);

    $rel = $resourceSpecifications["relations"][$relationshipName];
    if($rel["type"]=="outbound")
        $dataObj = [
            "\$ref"=>"#/components/schemas/{$rel["table"]}ResourceIdentifierObject"
        ];
    else
        $dataObj = [
            "type"=>"array",
            "items"=>[
                "\$ref"=>"#/components/schemas/{$rel["table"]}ResourceIdentifierObject"
            ],
            "minItems"=>0,
            "uniqueItems"=>true
        ];

    $data = [
        "summary" => "Get '$relationshipName' relationship of type '$resourceName'",
        "description" => "Get $resourceName relationships of type $relationshipName",
        "operationId" => $resourceName."_get_relationship_".$relationshipName,
        "tags"=>[$resourceName."/relationship/".$relationshipName],
        "parameters" => [

        ],
        "responses"=> [
            "200"=>[
                "description"=> "Return record of type $resourceName identified by ID",
                "content"=>[
                    "application/json"=>[
                        "schema"=> [
                            "type"=> "object",
                            "properties"=> [
                                "data"=> $dataObj
                            ],
                            "required"=>["data"]
                        ]
                    ]
                ]
            ],
            "404"=>[
                "description"=>"Not found"
            ]
        ]
    ];

    $data["parameters"]= [
        [
            "name"=>$resourceSpecifications["keyFld"],
            "in"=>"path",
            "description"=>"Field which uniquely identifies the retrieved record",
            "required"=>true,
            "schema"=>[
                "type"=>"string"
            ]
        ]
    ];
    return $data;
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function create_relationships($resourceName,$resourceSpecifications,$relationshipName)
{
    $data = get_relationships($resourceName,$resourceSpecifications,$relationshipName);
    // todo
    $data["summary"] = "Create single or multiple $resourceName relationships of type $relationshipName";
    $data["description"] = "";
    $data["operationId"] = $resourceName."_create_relationship_".$relationshipName;
    return $data;
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function update_relationships($resourceName,$resourceSpecifications,$relationshipName)
{
    // todo
    $data = get_relationships($resourceName,$resourceSpecifications,$relationshipName);
    $data["summary"] = "Update single or multiple $resourceName relationships of type $relationshipName";
    $data["description"] = "";
    $data["operationId"] = $resourceName."_update_relationship_".$relationshipName;

    return $data;
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function delete_relationships($resourceName,$resourceSpecifications,$relationshipName)
{

    $data = get_relationships($resourceName,$resourceSpecifications,$relationshipName);
    // todo
    $data["summary"] = "Delete single or multiple $resourceName relationships of type $relationshipName";
    $data["description"] = "";
    $data["operationId"] = $resourceName."_delete_relationship_".$relationshipName;

    return $data;

}

/********************************
 * path: /resourceName/$id/$relation
 ********************************/
/**
 * @param $resourceName
 * @param $resourceSpecification
 * @param $dataModel
 * @return array
 */
function get_related($resourceName, $resourceSpecification, $relationshipName)
{
    //return ["aaaaa"];
    // todo
    $relSpec = $resourceSpecification["relations"][$relationshipName];
    if($relSpec["type"]=="outbound")
        $dataObj = [
            "\$ref"=>"#/components/schemas/{$relSpec["table"]}ResourceObject"
        ];
    else
        $dataObj = [
            "type"=>"array",
            "items"=>[
                "\$ref"=>"#/components/schemas/{$relSpec["table"]}ResourceObject"
            ]
        ];

    $data = [
        "summary" => "Get related records of type $relationshipName for single record of type $resourceName",
        "tags"=>["$relationshipName/$resourceName"],
        "description" => "",
        "operationId" => $resourceName."_get_related_records_$relationshipName",
        "parameters" => [],
        "responses"=> [
            "200"=>[
                "description"=>"Returns related resource objects of type $relationshipName for $resourceName",
                "content"=>[
                    "application/json"=>[
                        "schema"=>[
                            "type"=>"object",
                            "properties"=>[
                                "data"=>$dataObj
                            ],
                            "required"=>["data"]
                        ]
                    ]
                ]

            ]
        ],

    ];

    $data["parameters"]= [
        [
            "name"=>$resourceSpecification["keyFld"],
            "in"=>"path",
            "description"=>"Field which uniquely identifies the retrieved record",
            "required"=>true,
            "schema"=>[
                "type"=>"string"
            ]
        ]
    ];
    return $data;
}

/**
 * @param string $url
 * @param string $desc
 * @param string $title
 * @param string $contactName
 * @param string $contactEmail
 * @return array
 */
function open_api_spec(string $url,string $desc,string $title,string $contactName,string $contactEmail)
{

    return [
        "openapi" => "3.0.2",
        "info" => [
            "description" => $desc,
            "version" => "1.0.0",
            "title" => $title,
            "contact" => [
                "name" => $contactName,
                "email" => $contactEmail
            ],
            "license" => [
                "name" => "GPL"
            ]
        ],
        "servers" => [
            ["url"=>$url]
        ],
        "paths"=>[],
        "components"=> [
            "schemas"=>[

            ],
        ]
    ];
}

/**
 * @param $hostName
 * @param $dataModel
 * @param $basePath
 * @param $desc
 * @param $title
 * @param $name
 * @param $email
 * @return array
 */
function generate_swagger(string $url,array $dataModel,string $desc,string $title,string $name,string $email)
{
    $openApiSpec =  open_api_spec($url,$desc,$title,$name,$email);

    /************************************************
     * path: /
     ***********************************************/
    $openApiSpec["paths"]["/"] = [
        "post"=>create_multiple_records(),
        "patch"=>update_multiple_records(),
        "delete"=>delete_multiple_records()
    ];



    foreach ($dataModel as $resourceName=>$resourceSpecifications) {

        /************************************************
         * path: /resourceName
         ***********************************************/
        $resourcesPath = "/$resourceName";
        $openApiSpec["paths"][$resourcesPath] = [];


        // GET multiple records
        if($data=get_multiple_records($resourceName,$resourceSpecifications,$dataModel)) {
            $openApiSpec["paths"][$resourcesPath]["get"] = $data;
        }

        // POST (create) single record
        if($data=create_single_record($resourceName,$resourceSpecifications)) {
            $openApiSpec["paths"][$resourcesPath]["post"] = $data;
        }


        if(count($openApiSpec["paths"][$resourcesPath])) {
            $openApiSpec["paths"][$resourcesPath]["summary"] = "Bulk records manipulation options ";
            $openApiSpec["paths"][$resourcesPath]["description"] = "End point for retrieving and creating data of type $resourceName";
        }
        else
            unset($openApiSpec["paths"][$resourcesPath]);


        /************************************************
         * path: /resourceName/ID
         ***********************************************/
        if(!isset($resourceSpecifications["keyFld"]))
            continue;
        $singleResourcePath = sprintf("%s/{%s}",$resourcesPath,$resourceSpecifications["keyFld"]);
        $openApiSpec["paths"][$singleResourcePath] = [];

        // GET
        if($data=get_single_record($resourceName,$resourceSpecifications,$dataModel)) {
            $openApiSpec["paths"][$singleResourcePath]["get"] = $data;
        }

        // UPDATE
        if($data=update_single_record($resourceName,$resourceSpecifications,$dataModel)) {
            $openApiSpec["paths"][$singleResourcePath]["patch"] = $data;
        }

        // DELETE
        if($data=delete_single_record($resourceName,$resourceSpecifications,$dataModel)) {
            $openApiSpec["paths"][$singleResourcePath]["delete"] = $data;
        }




        if(!isset($resourceSpecifications["relations"]))
            continue;

        foreach ($resourceSpecifications["relations"] as $relName=>$relSpec) {

            /************************************************
             * relationship
             * /s/resourceName/ID/__relationships/relName
             ***********************************************/
            $relationshipPath = "$singleResourcePath/__relationships/$relName";
            $openApiSpec["paths"][$relationshipPath] = [];

            // GET
            if($data=get_relationships($resourceName,$resourceSpecifications,$relName)) {
                $openApiSpec["paths"][$relationshipPath]["get"] = $data;
            }

            // POST
            if($data=create_relationships($resourceName,$resourceSpecifications,$relName)) {
                $openApiSpec["paths"][$relationshipPath]["post"] = $data;
            }

            // PATCH
            if($data=update_relationships($resourceName,$resourceSpecifications,$relName)) {
                $openApiSpec["paths"][$relationshipPath]["patch"] = $data;
            }

            // DELETE
            if($data=delete_relationships($resourceName,$resourceSpecifications,$relName)) {
                $openApiSpec["paths"][$relationshipPath]["delete"] = $data;
            }


            /************************************************
             * related
             * /s/resourceName/ID/relName
             ***********************************************/
            $relatedResourcePath = "$singleResourcePath/$relName";
            $openApiSpec["paths"][$relatedResourcePath] = [];

            // GET
            if($data=get_related($resourceName,$resourceSpecifications,$relName)) {
                $openApiSpec["paths"][$relatedResourcePath]["get"] = $data;
            }
        }

    }


    global $_schemas;
    $openApiSpec["components"]["schemas"]= $_schemas;

    return $openApiSpec;
}