<?php
return [
    "access_rights"=> [
        "fields"=> [
            "group"=> [
                "description"=> "",
                "name"=> "group",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "groups",
                    "field"=> "id"
                ]
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "read"=> [
                "description"=> "",
                "name"=> "read",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "resource"=> [
                "description"=> "",
                "name"=> "resource",
                "comment"=> "",
                "type"=> [
                    "proto"=> "text"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "write"=> [
                "description"=> "",
                "name"=> "write",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "access_rights",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "group"=> [
                "table"=> "groups",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "group"
            ]
        ]
    ],
    "catalog"=> [
        "fields"=> [
            "activ"=> [
                "description"=> "",
                "name"=> "activ",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "1"
            ],
            "category"=> [
                "description"=> "",
                "name"=> "category",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "comments"=> [
                "description"=> "",
                "name"=> "comments",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "orders_items",
                        "field"=> "product_id"
                    ],
                    [
                        "table"=> "product_structure",
                        "field"=> "product_id"
                    ],
                    [
                        "table"=> "product_structure",
                        "field"=> "component_id"
                    ],
                    [
                        "table"=> "stocks_details",
                        "field"=> "product_id"
                    ],
                    [
                        "table"=> "stocks_logs",
                        "field"=> "product_id"
                    ]
                ]
            ],
            "last_update"=> [
                "description"=> "",
                "name"=> "last_update",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "loss"=> [
                "description"=> "",
                "name"=> "loss",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "model"=> [
                "description"=> "",
                "name"=> "model",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "stock"=> [
                "description"=> "",
                "name"=> "stock",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "stocks",
                    "field"=> "id"
                ]
            ],
            "stock_qty"=> [
                "description"=> "",
                "name"=> "stock_qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "stock_value"=> [
                "description"=> "",
                "name"=> "stock_value",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "set",
                    "vals"=> [
                        "service",
                        "product",
                        "product_composed"
                    ]
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "unit"=> [
                "description"=> "",
                "name"=> "unit",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "config_units",
                    "field"=> "unit"
                ]
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0.00"
            ],
            "vendor"=> [
                "description"=> "",
                "name"=> "vendor",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "weight"=> [
                "description"=> "",
                "name"=> "weight",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "catalog",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "unit"=> [
                "table"=> "config_units",
                "field"=> "unit",
                "type"=> "outbound",
                "fkfield"=> "unit"
            ],
            "stock"=> [
                "table"=> "stocks",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "stock"
            ],
            "orders_items"=> [
                "table"=> "orders_items",
                "field"=> "product_id",
                "type"=> "inbound"
            ],
            "product_structure"=> [
                "table"=> "product_structure",
                "field"=> "component_id",
                "type"=> "inbound"
            ],
            "product_structure_component_id"=> [
                "table"=> "product_structure",
                "field"=> "component_id",
                "type"=> "inbound"
            ],
            "product_structure_product_id"=> [
                "table"=> "product_structure",
                "field"=> "product_id",
                "type"=> "inbound"
            ],
            "stocks_details"=> [
                "table"=> "stocks_details",
                "field"=> "product_id",
                "type"=> "inbound"
            ],
            "stocks_logs"=> [
                "table"=> "stocks_logs",
                "field"=> "product_id",
                "type"=> "inbound"
            ]
        ]
    ],
    "comenzi_incasari_zilnic"=> [
        "fields"=> [
            "cnt"=> [
                "description"=> "",
                "name"=> "cnt",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "d"=> [
                "description"=> "",
                "name"=> "d",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "icnt"=> [
                "description"=> "",
                "name"=> "icnt",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "33"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "incasat"=> [
                "description"=> "",
                "name"=> "incasat",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "m"=> [
                "description"=> "",
                "name"=> "m",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "s"=> [
                "description"=> "",
                "name"=> "s",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "y"=> [
                "description"=> "",
                "name"=> "y",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "comenzi_saptamanal"=> [
        "fields"=> [
            "cnt"=> [
                "description"=> "",
                "name"=> "cnt",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "22"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "s"=> [
                "description"=> "",
                "name"=> "s",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "w"=> [
                "description"=> "",
                "name"=> "w",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "y"=> [
                "description"=> "",
                "name"=> "y",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "comenzi_zilnic"=> [
        "fields"=> [
            "cnt"=> [
                "description"=> "",
                "name"=> "cnt",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "d"=> [
                "description"=> "",
                "name"=> "d",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "33"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "m"=> [
                "description"=> "",
                "name"=> "m",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "s"=> [
                "description"=> "",
                "name"=> "s",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "y"=> [
                "description"=> "",
                "name"=> "y",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "config_cont_conta"=> [
        "fields"=> [
            "cont"=> [
                "description"=> "",
                "name"=> "cont",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "10"
                ],
                "iskey"=> true,
                "required"=> true,
                "default"=> null
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> true,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "config_cont_conta",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "cont"
    ],
    "config_doc_numbers"=> [
        "fields"=> [
            "current_no"=> [
                "description"=> "",
                "name"=> "current_no",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "serie"=> [
                "description"=> "",
                "name"=> "serie",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "10"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> ""
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> true,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "document_types",
                    "field"=> "type"
                ]
            ],
            "valid_from"=> [
                "description"=> "",
                "name"=> "valid_from",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "valid_to"=> [
                "description"=> "",
                "name"=> "valid_to",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "2038-01-01 00=>00=>00"
            ]
        ],
        "name"=> "config_doc_numbers",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "type",
        "relations"=> [
            "type"=> [
                "table"=> "document_types",
                "field"=> "type",
                "type"=> "outbound",
                "fkfield"=> "type"
            ]
        ]
    ],
    "config_units"=> [
        "fields"=> [
            "unit"=> [
                "description"=> "",
                "name"=> "unit",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> true,
                "required"=> true,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "catalog",
                        "field"=> "unit"
                    ]
                ]
            ]
        ],
        "name"=> "config_units",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "unit",
        "relations"=> [
            "catalog"=> [
                "table"=> "catalog",
                "field"=> "unit",
                "type"=> "inbound"
            ]
        ]
    ],
    "consum_agregate"=> [
        "fields"=> [
            "m"=> [
                "description"=> "",
                "name"=> "m",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "sum(sl.loss_qty)"=> [
                "description"=> "",
                "name"=> "sum(sl.loss_qty)",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "sum(sl.order_qty)"=> [
                "description"=> "",
                "name"=> "sum(sl.order_qty)",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "20,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "sum(sl.qty)"=> [
                "description"=> "",
                "name"=> "sum(sl.qty)",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "20,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "weight"=> [
                "description"=> "",
                "name"=> "weight",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "y"=> [
                "description"=> "",
                "name"=> "y",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "consum_lunar"=> [
        "fields"=> [
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "20,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "val"=> [
                "description"=> "",
                "name"=> "val",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "ym"=> [
                "description"=> "",
                "name"=> "ym",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "7"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "docs_w_partner_name"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "external_doc_id"=> [
                "description"=> "",
                "name"=> "external_doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "is_draft"=> [
                "description"=> "",
                "name"=> "is_draft",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "1"
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "partner_name"=> [
                "description"=> "",
                "name"=> "partner_name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "user"=> [
                "description"=> "",
                "name"=> "user",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "document_types"=> [
        "fields"=> [
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> " utf8_general_ci ",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> true,
                "required"=> true,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "config_doc_numbers",
                        "field"=> "type"
                    ],
                    [
                        "table"=> "documents",
                        "field"=> "type"
                    ],
                    [
                        "table"=> "stocks_logs",
                        "field"=> "type"
                    ]
                ]
            ]
        ],
        "name"=> "document_types",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "type",
        "relations"=> [
            "config_doc_numbers"=> [
                "table"=> "config_doc_numbers",
                "field"=> "type",
                "type"=> "inbound"
            ],
            "documents"=> [
                "table"=> "documents",
                "field"=> "type",
                "type"=> "inbound"
            ],
            "stocks_logs"=> [
                "table"=> "stocks_logs",
                "field"=> "type",
                "type"=> "inbound"
            ]
        ]
    ],
    "documents"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "doc_serie"=> [
                "description"=> "",
                "name"=> "doc_serie",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "10"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "external_doc_id"=> [
                "description"=> "",
                "name"=> "external_doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "filepath"=> [
                "description"=> "",
                "name"=> "filepath",
                "comment"=> "",
                "type"=> [
                    "proto"=> "text"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "filetype"=> [
                "description"=> "",
                "name"=> "filetype",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "100"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "orders",
                        "field"=> "doc_id"
                    ],
                    [
                        "table"=> "stocks_logs",
                        "field"=> "doc_id"
                    ]
                ]
            ],
            "is_draft"=> [
                "description"=> "",
                "name"=> "is_draft",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "1"
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "partners",
                    "field"=> "id"
                ]
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "document_types",
                    "field"=> "type"
                ]
            ],
            "user"=> [
                "description"=> "",
                "name"=> "user",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "users",
                    "field"=> "id"
                ]
            ]
        ],
        "name"=> "documents",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "type"=> [
                "table"=> "document_types",
                "field"=> "type",
                "type"=> "outbound",
                "fkfield"=> "type"
            ],
            "partner"=> [
                "table"=> "partners",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "partner"
            ],
            "user"=> [
                "table"=> "users",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "user"
            ],
            "orders"=> [
                "table"=> "orders",
                "field"=> "doc_id",
                "type"=> "inbound"
            ],
            "stocks_logs"=> [
                "table"=> "stocks_logs",
                "field"=> "doc_id",
                "type"=> "inbound"
            ]
        ]
    ],
    "fisa_magazie"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "doc_serie"=> [
                "description"=> "",
                "name"=> "doc_serie",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "10"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "documents_id"=> [
                "description"=> "",
                "name"=> "documents_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "order_id"=> [
                "description"=> "",
                "name"=> "order_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "fisa_magazie_old"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "doc_serie"=> [
                "description"=> "",
                "name"=> "doc_serie",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "10"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "order_id"=> [
                "description"=> "",
                "name"=> "order_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "groups"=> [
        "fields"=> [
            "admin"=> [
                "description"=> "",
                "name"=> "admin",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "users",
                    "field"=> "id"
                ]
            ],
            "description"=> [
                "description"=> "",
                "name"=> "description",
                "comment"=> "",
                "type"=> [
                    "proto"=> "text"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "access_rights",
                        "field"=> "group"
                    ]
                ]
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "groups",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "admin"=> [
                "table"=> "users",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "admin"
            ],
            "access_rights"=> [
                "table"=> "access_rights",
                "field"=> "group",
                "type"=> "inbound"
            ]
        ]
    ],
    "incasari_saptamanal"=> [
        "fields"=> [
            "cnt"=> [
                "description"=> "",
                "name"=> "cnt",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "22"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "s"=> [
                "description"=> "",
                "name"=> "s",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "w"=> [
                "description"=> "",
                "name"=> "w",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "y"=> [
                "description"=> "",
                "name"=> "y",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "incasari_zilnic"=> [
        "fields"=> [
            "cnt"=> [
                "description"=> "",
                "name"=> "cnt",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "d"=> [
                "description"=> "",
                "name"=> "d",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "33"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "m"=> [
                "description"=> "",
                "name"=> "m",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "s"=> [
                "description"=> "",
                "name"=> "s",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "y"=> [
                "description"=> "",
                "name"=> "y",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "locations"=> [
        "fields"=> [
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "stocks",
                        "field"=> "location"
                    ]
                ]
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> true,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "locations",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "stocks"=> [
                "table"=> "stocks",
                "field"=> "location",
                "type"=> "inbound"
            ]
        ]
    ],
    "order_items_states"=> [
        "fields"=> [
            "order_id"=> [
                "description"=> "",
                "name"=> "order_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "status"=> [
                "description"=> "",
                "name"=> "status",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "ordered",
                        "processing",
                        "ready",
                        "delivered",
                        "canceled",
                        "offer"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "order_payed"=> [
        "fields"=> [
            "order_id"=> [
                "description"=> "",
                "name"=> "order_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "order_total"=> [
        "fields"=> [
            "order_id"=> [
                "description"=> "",
                "name"=> "order_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "orders"=> [
        "fields"=> [
            "creation_date"=> [
                "description"=> "",
                "name"=> "creation_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "documents",
                    "field"=> "id"
                ]
            ],
            "estimated_delivery_date"=> [
                "description"=> "",
                "name"=> "estimated_delivery_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "orders_items",
                        "field"=> "order_id"
                    ],
                    [
                        "table"=> "receipts",
                        "field"=> "order"
                    ]
                ]
            ],
            "last_update"=> [
                "description"=> "",
                "name"=> "last_update",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "partners",
                    "field"=> "id"
                ]
            ],
            "status"=> [
                "description"=> "",
                "name"=> "status",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "offer",
                        "ordered",
                        "processing",
                        "ready",
                        "delivered",
                        "canceled"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "ordered"
            ],
            "to_pay"=> [
                "description"=> "",
                "name"=> "to_pay",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0.00"
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "total_wtva"=> [
                "description"=> "",
                "name"=> "total_wtva",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "user_id"=> [
                "description"=> "",
                "name"=> "user_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "users",
                    "field"=> "id"
                ]
            ]
        ],
        "name"=> "orders",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "doc_id"=> [
                "table"=> "documents",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "doc_id"
            ],
            "partner"=> [
                "table"=> "partners",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "partner"
            ],
            "user_id"=> [
                "table"=> "users",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "user_id"
            ],
            "orders_items"=> [
                "table"=> "orders_items",
                "field"=> "order_id",
                "type"=> "inbound"
            ],
            "receipts"=> [
                "table"=> "receipts",
                "field"=> "order",
                "type"=> "inbound"
            ]
        ]
    ],
    "orders_items"=> [
        "fields"=> [
            "comments"=> [
                "description"=> "",
                "name"=> "comments",
                "comment"=> "",
                "type"=> [
                    "proto"=> "text"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "creation_date"=> [
                "description"=> "",
                "name"=> "creation_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "datetime"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "discount"=> [
                "description"=> "",
                "name"=> "discount",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "orders_items",
                        "field"=> "parent_id"
                    ],
                    [
                        "table"=> "stocks_logs",
                        "field"=> "order_item"
                    ]
                ]
            ],
            "invoiced"=> [
                "description"=> "",
                "name"=> "invoiced",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "last_update"=> [
                "description"=> "",
                "name"=> "last_update",
                "comment"=> "",
                "type"=> [
                    "proto"=> "datetime"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "loss"=> [
                "description"=> "",
                "name"=> "loss",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "order_id"=> [
                "description"=> "",
                "name"=> "order_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "orders",
                    "field"=> "id"
                ]
            ],
            "parent_id"=> [
                "description"=> "",
                "name"=> "parent_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "orders_items",
                    "field"=> "id"
                ]
            ],
            "processed"=> [
                "description"=> "",
                "name"=> "processed",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "catalog",
                    "field"=> "id"
                ]
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "status"=> [
                "description"=> "",
                "name"=> "status",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "ordered",
                        "processing",
                        "ready",
                        "delivered",
                        "canceled",
                        "offer"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "stocks",
                    "field"=> "id"
                ]
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "total_wtva"=> [
                "description"=> "",
                "name"=> "total_wtva",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "product",
                        "product_composed",
                        "service"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "unit"=> [
                "description"=> "",
                "name"=> "unit",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "orders_items",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "order_id"=> [
                "table"=> "orders",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "order_id"
            ],
            "product_id"=> [
                "table"=> "catalog",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "product_id"
            ],
            "parent_id"=> [
                "table"=> "orders_items",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "parent_id"
            ],
            "stock_id"=> [
                "table"=> "stocks",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "stock_id"
            ],
            "orders_items"=> [
                "table"=> "orders_items",
                "field"=> "parent_id",
                "type"=> "inbound"
            ],
            "stocks_logs"=> [
                "table"=> "stocks_logs",
                "field"=> "order_item",
                "type"=> "inbound"
            ]
        ]
    ],
    "orders_status"=> [
        "fields"=> [
            "status"=> [
                "description"=> "",
                "name"=> "status",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "offer",
                        "ordered",
                        "processing",
                        "ready",
                        "delivered",
                        "canceled"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "ordered"
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "orders_wtotal"=> [
        "fields"=> [
            "creation_date"=> [
                "description"=> "",
                "name"=> "creation_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "estimated_delivery_date"=> [
                "description"=> "",
                "name"=> "estimated_delivery_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "last_update"=> [
                "description"=> "",
                "name"=> "last_update",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "partner_id"=> [
                "description"=> "",
                "name"=> "partner_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "payed"=> [
                "description"=> "",
                "name"=> "payed",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "status"=> [
                "description"=> "",
                "name"=> "status",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "offer",
                        "ordered",
                        "processing",
                        "ready",
                        "delivered",
                        "canceled"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "ordered"
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "user_id"=> [
                "description"=> "",
                "name"=> "user_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "ordersitem_w_order_id"=> [
        "fields"=> [
            "creation_date"=> [
                "description"=> "",
                "name"=> "creation_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "datetime"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0000-00-00 00=>00=>00"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "doc_serie"=> [
                "description"=> "",
                "name"=> "doc_serie",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "10"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "document_id"=> [
                "description"=> "",
                "name"=> "document_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "last_update"=> [
                "description"=> "",
                "name"=> "last_update",
                "comment"=> "",
                "type"=> [
                    "proto"=> "datetime"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0000-00-00 00=>00=>00"
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "order_id"=> [
                "description"=> "",
                "name"=> "order_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "bigint"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "parent_id"=> [
                "description"=> "",
                "name"=> "parent_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "status"=> [
                "description"=> "",
                "name"=> "status",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "ordered",
                        "processing",
                        "ready",
                        "delivered",
                        "canceled",
                        "offer"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "product",
                        "product_composed",
                        "service"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "unit"=> [
                "description"=> "",
                "name"=> "unit",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "partners"=> [
        "fields"=> [
            "address"=> [
                "description"=> "",
                "name"=> "address",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "agent"=> [
                "description"=> "",
                "name"=> "agent",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "agent_name"=> [
                "description"=> "",
                "name"=> "agent_name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "analitic"=> [
                "description"=> "",
                "name"=> "analitic",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "bank_account"=> [
                "description"=> "",
                "name"=> "bank_account",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "bank_name"=> [
                "description"=> "",
                "name"=> "bank_name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "blocked"=> [
                "description"=> "",
                "name"=> "blocked",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "branch"=> [
                "description"=> "",
                "name"=> "branch",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "branch_rep"=> [
                "description"=> "",
                "name"=> "branch_rep",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "cb_card"=> [
                "description"=> "",
                "name"=> "cb_card",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "city"=> [
                "description"=> "",
                "name"=> "city",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "cod_fiscal"=> [
                "description"=> "",
                "name"=> "cod_fiscal",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "cod_saga"=> [
                "description"=> "",
                "name"=> "cod_saga",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "comments"=> [
                "description"=> "",
                "name"=> "comments",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "country"=> [
                "description"=> "",
                "name"=> "country",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "county"=> [
                "description"=> "",
                "name"=> "county",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "create_date"=> [
                "description"=> "",
                "name"=> "create_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "credit_limit"=> [
                "description"=> "",
                "name"=> "credit_limit",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "date_s_vat"=> [
                "description"=> "",
                "name"=> "date_s_vat",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "date_v_vat"=> [
                "description"=> "",
                "name"=> "date_v_vat",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "discount"=> [
                "description"=> "",
                "name"=> "discount",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "email"=> [
                "description"=> "",
                "name"=> "email",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "group"=> [
                "description"=> "",
                "name"=> "group",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "documents",
                        "field"=> "partner"
                    ],
                    [
                        "table"=> "orders",
                        "field"=> "partner"
                    ],
                    [
                        "table"=> "receipts",
                        "field"=> "customer"
                    ]
                ]
            ],
            "is_customer"=> [
                "description"=> "",
                "name"=> "is_customer",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "1"
            ],
            "is_supplier"=> [
                "description"=> "",
                "name"=> "is_supplier",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "is_vat"=> [
                "description"=> "",
                "name"=> "is_vat",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "license_plate"=> [
                "description"=> "",
                "name"=> "license_plate",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "personal_id_issuer"=> [
                "description"=> "",
                "name"=> "personal_id_issuer",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "personal_id_number"=> [
                "description"=> "",
                "name"=> "personal_id_number",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "personal_id_serie"=> [
                "description"=> "",
                "name"=> "personal_id_serie",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "phone"=> [
                "description"=> "",
                "name"=> "phone",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "reg_com"=> [
                "description"=> "",
                "name"=> "reg_com",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type_tert"=> [
                "description"=> "",
                "name"=> "type_tert",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "zs"=> [
                "description"=> "",
                "name"=> "zs",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "name"=> "partners",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "documents"=> [
                "table"=> "documents",
                "field"=> "partner",
                "type"=> "inbound"
            ],
            "orders"=> [
                "table"=> "orders",
                "field"=> "partner",
                "type"=> "inbound"
            ],
            "receipts"=> [
                "table"=> "receipts",
                "field"=> "customer",
                "type"=> "inbound"
            ]
        ]
    ],
    "product_structure"=> [
        "fields"=> [
            "component_id"=> [
                "description"=> "",
                "name"=> "component_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "catalog",
                    "field"=> "id"
                ]
            ],
            "grp"=> [
                "description"=> "",
                "name"=> "grp",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "ingrpseq"=> [
                "description"=> "",
                "name"=> "ingrpseq",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "catalog",
                    "field"=> "id"
                ]
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ]
        ],
        "name"=> "product_structure",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "product_id"=> [
                "table"=> "catalog",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "product_id"
            ],
            "component_id"=> [
                "table"=> "catalog",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "component_id"
            ]
        ]
    ],
    "product_structure_expanded"=> [
        "fields"=> [
            "activ"=> [
                "description"=> "",
                "name"=> "activ",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "1"
            ],
            "category"=> [
                "description"=> "",
                "name"=> "category",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "comments"=> [
                "description"=> "",
                "name"=> "comments",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "grp"=> [
                "description"=> "",
                "name"=> "grp",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "ingrpseq"=> [
                "description"=> "",
                "name"=> "ingrpseq",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "inventory_id"=> [
                "description"=> "",
                "name"=> "inventory_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "last_update"=> [
                "description"=> "",
                "name"=> "last_update",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0000-00-00 00=>00=>00"
            ],
            "loss"=> [
                "description"=> "",
                "name"=> "loss",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "model"=> [
                "description"=> "",
                "name"=> "model",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "set",
                    "vals"=> [
                        "service",
                        "product",
                        "product_composed"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "unit"=> [
                "description"=> "",
                "name"=> "unit",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0.00"
            ],
            "vendor"=> [
                "description"=> "",
                "name"=> "vendor",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "receipts"=> [
        "fields"=> [
            "amount"=> [
                "description"=> "",
                "name"=> "amount",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "customer"=> [
                "description"=> "",
                "name"=> "customer",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "partners",
                    "field"=> "id"
                ]
            ],
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "order"=> [
                "description"=> "",
                "name"=> "order",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "orders",
                    "field"=> "id"
                ]
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "cash_receipt",
                        "pos_receipt",
                        "bank",
                        "cr_receipt"
                    ]
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "receipts",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "order"=> [
                "table"=> "orders",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "order"
            ],
            "customer"=> [
                "table"=> "partners",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "customer"
            ]
        ]
    ],
    "receptii_details"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "external_doc_id"=> [
                "description"=> "",
                "name"=> "external_doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "furnizor"=> [
                "description"=> "",
                "name"=> "furnizor",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "produs"=> [
                "description"=> "",
                "name"=> "produs",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "22,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "receptii_total_value"=> [
        "fields"=> [
            "documents_id"=> [
                "description"=> "",
                "name"=> "documents_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "report_receptii_items"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "id_receptie"=> [
                "description"=> "",
                "name"=> "id_receptie",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product"=> [
                "description"=> "",
                "name"=> "product",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "22,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "settings"=> [
        "fields"=> [
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "namespace"=> [
                "description"=> "",
                "name"=> "namespace",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "155"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "setting"=> [
                "description"=> "",
                "name"=> "setting",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "value"=> [
                "description"=> "",
                "name"=> "value",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "name"=> "settings",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id"
    ],
    "stocks"=> [
        "fields"=> [
            "address"=> [
                "description"=> "",
                "name"=> "address",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "cod_conta"=> [
                "description"=> "",
                "name"=> "cod_conta",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "catalog",
                        "field"=> "stock"
                    ],
                    [
                        "table"=> "orders_items",
                        "field"=> "stock_id"
                    ],
                    [
                        "table"=> "stocks_details",
                        "field"=> "stock_id"
                    ],
                    [
                        "table"=> "stocks_logs",
                        "field"=> "stock_id"
                    ]
                ]
            ],
            "is_default"=> [
                "description"=> "",
                "name"=> "is_default",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "is_raw"=> [
                "description"=> "",
                "name"=> "is_raw",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "location"=> [
                "description"=> "",
                "name"=> "location",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "locations",
                    "field"=> "id"
                ]
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "100"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "person"=> [
                "description"=> "",
                "name"=> "person",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "mat",
                        "maf",
                        "con"
                    ]
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "stocks",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "location"=> [
                "table"=> "locations",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "location"
            ],
            "catalog"=> [
                "table"=> "catalog",
                "field"=> "stock",
                "type"=> "inbound"
            ],
            "orders_items"=> [
                "table"=> "orders_items",
                "field"=> "stock_id",
                "type"=> "inbound"
            ],
            "stocks_details"=> [
                "table"=> "stocks_details",
                "field"=> "stock_id",
                "type"=> "inbound"
            ],
            "stocks_logs"=> [
                "table"=> "stocks_logs",
                "field"=> "stock_id",
                "type"=> "inbound"
            ]
        ]
    ],
    "stocks_consum_report"=> [
        "fields"=> [
            "comments"=> [
                "description"=> "",
                "name"=> "comments",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "dnd"=> [
                "description"=> "",
                "name"=> "dnd",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "document_id"=> [
                "description"=> "",
                "name"=> "document_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "has_vat"=> [
                "description"=> "",
                "name"=> "has_vat",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "loss_qty"=> [
                "description"=> "",
                "name"=> "loss_qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "name"=> [
                "description"=> "",
                "name"=> "name",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "order_date"=> [
                "description"=> "",
                "name"=> "order_date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "order_item"=> [
                "description"=> "",
                "name"=> "order_item",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "order_qty"=> [
                "description"=> "",
                "name"=> "order_qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "unit"=> [
                "description"=> "",
                "name"=> "unit",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "stocks_details"=> [
        "fields"=> [
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "catalog",
                    "field"=> "id"
                ]
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "stocks",
                    "field"=> "id"
                ]
            ],
            "value"=> [
                "description"=> "",
                "name"=> "value",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "name"=> "stocks_details",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "product_id"=> [
                "table"=> "catalog",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "product_id"
            ],
            "stock_id"=> [
                "table"=> "stocks",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "stock_id"
            ]
        ]
    ],
    "stocks_logs"=> [
        "fields"=> [
            "comments"=> [
                "description"=> "",
                "name"=> "comments",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "dnd"=> [
                "description"=> "",
                "name"=> "dnd",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> "0"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "documents",
                    "field"=> "id"
                ]
            ],
            "has_vat"=> [
                "description"=> "",
                "name"=> "has_vat",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "loss_qty"=> [
                "description"=> "",
                "name"=> "loss_qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "order_item"=> [
                "description"=> "",
                "name"=> "order_item",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "orders_items",
                    "field"=> "id"
                ]
            ],
            "order_qty"=> [
                "description"=> "",
                "name"=> "order_qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "catalog",
                    "field"=> "id"
                ]
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "stocks",
                    "field"=> "id"
                ]
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "type"=> [
                "description"=> "",
                "name"=> "type",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "50"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null,
                "foreignKey"=> [
                    "table"=> "document_types",
                    "field"=> "type"
                ]
            ],
            "unit_price"=> [
                "description"=> "",
                "name"=> "unit_price",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "name"=> "stocks_logs",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "type"=> [
                "table"=> "document_types",
                "field"=> "type",
                "type"=> "outbound",
                "fkfield"=> "type"
            ],
            "product_id"=> [
                "table"=> "catalog",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "product_id"
            ],
            "stock_id"=> [
                "table"=> "stocks",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "stock_id"
            ],
            "doc_id"=> [
                "table"=> "documents",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "doc_id"
            ],
            "order_item"=> [
                "table"=> "orders_items",
                "field"=> "id",
                "type"=> "outbound",
                "fkfield"=> "order_item"
            ]
        ]
    ],
    "stocks_realstock"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null
            ],
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "float",
                    "length"=> "10,3"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "stocks_realstock",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id"
    ],
    "transfer_lunar"=> [
        "fields"=> [
            "product_id"=> [
                "description"=> "",
                "name"=> "product_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "qty"=> [
                "description"=> "",
                "name"=> "qty",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "20,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "val"=> [
                "description"=> "",
                "name"=> "val",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "20,3"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "ym"=> [
                "description"=> "",
                "name"=> "ym",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "7"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ],
    "users"=> [
        "fields"=> [
            "fname"=> [
                "description"=> "",
                "name"=> "fname",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> true,
                "required"=> false,
                "default"=> null,
                "referencedBy"=> [
                    [
                        "table"=> "documents",
                        "field"=> "user"
                    ],
                    [
                        "table"=> "groups",
                        "field"=> "admin"
                    ],
                    [
                        "table"=> "orders",
                        "field"=> "user_id"
                    ]
                ]
            ],
            "lname"=> [
                "description"=> "",
                "name"=> "lname",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "20"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "locked"=> [
                "description"=> "",
                "name"=> "locked",
                "comment"=> "",
                "type"=> [
                    "proto"=> "tinyint",
                    "length"=> "1"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "passwordhash"=> [
                "description"=> "",
                "name"=> "passwordhash",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "100"
                ],
                "iskey"=> false,
                "required"=> true,
                "default"=> null
            ],
            "role"=> [
                "description"=> "",
                "name"=> "role",
                "comment"=> "",
                "type"=> [
                    "proto"=> "enum",
                    "vals"=> [
                        "admin",
                        "sales",
                        "prod"
                    ]
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "admin"
            ],
            "uname"=> [
                "description"=> "",
                "name"=> "uname",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "45"
                ],
                "iskey"=> true,
                "required"=> true,
                "default"=> null
            ]
        ],
        "name"=> "users",
        "description"=> "",
        "comment"=> "",
        "type"=> "table",
        "keyFld"=> "id",
        "relations"=> [
            "documents"=> [
                "table"=> "documents",
                "field"=> "user",
                "type"=> "inbound"
            ],
            "groups"=> [
                "table"=> "groups",
                "field"=> "admin",
                "type"=> "inbound"
            ],
            "orders"=> [
                "table"=> "orders",
                "field"=> "user_id",
                "type"=> "inbound"
            ]
        ]
    ],
    "valoare_receptii"=> [
        "fields"=> [
            "date"=> [
                "description"=> "",
                "name"=> "date",
                "comment"=> "",
                "type"=> [
                    "proto"=> "timestamp"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "CURRENT_TIMESTAMP"
            ],
            "doc_id"=> [
                "description"=> "",
                "name"=> "doc_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "documents_id"=> [
                "description"=> "",
                "name"=> "documents_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> "0"
            ],
            "id"=> [
                "description"=> "",
                "name"=> "id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "21"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "partner"=> [
                "description"=> "",
                "name"=> "partner",
                "comment"=> "",
                "type"=> [
                    "proto"=> "varchar",
                    "length"=> "255"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "stock_id"=> [
                "description"=> "",
                "name"=> "stock_id",
                "comment"=> "",
                "type"=> [
                    "proto"=> "int"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ],
            "total"=> [
                "description"=> "",
                "name"=> "total",
                "comment"=> "",
                "type"=> [
                    "proto"=> "double",
                    "length"=> "19,2"
                ],
                "iskey"=> false,
                "required"=> false,
                "default"=> null
            ]
        ],
        "relations"=> [],
        "description"=> "",
        "comment"=> "",
        "type"=> "view",
        "keyFld"=> null
    ]
];