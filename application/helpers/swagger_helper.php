<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 9/11/19
 * Time: 9:03 AM
 */
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
function bulk_create_mixed_records()
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
                    "default"=>"application/json"
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
                    "default"=>"application/json"
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
 * @param $resourceName
 * @param $resourceSpecification
 * @return array
 */
function add_components($resourceName, $resourceSpecification)
{
    $baseSchema = [
        "type"=>"object",
        "properties"=>[
            "id"=>[
                "type"=>"string"
            ],
            "type"=>[
                "type"=>"string",
                "enum"=>[$resourceName]
            ]
        ],
        "required"=>["type"]
    ];


    if(!empty($resourceSpecification["keyFld"]))
        $baseSchema["required"][] = "id";
    $resourceSchema = $baseSchema;
    $resourceSchema["properties"]["attributes"] = [
        "type"=>"object",
        "properties"=>[]
    ];
    $resourceSchema["required"][] = "attributes";

    // extract attributes
    $reqAttrs = [];
    foreach ($resourceSpecification["fields"] as $fldName=>$fldSpec) {
        $resourceSchema["properties"]["attributes"]["properties"][$fldName] = typeMap($fldSpec["type"]);
        $attrs[$fldName] = typeMap($fldSpec["type"]);
        if($fldSpec["required"])
            $reqAttrs[] = $fldName;
    }
    if(count($reqAttrs))
        $resourceSchema["properties"]["attributes"]["required"] = $reqAttrs;

    // extract relationships
    if(isset($resourceSpecification["relations"])) {
        $resourceSchema["required"][] = "relationships";
        $resourceSchema["properties"]["relationships"] = [
            "type"=>"object",
            "properties"=>[]
        ];
        foreach ($resourceSpecification["relations"] as $relName=>$relSpec) {
            if($relSpec["type"]=="inbound")
                $resourceSchema["properties"]["relationships"]["properties"][$relName] = [
                    "type"=>"object",
                    "properties"=>[
                        "data"=>[
                            "type"=>"array",
                            "items"=>[
                                "\$ref"=>"#/components/schemas/".$relSpec["table"]."_ResourceIdentifierObject"
                            ]
                        ]
                    ],
                    "required"=>["data"]
                ];
            else
                $resourceSchema["properties"]["relationships"]["properties"][$relName] = [
                    "type"=>"object",
                    "properties"=>[
                        "data"=>[
                            "\$ref"=>"#/components/schemas/".$relSpec["table"]."_ResourceIdentifierObject"
                        ]
                    ],
                    "required"=>["data"]
                ];

        }
    }

    $createResourceSchema = $resourceSchema;
    unset($createResourceSchema["required"]);
    return [
        "{$resourceName}_ResourceIdentifierObject"=>$baseSchema,
        "{$resourceName}_ResourceObject"=>$resourceSchema,
        "{$resourceName}_ResourceObjectCreate"=>$createResourceSchema,
    ];

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
function get_records($tags,$resourceName, $resourceSpecifications, $dataModel,$overwrites=[])
{
    // todo
    $data = [
        "summary" => "Get '$resourceName' records list",
        "description" => "",
        "operationId" => $resourceName."_get_multiple_records",
        "parameters" => [],
        "tags"=>$tags,
        "responses"=> [
            "200"=>['$ref'=>"#/components/responses/{$resourceName}_GetRecords"],
            "400"=>['$ref'=>"#/components/responses/BadRequest"],
            "401"=>['$ref'=>"#/components/responses/NotAuthorized"],
            "403"=>['$ref'=>"#/components/responses/Forbidden"],
            "404"=>['$ref'=>"#/components/responses/NotFound"],
            "500"=>['$ref'=>"#/components/responses/ServerError"],
        ]
    ];

    //add_component_new_resource_object($resourceName,$resourceSpecifications);


    $data["parameters"] = [
        ['$ref'=>"#/components/parameters/{$resourceName}_filter"],
        ['$ref'=>"#/components/parameters/{$resourceName}_include"],
        ['$ref'=>"#/components/parameters/{$resourceName}_fields"],
        ['$ref'=>"#/components/parameters/{$resourceName}_limit"],
        ['$ref'=>"#/components/parameters/{$resourceName}_offset"],
        ['$ref'=>"#/components/parameters/{$resourceName}_sort"]
    ];


    return mergeRecursive($data,$overwrites);
}

/**
 * @param $typeSpec
 * @return array
 */

function typeMap($typeSpec)
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
    $res = isset($mysqlTypes[$typeSpec["proto"]]) ? $mysqlTypes[$typeSpec["proto"]] : ["type"=>"random_".$typeSpec["proto"]];
    if(in_array($typeSpec["proto"],["enum","set"])) {
        $res["enum"] = $typeSpec["vals"];
    }
    return $res;
}


