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
    ]
];
$errorsCatalog["access"] = [
    "ip_not_authorized"=>[
        "code"=>"4001",
        "message"=>"IP not authorized"
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
    ]
];


$config["errors_catalog"] = $errorsCatalog;