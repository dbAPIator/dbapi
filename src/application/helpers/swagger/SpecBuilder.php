<?php
/**
 * OpenAPI document assembly, generation, and persistence.
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
                    "description"=>"INTERNAL SERVER ERROR",
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
                    "description"=>"OK — no body is returned (e.g. successful delete)",
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
        if($resourceSpecifications["type"]=="table")
            $openApiSpec["paths"][$resourcesPath]["post"] = create_records([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        /************************************************
         * path: /resourceName/{resource_key}
         ***********************************************/
        if (empty($resourceSpecifications['keyFld'])) {
            continue;
        }

        $parentPathParam = swagger_path_param_name($resourceName, $resourceSpecifications['keyFld']);
        $singleResourcePath = swagger_single_resource_path($resourceName, $resourceSpecifications);
        $openApiSpec["paths"][$singleResourcePath] = [];

        // GET
        $openApiSpec["paths"][$singleResourcePath]["get"] = get_record_by_id([$resourceName],$resourceName,$resourceSpecifications,$dataModel);
        
        // UPDATE
        if($resourceSpecifications["type"]=="table")
            $openApiSpec["paths"][$singleResourcePath]["patch"] = update_single_record([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        // DELETE
        if($resourceSpecifications["type"]=="table")
            $openApiSpec["paths"][$singleResourcePath]["delete"] = delete_single_record([$resourceName],$resourceName,$resourceSpecifications,$dataModel);

        $relations = resource_relations($resourceSpecifications);
        if(empty($relations))
            continue;

        foreach ($relations as $relName=>$relSpec) {
            /************************************************
             * Related resources: /parent/{parentId}/relationName[/childId]
             ***********************************************/
            $relationshipPath = "$singleResourcePath/$relName";
            $openApiSpec["paths"][$relationshipPath] = [];
            $parentCtx = [[$resourceName, $resourceSpecifications, $parentPathParam]];
            $parentTag = "{$resourceName}/{".$parentPathParam."}/{$relName}";

            if($relSpec['type']=="outbound") {
                $openApiSpec["paths"][$relationshipPath]["get"] = get_outbound_related_record(
                    [$parentTag],
                    $resourceName,
                    $resourceSpecifications,
                    $parentPathParam,
                    $relName,
                    $relSpec,
                    $dataModel
                );

                if(($dataModel[$relSpec["table"]]["type"] ?? '') === 'table') {
                    $openApiSpec["paths"][$relationshipPath]["patch"] = update_outbound_related_record(
                        [$parentTag],
                        $resourceName,
                        $resourceSpecifications,
                        $parentPathParam,
                        $relName,
                        $relSpec,
                        $dataModel
                    );
                }
            }
            else {
                $childResource = $relSpec["table"];
                $childSpec = $dataModel[$childResource];

                $openApiSpec["paths"][$relationshipPath]["get"] = get_records(
                    ["{$parentTag}/{$childResource}"],
                    $childResource,
                    $childSpec,
                    $dataModel,
                    ["summary"=>"Get related {$childResource} records of {$resourceName}"],
                    $parentCtx
                );

                if(($childSpec["type"] ?? '') === "table") {
                    $openApiSpec["paths"][$relationshipPath]["post"] = create_records(
                        ["{$parentTag}/{$childResource}"],
                        $childResource,
                        $childSpec,
                        $dataModel,
                        ["summary"=>"Create related {$childResource} records under {$resourceName}"],
                        $parentCtx
                    );
                }

                if(empty($childSpec["keyFld"]))
                    continue;

                $childPathParam = swagger_path_param_name($childResource, $childSpec['keyFld']);
                $nestedPath = $relationshipPath . '/{' . $childPathParam . '}';
                $openApiSpec["paths"][$nestedPath] = [];
                $nestedCtx = array_merge($parentCtx, [[$childResource, $childSpec, $childPathParam]]);
                $nestedTag = "{$parentTag}/{$childResource}";

                $openApiSpec["paths"][$nestedPath]["get"] = get_record_by_id(
                    [$nestedTag],
                    $childResource,
                    $childSpec,
                    $dataModel,
                    ["summary"=>"Get one related {$childResource} record of {$resourceName}"],
                    $nestedCtx
                );

                if(($childSpec["type"] ?? '') === "table") {
                    $openApiSpec["paths"][$nestedPath]["patch"] = update_single_record(
                        [$nestedTag],
                        $childResource,
                        $childSpec,
                        $dataModel,
                        ["summary"=>"Update one related {$childResource} record of {$resourceName}"],
                        $nestedCtx
                    );

                    $openApiSpec["paths"][$nestedPath]["delete"] = delete_single_record(
                        [$nestedTag],
                        $childResource,
                        $childSpec,
                        $dataModel,
                        ["summary"=>"Delete one related {$childResource} record of {$resourceName}"],
                        $nestedCtx
                    );
                }
            }

        }

    }

    return $openApiSpec;
}