/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @return array
 */
function create_records($tags,$resourceName, $resourceSpecifications,$datamodel,$overwrites=[])
{
    $relNamesList = implode("|",array_keys($resourceSpecifications["relations"]));

    $parameters = [
        ['$ref'=>"#/components/parameters/onduplicate"],
        ['$ref'=>"#/components/parameters/contentTypeJson"],
        ['$ref'=>"#/components/parameters/{$resourceName}_update"],
        ['$ref'=>"#/components/parameters/{$resourceName}_include"]
    ];
    $data = [
        "summary" => "Create records of type $resourceName",
        "description" => "This method allows both single record creation as well as batch record creation.\n"
            ."The create operation is enclosed in a transaction. If one of the inserts fail the entire block will fail.\n"
            ."For handling errors causes by duplicate inserts use the **onduplicate** parameter",
        "operationId" => $resourceName."_create_multiple_records",
        "parameters" => $parameters,
        "tags"=>$tags,
        "requestBody"=>['$ref'=>"#/components/requestBodies/{$resourceName}_Create"],
        "responses"=> [
            "201"=>['$ref'=>"#/components/responses/{$resourceName}_CreateOK"],
            "400"=>['$ref'=>"#/components/responses/BadRequest"],
            "401"=>['$ref'=>"#/components/responses/NotAuthorized"],
            "403"=>['$ref'=>"#/components/responses/Forbidden"],
            "404"=>['$ref'=>"#/components/responses/NotFound"],
            "409"=>['$ref'=>"#/components/responses/Conflict"],
            "500"=>['$ref'=>"#/components/responses/ServerError"],

        ]
    ];

    return array_merge_recursive($data,$overwrites);
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
function get_record_by_id($tags,$resourceName, $resourceSpecifications, $dataModel,$overwrites=[])
{
    // todo
    $data = [
        "summary" => "Get '$resourceName' record by ID (primary key)",
        "description" => "get record by id",
        "operationId" => $resourceName."_get_record_by_id",
        "tags"=>$tags,
        "responses"=> []
    ];

    $data["parameters"] = [
        [
            "name"=>$resourceSpecifications["keyFld"],
            "in"=>"path",
            "description"=>"Record ID (primary key value)",
            "required"=>true,
            "schema"=>[
                "type"=>"string"
            ]
        ],
        ['$ref'=>"#/components/parameters/{$resourceName}_fields"],
        ['$ref'=>"#/components/parameters/{$resourceName}_include"],
    ];

    $data["responses"] =[
        "200"=>[ '$ref'=>"#/components/responses/{$resourceName}_GetRecordById"],
        "400"=>[ '$ref'=>"#/components/responses/BadRequest"],
        "401"=>[ '$ref'=>"#/components/responses/NotAuthorized"],
        "403"=>[ '$ref'=>"#/components/responses/Forbidden"],
        "404"=>[ '$ref'=>"#/components/responses/NotFound"],
        "500"=>['$ref'=>"#/components/responses/ServerError"],
    ];

    return mergeRecursive($data,$overwrites);
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function update_single_record($tags,$resourceName,$resourceSpecifications,$dataModel,$overwrites=[])
{
    // todo
    $data = [
        "summary" => "Update single record of type $resourceName",
        "description" => "",
        "tags"=>$tags,
        "operationId" => $resourceName."_update_single_record",
    ];
    $data["parameters"]= [
//        ['$ref'=>"#/components/parameters/contentTypeJson"],
        ['$ref'=>"#/components/parameters/onduplicate"],
        [
            "name"=>$resourceSpecifications["keyFld"],
            "in"=>"path",
            "description"=>"Field which uniquely identifies the record to be update",
            "required"=>true,
            "schema"=>[
                "type"=>"string"
            ]
        ],

    ];

    $data["requestBody"] = ['$ref'=>"#/components/requestBodies/{$resourceName}_Update"];

    $data["responses"] =[
        "200"=>[ '$ref'=>"#/components/responses/{$resourceName}_GetRecordById"],
        "400"=>[ '$ref'=>"#/components/responses/BadRequest"],
        "401"=>[ '$ref'=>"#/components/responses/NotAuthorized"],
        "403"=>[ '$ref'=>"#/components/responses/Forbidden"],
        "404"=>[ '$ref'=>"#/components/responses/NotFound"],
        "500"=>['$ref'=>"#/components/responses/ServerError"],
    ];
    return mergeRecursive($data,$overwrites);

}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @param $dataModel
 * @return array
 */
function delete_single_record($tags,$resourceName,$resourceSpecifications,$dataModel,$overwrites=[])
{
    // todo
    $data = [
        "summary" => "Delete single record of type $resourceName",
        "description" => "",
        "tags"=>$tags,
        "operationId" => $resourceName."_delete_single_record"
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
    $data["responses"] =[
        "204"=>[ '$ref'=>"#/components/responses/NoContent"],
        "400"=>[ '$ref'=>"#/components/responses/BadRequest"],
        "401"=>[ '$ref'=>"#/components/responses/NotAuthorized"],
        "403"=>[ '$ref'=>"#/components/responses/Forbidden"],
        "404"=>[ '$ref'=>"#/components/responses/NotFound"],
        "500"=>['$ref'=>"#/components/responses/ServerError"],
    ];
    return mergeRecursive($data,$overwrites);

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
            "\$ref"=>"#/components/schemas/{$rel["table"]}_ResourceIdentifierObject"
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
            "\$ref"=>"#/components/schemas/{$relSpec["table"]}_ResourceObject"
        ];
    else
        $dataObj = [
            "type"=>"array",
            "items"=>[
                "\$ref"=>"#/components/schemas/{$relSpec["table"]}_ResourceObject"
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
 * @param string $url API base URL
 * @param string $desc API description
 * @param string $title API title
 * @param string $contactName Contact name
 * @param string $contactEmail Contact email
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
                "jsonapi"=>[
                    "type"=>"string",
                    "enum"=>["1.0"],
                    "default"=>"1.0"
                ],
                "errors"=>[
                    "type"=>"array",
                    "items"=>[
                        "type"=>"object",
                        "properties"=>[
                            "code"=>[
                                "type"=>"string",
                            ],
                            "message"=>[
                                "type"=>"string"
                            ]
                        ]
                    ]
                ]
            ],
            "requestBodies"=>[],
            "responses"=>[
                "BadRequest"=>[
                    "description"=>"BAD REQUEST - Invalid input data",
                    "content"=>[
                        "application/json"=>[
                            "schema"=>[
                                "type"=>"object",
                                "properties"=>[
                                    "errors"=>['$ref'=>"#/components/schemas/errors"],
                                    "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"],
                                ]
                            ]
                        ]
                    ]
                ],
                "NotAuthorized"=>[
                    "description"=>"NOT AUTHORIZED - the user is not authorized/authenticated",
                    "content"=>[
                        "application/json"=>[
                            "schema"=>[
                                "type"=>"object",
                                "properties"=>[
                                    "errors"=>['$ref'=>"#/components/schemas/errors"],
                                    "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"],
                                ]
                            ]
                        ]
                    ]
                ],
                "Forbidden"=>[
                    "description"=>"FORBIDDEN - the user does not have access to the requested resource",
                    "content"=>[
                        "application/json"=>[
                            "schema"=>[
                                "type"=>"object",
                                "properties"=>[
                                    "errors"=>['$ref'=>"#/components/schemas/errors"],
                                    "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"],
                                ]
                            ]
                        ]
                    ]
                ],
                "NoContent"=>[
                    "description"=>"OK - no body is returned",
                ],

                "NotFound"=>[
                    "description"=>"NOT FOUND - Requested resource not found",
                    "content"=>[
                        "application/json"=>[
                            "schema"=>[
                                "type"=>"object",
                                "properties"=>[
                                    "errors"=>['$ref'=>"#/components/schemas/errors"],
                                    "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"],
                                ]
                            ]
                        ]
                    ]
                ],
                "ServerError"=>[
                    "description"=>"INTERNAL SERVER ERROR - Requested resource not found",
                    "content"=>[
                        "application/json"=>[
                            "schema"=>[
                                "type"=>"object",
                                "properties"=>[
                                    "errors"=>['$ref'=>"#/components/schemas/errors"],
                                    "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"],
                                ]
                            ]
                        ]
                    ]
                ],
                "NoContent"=>[
                    "description"=>"Record deleted successfully",
                ],
                "Conflict"=>[
                    "description"=>"Conflict - there is a duplicate record either by the primary key field or one of the unique fields defined",
                    "content"=>[
                        "application/json"=>[
                            "schema"=>[
                                "type"=>"object",
                                "properties"=>[
                                    "errors"=>['$ref'=>"#/components/schemas/errors"],
                                    "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"],
                                ]
                            ]
                        ]
                    ]
                ],
            ],
            "parameters"=>[
                "onduplicate"=>[
                    "name"=>"onduplicate",
                    "in"=>"query",
                    "description"=>"Select behaviour when a duplicate key conflict occurs. Possible options:\n- update: update certain fields \n- ignore: do nothing and \n ",
                    "required"=>false,
                    "schema"=>[
                        "type"=>"string",
                        "enum"=>["ignore","update","error"],
                        "default"=>"error"
                    ]
                ],
                "contentTypeJson"=>[
                    "name"=>"Content-type",
                    "in"=>"header",
                    "required"=>false,
                    "schema"=>[
                        "type"=>"string",
                        "default"=>"application/json"
                    ]
                ],
            ],
            "securitySchemes"=>[]
        ]
    ];
}

