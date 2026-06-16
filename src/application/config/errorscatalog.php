<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 9/23/19
 * Time: 9:39 AM
 */

$errorsCatalog = [];

$errorsCatalog["input"] = [
    "invalid_input_data"=>[
        "code"=>"2001",
        "message"=>"Invalid input data"
    ],
    "invalid_content_type"=>[
        "code"=>"2002",
        "message"=>"Invalid content type"
    ],
    "invalid_request"=>[
        "code"=>"2003",
        "message"=>"Invalid request"
    ],
    "invalid_json"=>[
        "code"=>"2004",
        "message"=>"Invalid JSON"
    ],
];
$errorsCatalog["access"] = [
    "ip_not_authorized"=>[
        "code"=>"4001",
        "message"=>"IP not authorized ".$_SERVER["REMOTE_ADDR"]
    ],
    "secret_not_authorized"=>[
        "code"=>"4002",
        "message"=>"Secret not authorized"
    ],
    "api_config_secret_not_authorized"=>[
        "code"=>"4003",
        "message"=>"API config key not authorized"
    ]
];
$errorsCatalog["config"] = [
    "api_exists"=>[
        "code"=>"3001",
        "message"=>"API already exists"
    ],
    "api_not_found"=>[
        "code"=>"3002",
        "message"=>"API not found"
    ],
    "db_name_not_provided"=>[
        "code"=>"3003",
        "message"=>"Database name not provided"
    ],
    "security_not_found"=>[
        "code"=>"3004",
        "message"=>"Security not found"
    ],
    "api_not_active"=>[
        "code"=>"3005",
        "message"=>"API is not active"
    ],
    "not_ready_for_activate"=>[
        "code"=>"3006",
        "message"=>"API is not ready to activate"
    ],
    "single_mode_no_create"=>[
        "code"=>"3010",
        "message"=>"Single deployment mode: the default API is auto-provisioned; creating additional APIs is not supported"
    ],
    "single_mode_no_list"=>[
        "code"=>"3011",
        "message"=>"Single deployment mode: use GET /mgmt/v1 for the instance API; listing APIs is not supported"
    ],
    "single_mode_no_delete"=>[
        "code"=>"3012",
        "message"=>"Single deployment mode: deleting the instance API is not supported"
    ],
    "single_mode_no_rename"=>[
        "code"=>"3013",
        "message"=>"Single deployment mode: the API id is fixed; renaming is not supported"
    ],

];


$config["errors_catalog"] = $errorsCatalog;