/**
 * Cached OpenAPI document filename inside each API config directory.
 */
function openapi_spec_filename(): string
{
    $CI = function_exists('get_instance') ? get_instance() : null;
    if ($CI && $CI->config) {
        $files = $CI->config->item('files');
        if (is_array($files) && !empty($files['openapi'])) {
            return $files['openapi'];
        }
    }
    return 'openapi.json';
}

function openapi_spec_path(string $apiDir): string
{
    return rtrim($apiDir, '/') . '/' . openapi_spec_filename();
}

/**
 * Effective schema for OpenAPI generation (structure.php merged with patch.php on disk).
 */
function api_structure_for_openapi(string $apiDir, ?array $structure = null): array
{
    if ($structure !== null) {
        return is_array($structure) ? $structure : [];
    }

    $apiDir = rtrim($apiDir, '/');
    $structureFile = $apiDir . '/structure.php';
    if (!is_file($structureFile)) {
        return [];
    }

    $structure = @include $structureFile;
    if (!is_array($structure)) {
        return [];
    }

    $patchFile = $apiDir . '/patch.php';
    if (!is_file($patchFile)) {
        return $structure;
    }

    if (!function_exists('smart_array_merge_recursive')) {
        require_once APPPATH . 'helpers/config_util_helper.php';
    }

    $patch = @include $patchFile;
    if (!is_array($patch) || $patch === []) {
        return $structure;
    }

    if (!function_exists('schema_patch_apply_overrides')) {
        require_once APPPATH . 'helpers/config_util_helper.php';
    }

    return smart_array_merge_recursive($structure, schema_patch_apply_overrides($patch));
}

function api_openapi_data_url(string $baseUrl, string $apiName): string
{
    if (!function_exists('is_single_deployment_mode')) {
        require_once APPPATH . 'helpers/deployment_helper.php';
    }
    if (is_single_deployment_mode()) {
        return rtrim($baseUrl, '/') . '/v1/data';
    }
    return rtrim($baseUrl, '/') . '/v1/apis/' . rawurlencode($apiName) . '/data';
}

/**
 * Replace servers[].url with the public data-plane base URL for the current request.
 */
function with_api_openapi_servers_url(array $spec, string $apiName, ?string $baseUrl = null): array
{
    if ($baseUrl === null) {
        if (!function_exists('api_public_base_url')) {
            require_once APPPATH . 'helpers/deployment_helper.php';
        }
        $baseUrl = api_public_base_url();
    }
    $spec['servers'] = [['url' => api_openapi_data_url($baseUrl, $apiName)]];
    return $spec;
}

function mgmt_openapi_spec_path(): string
{
    return APPPATH . '../public/management-openapi.json';
}

/**
 * @return array<string,mixed>
 */
function read_mgmt_openapi_spec(): array
{
    $path = mgmt_openapi_spec_path();
    if (!is_file($path)) {
        throw new RuntimeException('Management OpenAPI spec not found');
    }
    $spec = json_decode((string) file_get_contents($path), true);
    if (!is_array($spec)) {
        throw new RuntimeException('Management OpenAPI spec is invalid JSON');
    }
    return $spec;
}