function create_request_body_create($resourceName){
    return [
        "required"=>true,
        "content"=>[
            "application/json"=>[
                "schema"=>[
                    "type"=>"object",
                    "properties"=>[
                        "data"=>[
                            "oneOf"=>[
                                [
                                    "type"=>"array",
                                    "items"=>[
                                        "\$ref"=>"#/components/schemas/{$resourceName}_ResourceObjectCreate"
                                    ]
                                ],
                                [
                                    "\$ref"=>"#/components/schemas/{$resourceName}_ResourceObjectCreate"
                                ]
                            ]
                        ],
                        "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"]
                    ]
                ]
            ]
        ]
    ];
}
function create_request_body_update($resourceName){
    return [
        "required"=>true,
        "content"=>[
            "application/json"=>[
                "schema"=>[
                    "type"=>"object",
                    "properties"=>[
                        "data"=>[
                            '$ref'=>"#/components/schemas/{$resourceName}_ResourceObject"
                        ],
                        "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"]
                    ]
                ]
            ]
        ]
    ];
}

function create_response_ok_for_create($resourceName) {
    return [
        "description"=>"Record of type $resourceName",
        "content"=>[
            "application/json"=>[
                "schema"=>[
                    "type"=>"object",
                    "properties"=>[
                        "data"=>[
                            "oneOf"=>[
                                [
                                    '$ref'=>"#/components/schemas/{$resourceName}_ResourceObject"
                                ],
                                [
                                    "type"=>"array",
                                    "items"=>['$ref'=>"#/components/schemas/{$resourceName}_ResourceObject"]
                                ]
                            ]
                        ],
                        "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"]
                    ]
                ]
            ]
        ]
    ];
}

