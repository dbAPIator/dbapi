<?php


namespace dbAPI\API;

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
     * Filter AST (compare / and / or nodes) or legacy flat list of comparisons.
     * @var array|string|null
     */
    public $filter = [];

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

    /**
     * Relationship name on the parent resource (outbound local FK column name).
     * @var string|null
     */
    public $relName = null;

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
     * @throws \dbAPI\API\Exception
     */
    /**
     * @param string $expression
     * @return $this
     * @throws Exception
     */
    public function &add_filter($expression) {

        if(!$expression)
            return $this;

        try {
            $compare = FilterParser::parseComparison($expression);
        } catch (Exception $e) {
            throw new Exception("Invalid filtering expression $expression ".
                "for resource $this->resourceName: ".$e->getMessage(), 400);
        }

        $this->filter = FilterParser::addCompare($this->filter, $compare['left'], $compare['op'], $compare['right']);
        return $this;
    }

    /**
     * @param string $expression
     * @return $this
     * @throws Exception
     */
    public function &set_filter_from_string($expression) {
        if (!$expression) {
            $this->filter = [];
            return $this;
        }
        try {
            $this->filter = FilterParser::parse($expression);
        } catch (Exception $e) {
            throw new Exception("Invalid filter for resource $this->resourceName: ".$e->getMessage(), 400);
        }
        return $this;
    }

    /**
     * @param string $left
     * @param string $op
     * @param string $right
     * @return $this
     */
    public function &add_filter_condition($left, $op, $right) {
        $this->filter = FilterParser::addCompare($this->filter, $left, $op, (string) $right);
        return $this;
    }

    /**
     * @param string $field
     * @return $this
     */
    public function &remove_filters_on_field($field) {
        $this->filter = FilterParser::removeCompareOnField($this->filter, $field) ?? [];
        return $this;
    }

    function __construct($resourceName,$defaultPageSize)
    {
        $this->resourceName = $resourceName;
        $this->page["limit"] = $defaultPageSize;
    }
}