/**
 * Replace servers[].url with the public instance base URL for the current request.
 *
 * @param array<string,mixed> $spec
 * @return array<string,mixed>
 */
function with_mgmt_openapi_servers_url(array $spec, ?string $baseUrl = null): array
{
    if ($baseUrl === null) {
        if (!function_exists('api_public_base_url')) {
            require_once APPPATH . 'helpers/deployment_helper.php';
        }
        $baseUrl = api_public_base_url();
    }
    $spec['servers'] = [['url' => rtrim($baseUrl, '/')]];
    return $spec;
}

function mgmt_openapi_yaml_with_servers(?string $baseUrl = null): string
{
    if ($baseUrl === null) {
        if (!function_exists('api_public_base_url')) {
            require_once APPPATH . 'helpers/deployment_helper.php';
        }
        $baseUrl = api_public_base_url();
    }
    $path = APPPATH . '../public/management-openapi.yaml';
    if (!is_file($path)) {
        throw new RuntimeException('Management OpenAPI YAML not found');
    }
    $yaml = (string) file_get_contents($path);
    $patched = preg_replace(
        '/^(servers:\s*\n\s*- url: ).+$/m',
        '${1}' . rtrim($baseUrl, '/'),
        $yaml,
        1,
        $count
    );
    if ($count !== 1) {
        throw new RuntimeException('Could not patch servers URL in management-openapi.yaml');
    }
    return $patched;
}

/**
 * Validate a generated data-plane OpenAPI document before persisting.
 *
 * @return array{valid:bool,errors:array<int,string>,warnings:array<int,string>,summary:array<string,mixed>}
 */
function validate_data_api_openapi_spec(array $spec): array
{
    require_once APPPATH . 'libraries/OpenApiSpecValidator.php';
    return OpenApiSpecValidator::validate($spec);
}

/**
 * Build and persist openapi.json for an API (call after structure save / patch / rebuild).
 *
 * @throws RuntimeException when generation, validation, or write fails
 */
function write_api_openapi_spec(string $apiName, string $apiDir, string $baseUrl, ?array $structure = null): bool
{
    if (!class_exists(\dbAPI\API\Datamodel::class, false)) {
        require_once APPPATH . 'third_party/dbAPI/Autoloader.php';
        \dbAPI\Autoloader::register();
    }

    $structure = api_structure_for_openapi($apiDir, $structure);
    if (empty($structure)) {
        $structureFile = rtrim($apiDir, '/') . '/structure.php';
        if (!is_file($structureFile)) {
            throw new RuntimeException('Cannot generate OpenAPI: structure.php missing for ' . $apiName);
        }
        if (!is_readable($structureFile)) {
            throw new RuntimeException('Cannot generate OpenAPI: structure.php not readable for ' . $apiName);
        }
        throw new RuntimeException('Cannot generate OpenAPI: structure is empty for ' . $apiName);
    }

    $dm = \dbAPI\API\Datamodel::init($structure);
    $spec = generate_swagger(
        api_openapi_data_url($baseUrl, $apiName),
        $dm->get_dataModel(),
        "{$apiName} Spec",
        "{$apiName} spec",
        $apiName,
        'test@user.com'
    );

    $validation = validate_data_api_openapi_spec($spec);
    if (!$validation['valid']) {
        throw new RuntimeException(
            'Generated OpenAPI spec failed validation: ' . implode('; ', $validation['errors'])
        );
    }

    $path = openapi_spec_path($apiDir);
    $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode OpenAPI spec for ' . $apiName);
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create directory for OpenAPI spec: ' . $dir);
    }

    $tmp = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('Failed to write temporary OpenAPI spec to ' . $tmp);
    }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Failed to publish OpenAPI spec to ' . $path);
    }
    @chmod($path, 0644);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($path, true);
    }
    return true;
}

/**
 * @return array<string,mixed>|null
 */
function read_api_openapi_spec(string $apiDir): ?array
{
    $path = openapi_spec_path($apiDir);
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}