<?php


namespace Softaccel\Apiator\DBApi;

class DBAPIRequest {
    /**
     * resource name
     * @var string
     */
    public $resourceName;    // resource name to be fetched (can be name of a table/view or relation name)

    /**
     * record ID
     * @var string|null
     */
    public $resourceId = null;

    /**
     * @var string|null
     */
    public $primaryKey = null;

    /**
     * filter conditions
     * @var array|string
     */
    public $filter = [];                     // filters (where)

    /**
     * array of fields to include
     * @var array|string
     */
    public $fields = [];                     // fields to fetch

    public $fieldsIndexes = [];
    /**
     * array of includes
     * @var DBAPIRequest[]
     */
    public $include = [];                    // relations to include

    /**
     * pagging parameter
     * @var array
     */
    public $page = [
        "offset"=>0,
        "limit"=>10
    ];

    public $offset=0;
    public $limit=10;

    /**
     * array of sort options
     * @var array|string
     */
    public $sort = [];                       // sort options

    /**
     * customer where query
     * @var string|null
     */
    public $filter_advanced = null;



    /**
     * sets on duplicate behaviour: ignore, update, error
     * @var string|null
     */
    public $onduplicate = "error";

    /**
     * array of fields to update when onduplicate=update
     * @var array|string
     */
    public $update = [];

    /**
     * if true insert ignore will be applied on inserts
     * @var boolean
     */
    public $insertignore = false;

    /**
     * relationship type [inbound|outbound]
     * @var array|null
     */
    public $relSpec = null;

    public $selectFieldsOffset;

    /**
     * @param $fldName
     * @return $this
     */
    public function &add_field($fldName) {
        $this->fields[] = $fldName;
        $this->fieldsIndexes[$fldName] = count($this->fields)-1;
        return $this;
    }

    /**
     * @param $expression
     * @return $this
     * @throws \Apiator\DBApi\Exception
     */
    public function &add_filter($expression) {

        if(!$expression)
            return $this;
        $validOps = ["!=","=","<","<=",">",">=","><","~=","!~=","~=~","!~=~","=~","!=~","<>","!><"];

        if(!preg_match("/(\w+)([\>\<\=\~\!]+)(.*)/",$expression,$matches)) {
            throw new \Apiator\DBApi\Exception("Invalid filtering expression $expression ".
                "for resource $this->resourceName",400);
        }

        if(!in_array($matches[2],$validOps)) {
            throw new \Apiator\DBApi\Exception("Invalid comparison operator in".
                " filtering expression $expression for resource $this->resourceName",400);
        }

        $this->filter[] = [
            "left"=>$matches[1],
            "op"=>$matches[2],
            "right"=>$matches[3],
        ];
        return $this;
    }

    function __construct($resourceName,$defaultPageSize)
    {
        $this->resourceName = $resourceName;
        $this->page["limit"] = $defaultPageSize;
    }
}