function create_reponse_ok_for_single_update($resourceName) {
    return [
        "application/json"=>[
            "description"=>"Record or array of records of type $resourceName",
            "content"=>[
                "application/json"=>[
                    "schema"=>[
                        "type"=>"object",
                        "properties"=>[
                            "data"=>[
                                "\$ref"=>"#/components/schemas/{$resourceName}_ResourceObjectCreate"
                            ],
                            "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"]
                        ]
                    ]
                ]
            ]
        ]
    ];
}

function create_response_ok_for_get_records($resourceName){
    return [
        "description"=>"Array of records of type $resourceName",
        "content"=>[
            "application/json"=>[
                "schema"=>[
                    "type"=>"object",
                    "properties"=>[
                        "data"=>[
                            "type"=>"array",
                            "items"=>["\$ref"=>"#/components/schemas/{$resourceName}_ResourceObject"]
                        ],
                        "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"]
                    ]
                ]
            ]
        ]
    ];
}
function create_response_ok_for_get_record_by_id($resourceName){
    return [
        "description"=>"Array of records of type $resourceName",
        "content"=>[
            "application/json"=>[
                "schema"=>[
                    "type"=>"object",
                    "properties"=>[
                        "data"=>["\$ref"=>"#/components/schemas/{$resourceName}_ResourceObject"],
                        "jsonapi"=>['$ref'=>"#/components/schemas/jsonapi"]
                    ]
                ]
            ]
        ]
    ];
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @return array
 */
function create_param_include($resourceName,$resourceSpecifications) {
    $relNamesList = implode("|",array_keys($resourceSpecifications["relations"]));
    return  [
        "name"=>"include[{$resourceName}]",
        "in"=>"query",
        "description"=>"Comma separated list of relation names to include in the result",
        "schema"=>[
            "type"=>"string",
            "pattern"=>sprintf("^(%s)(,(%s))*$",$relNamesList,$relNamesList)
        ]
    ];
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @return array
 */
function create_param_filter($resourceName,$resourceSpecifications) {
    return  [
        "name"=>"filter[{$resourceName}]",
        "in"=>"query",
        "description" => "Comma separated list of filter expressions which are combined with AND.\n".
            "Filter expression syntax: {fieldName}[operator]{value}, where operator can be:\n"
            ."- \= : fieldName equals value\n"
            ."- \!\= : fieldName does not equals value\n"
            ."- \=\~ : fieldName starts with value\n"
            ."- \~\= : fieldName ends with value\n"
            ."- \~\=\~ : fieldName contains with value\n"
            ."- \> : fieldName greater than value\n"
            ."- \>\= : fieldName greater or equal than value\n"
            ."- \< : fieldName smaller than value\n"
            ."- \<\= : fieldName smaller or equal than value\n"
            ."- \>\< : fieldName is one of the values in the semicolon separated list of values (eg city\>\<Rome;Paris;London \n",
        "example" => "bdate>2000-01-01,fname=~John,city><Paris;London;Paris",
        "schema"=>[
            "type"=>"string"
        ]
    ];
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @return array
 */
function create_param_fields($resourceName,$resourceSpecifications) {
    $fieldNames = array_keys($resourceSpecifications["fields"]);
    $fieldNamesList = implode("|",$fieldNames);
    return  [
        "name"=>"fields[{$resourceName}]",
        "in"=>"query",
        "description"=>"Comma separated list of field names to include in the response. If not specified it will return all fields",
        "example"=>implode(",",$fieldNames),
        "schema"=>[
            "type"=>"string",
            "pattern"=>sprintf("^(%s)(,(%s))*$",$fieldNamesList,$fieldNamesList)
        ]
    ];
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @return array
 */
function create_param_sort($resourceName,$resourceSpecifications) {
    //print_r($resourceSpecifications["fields"]);

    $fieldNames = array_keys($resourceSpecifications["fields"]);
    $fieldNamesList = implode("|",$fieldNames);
    return  [
        "name"=>"sort[{$resourceName}]",
        "in"=>"query",
        "description"=>"Comma separated list of field names order the record set.\n"
            ."Default order direction is ascending. Placing a - (minus) in front of the field name will change the sort direction to descending for the column",
        "example"=>$fieldNames[0].(isset($fieldNames[1]) ? ",-{$fieldNames[1]}" : ""),
//        "example"=>$fieldNamesList,
        "schema"=>[
            "type"=>"string",
            "pattern"=>sprintf("^-*(%s)(,-*(%s))*$",$fieldNamesList,$fieldNamesList)
        ]
    ];
}

/**
 * @param $resourceName
 * @param $resourceSpecifications
 * @return array
 */
function create_param_pagination_offset($resourceName,$resourceSpecifications) {
    return  [
        "name"=>"page[{$resourceName}][offset]",
        "in"=>"query",
        "default"=>0,
        "description"=>"The number of records to skip before starting to return results. Used for pagination in combination with the **limit** parameter.",
        "example"=>"50",
        "schema"=>[
            "type"=>"integer"
        ]
    ];
}
function create_param_pagination_limit($resourceName,$resourceSpecifications) {
    return  [
        "name"=>"page[{$resourceName}][limit]",
        "in"=>"query",
        "default"=>100,
        "description"=>"The maximum number of records to return in the response. Used for pagination in combination with the **offset** parameter.",
        "example"=>"10",
        "schema"=>[
            "type"=>"integer"
        ]
    ];
}

function create_param_onduplicate_update($resourceName,$resourceSpecifications) {
    $fieldsList = implode("|",array_keys($resourceSpecifications["fields"]));
    return [
        "name"=>"update",
        "in"=>"query",
        "description"=>"Comma separated list of fields to update when parameter **onduplicate=update**",
        "required"=>false,
        "explode"=>false,
        "schema"=>[
            "type"=>"string",
            "pattern"=>sprintf("^(%s)(,(%s))*$",$fieldsList,$fieldsList)
        ]
    ];

}

function create_components(&$openApiSpec,$resourceName,$resourceSpecifications) {
    //print_r(add_components($resourceName,$resourceSpecifications));
    $openApiSpec["components"]["schemas"] = array_merge($openApiSpec["components"]["schemas"],add_components($resourceName,$resourceSpecifications));


    // request body for create
    $openApiSpec["components"]["requestBodies"]["{$resourceName}_Create"] = create_request_body_create($resourceName);
    // request body for update
    $openApiSpec["components"]["requestBodies"]["{$resourceName}_Update"] = create_request_body_update($resourceName);

    // response for create
    $openApiSpec["components"]["responses"]["{$resourceName}_CreateOK"] = create_response_ok_for_create($resourceName);
    // response for update
    $openApiSpec["components"]["responses"]["{$resourceName}_UpdateOK"] = create_reponse_ok_for_single_update($resourceName);

    // response for get records
    $openApiSpec["components"]["responses"]["{$resourceName}_GetRecords"] = create_response_ok_for_get_records($resourceName);
    // response for get record by id
    $openApiSpec["components"]["responses"]["{$resourceName}_GetRecordById"] = create_response_ok_for_get_record_by_id($resourceName);

    $openApiSpec["components"]["parameters"]["{$resourceName}_include"] = create_param_include($resourceName,$resourceSpecifications);
    $openApiSpec["components"]["parameters"]["{$resourceName}_filter"] = create_param_filter($resourceName,$resourceSpecifications);
    $openApiSpec["components"]["parameters"]["{$resourceName}_fields"] = create_param_fields($resourceName,$resourceSpecifications);
    $openApiSpec["components"]["parameters"]["{$resourceName}_sort"] = create_param_sort($resourceName,$resourceSpecifications);
    $openApiSpec["components"]["parameters"]["{$resourceName}_limit"] = create_param_pagination_limit($resourceName,$resourceSpecifications);
    $openApiSpec["components"]["parameters"]["{$resourceName}_offset"] = create_param_pagination_offset($resourceName,$resourceSpecifications);
    $openApiSpec["components"]["parameters"]["{$resourceName}_update"] = create_param_onduplicate_update($resourceName,$resourceSpecifications);


}

/**
 * @param $hostName
 * @param $dataModel
 * @param $basePath
 * @param $apiDescription
 * @param $apiTitle
 * @param $contactName
 * @param $contactEmail
 * @return array
 */
function generate_swagger(string $url,array $dataModel,string $apiDescription,string $apiTitle,string $contactName,string $contactEmail)
{
    $openApiSpec =  open_api_spec($url,
        $apiDescription,
        $apiTitle,
        $contactName,
        $contactEmail);

    /************************************************
     * path: /
     ***********************************************/
//    $openApiSpec["paths"]["/"] = [
//        "post"=>bulk_create_mixed_records(),
//        "patch"=>update_multiple_records(),
//        "delete"=>delete_multiple_records()
//    ];



    $resources = array_keys($dataModel);
    // print_r($resources);
    foreach ($resources as $resourceName) {
//        echo $resourceName;
        $resourceSpecifications = $dataModel[$resourceName];

        /************************************************
         * path: /resourceName
         ***********************************************/
        $resourcesPath = "/$resourceName";
        $openApiSpec["paths"][$resourcesPath] = [];
        $openApiSpec["paths"][$resourcesPath]["summary"] = "CRUD operations on table **$resourceName** and related tables";

        create_components($openApiSpec, $resourceName, $resourceSpecifications);
    }

    foreach ($resources as $resourceName) {
        $resourcesPath = "/$resourceName";
        $resourceSpecifications = $dataModel[$resourceName];

        // GET records
        $openApiSpec["paths"][$resourcesPath]["get"] = get_records([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        // POST create records
        $openApiSpec["paths"][$resourcesPath]["post"] = create_records([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        /************************************************
         * path: /resourceName/ID
         ***********************************************/
        if(!isset($resourceSpecifications["keyFld"]))
            continue;

        $resIdFld = $resourceSpecifications["keyFld"];
        $singleResourcePath = sprintf("%s/{%s}",$resourcesPath,$resIdFld);
        $openApiSpec["paths"][$singleResourcePath] = [];

        // GET
        $openApiSpec["paths"][$singleResourcePath]["get"] = get_record_by_id([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        // UPDATE
        $openApiSpec["paths"][$singleResourcePath]["patch"] = update_single_record([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        // DELETE
        $openApiSpec["paths"][$singleResourcePath]["delete"] = delete_single_record([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        if(!isset($resourceSpecifications["relations"]))
            continue;

        foreach ($resourceSpecifications["relations"] as $relName=>$relSpec) {
            /************************************************
             * relationship
             * /s/resourceName/ID/__relationships/relName
             ***********************************************/
            $relationshipPath = "$singleResourcePath/$relName";
            $openApiSpec["paths"][$relationshipPath] = [];

            //print_r($relSpec);
            if($relSpec['type']=="outbound") {

                // GET related record
                $openApiSpec["paths"][$relationshipPath]["get"] = get_record_by_id(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["fkfield"]}",
                        //"{$resourceName}"
                    ],$relSpec["table"],$dataModel[$relSpec["table"]],$dataModel,
                    [
                        "summary"=>"Get related {$relSpec["fkfield"]} record of {$resourceName}/{id}"
                    ]);

                // UPDATE related record
                $openApiSpec["paths"][$relationshipPath]["patch"] = update_single_record(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["fkfield"]}",
//                        "{$resourceName}"
                    ],$relSpec["table"],$dataModel[$relSpec["table"]],$dataModel,
                    [
                        "summary"=>"Update related {$relSpec["fkfield"]} record of {$resourceName}/{id}"
                    ]);

                // DELETE related record
                // UPDATE related record
                $openApiSpec["paths"][$relationshipPath]["delete"] = delete_single_record(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["fkfield"]}",
//                        "{$resourceName}"
                    ],$relSpec["table"],$dataModel[$relSpec["table"]],$dataModel,
                    [
                        "summary"=>"Delete related {$relSpec["fkfield"]} record of {$resourceName}/{id}"
                    ]);
            }
            else {
                // print_r($relSpec);
                //echo $relationshipPath."\n";
                // GET related records
                $openApiSpec["paths"][$relationshipPath]["get"] = get_records(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["table"]}",
//                        "{$resourceName}"
                    ],$relSpec["table"],$dataModel[$relSpec["table"]],$dataModel,
                    [
                        "summary"=>"Get related {$relSpec["table"]} records of {$resourceName}/{id}"
                    ]);

                // POST create related records
                $openApiSpec["paths"][$relationshipPath]["post"] = create_records(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["table"]}",
//                        "{$resourceName}"
                    ],$resourceName,$resourceSpecifications,$dataModel,
                    [
                        "summary"=>"Create related {$relSpec["table"]} records of {$resourceName}/{id}"
                    ]);

                if(empty($dataModel[$relSpec["table"]]["keyFld"]))
                    continue;

                $relationshipPath = $relationshipPath."/{{$dataModel[$relSpec["table"]]["keyFld"]}}";
                $openApiSpec["paths"][$relationshipPath] = [];
                // GET related record
                $openApiSpec["paths"][$relationshipPath]["get"] = get_record_by_id(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["table"]}/{{$dataModel[$relSpec["table"]]["keyFld"]}}",
//                        "{$resourceName}"
                    ],
                    $relSpec["table"],$dataModel[$relSpec["table"]],$dataModel,
                    [
                        "summary"=>"Get related {$relSpec["table"]} record of {$resourceName}/{id}"
                    ]);

                // UPDATE related record
                $openApiSpec["paths"][$relationshipPath]["patch"] = update_single_record(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["table"]}/{{$dataModel[$relSpec["table"]]["keyFld"]}}",
//                        "{$resourceName}"
                    ],$relSpec["table"],$dataModel[$relSpec["table"]],$dataModel,
                    [
                        "summary"=>"Update related {$relSpec["table"]} record of {$resourceName}/{id}"
                    ]);

                // DELETE related record
                // UPDATE related record
                $openApiSpec["paths"][$relationshipPath]["delete"] = delete_single_record(
                    [
                        "{$resourceName}/{{$resIdFld}}/{$relSpec["table"]}/{{$dataModel[$relSpec["table"]]["keyFld"]}}",
//                        "{$resourceName}"
                    ],$relSpec["table"],$dataModel[$relSpec["table"]],$dataModel,
                    [
                        "summary"=>"Delete related {$relSpec["table"]} record of {$resourceName}/{id}"
                    ]);
            }

        }

    }

    return $openApiSpec;
}


function mergeRecursive(array $a, array $b): array {
    foreach ($b as $key => $value) {
        if (array_key_exists($key, $a) && is_array($a[$key]) && is_array($value)) {
            $a[$key] = mergeRecursive($a[$key], $value);
        } else {
            $a[$key] = $value;
        }
    }
    return $a;
}