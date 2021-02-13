<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Class Recordset
 * @property array $records
 * @property int $offset
 * @property int $total
 * @property array $relatedRecords
 */
class RecordSet {
    /**
     * @var Record[]
     */
    public $records = [];
    public $offset = 0;
    public $total = 0;

    /**
     * Recordset constructor.
     * @param $records
     * @param $type
     * @param $idFld
     * @param $offset
     * @param $total
     * @param bool $dbg
     */
    public function __construct ($records, $type, $idFld, $offset=0, $total=null,$dbg=false)
    {
        $this->offset = $offset;
        $this->total = $total;
        isset($_GET["dbg1979"]) && print_r($records);
        foreach ($records as $record) {
            $this->add_record($type,$record,$idFld);
        }
    }


    /**
     * add new  Record to recordset
     * @param $type
     * @param array|Record $record
     * @param string $idFld
     * @return Record
     */
    public function add_record($type,$record,$idFld) {
        if(is_object($record) && get_class($record)=="Record") {
            $this->records[] = $record;
            return $this->records[count($this->records)-1];
        }

        $id = null;
        if($idFld && isset($record[$idFld])) {
            $id = $record[$idFld];
        }
        $this->records[] = new Record($type,$id,$record);
        return $this->records[count($this->records)-1];
    }

    /**
     * @param $rec
     * @return mixed
     */
    function &add_rec($rec)
    {
        $this->records[] = $rec;
        return $this->records[count($this->records)-1];
    }

    /**
     * @param $arr
     * @param $type
     * @param $idFld
     * @return mixed
     */
    function &add_array_as_rec($arr,$type,$idFld)
    {
        $this->records[] = new Record($type,$arr[$idFld],$arr);
        return $this->records[count($this->records)-1];
    }

}

class JSONApiError {
    public function __construct ($ini)
    {
        if(!is_array($ini))
            return;
        $props = ["id","links","status","code","title","detail","source","meta"];

        foreach ($ini as $label => $value) {
            if(in_array($label,$props))
                $this->$label = $value;
        }
    }
}

/**
 * Class JSONApiLinks
 * @property string self
 * @property string related
 * @property string next
 * @property string prev
 */
class JSONApiLinks {
    public $self;
    public $related;
    public $next;
    public $prev;

    /**
     * JSONApiLinks constructor.
     * @param $self
     * @param $related
     * @param $next
     * @param $prev
     */
    public function __construct ($self, $related=null, $next=null, $prev=null)
    {
        $this->self = $self;
        $this->related = $related==null?(object)[]:$related;
        $this->next = $next==null?(object)[]:$next;
        $this->prev = $prev==null?(object)[]:$prev;
    }
}

/**
 * Class JSONApiMeta
 * @property integer $offset
 * @property integer $total
 */
class JSONApiMeta {
    public $offset;
    public $total;

    /**
     * JSONApiMeta constructor.
     * @param $offset
     * @param $total
     */
    public function __construct ($offset=0, $total=null)
    {
        $this->offset = $offset;
        $this->total = $total;
    }

}

/**
 * Class JSONApiResponse
 * @property array|object $data
 * @property object|array $meta
 * @property object $links
 */
class JSONApiResponse {
    public $data=[];
    public $includes=[];
    public $meta=[];
    public $links=[];
    public $errors=[];
    private $root;

    /**
     * remove properties which are empy
     */
    public function cleanUp() {
        if(empty($this->meta))
            unset($this->meta);
        if(empty($this->links))
            unset($this->links);
        if(empty($this->includes))
            unset($this->includes);
        if(is_null($this->errors))
            unset($this->errors);
        if(!empty($this->errors) && empty($this->data))
            unset($this->data);
        // ToDo: add remove data when data is null & meta not null; need to implement data null
    }



    /**
     * @param bool $pretty
     * @return false|string
     */
    function toJSON($pretty=true) {
        $this->cleanUp();
        return json_encode($this, $pretty ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 0);
    }

    static function create($data, $meta=null, $links=null, $errors=null)
    {
        return new JSONApiResponse($data,$meta,$links,$errors);
    }

    /**
     * JSONApiResponse constructor.
     * @param RecordSet|Record $data
     * @param null $rootResponse
     * @param JSONApiMeta $meta
     * @param JSONApiLinks $links
     * @param null $errors
     */
    function __construct ($data, &$rootResponse=null, $meta=null, $links=null, $errors=null)
    {
        $this->meta = $meta;
        $this->links = $links==null?new stdClass():$links;
        $this->errors = $errors;
        $this->root = is_null($rootResponse)?$this:$rootResponse;

        if(get_class($data)=="RecordSet")
            foreach ($data->records as $record)
                $this->add_record($record);
        elseif(get_class($data)=="Record")
            $this->data = $this->add_record($data);
    }


    /**
     * @param Record $record
     * @return Record
     */
    function add_record($record) {
        /**
         * @param Record $rec
         * @param JSONApiResponse $rs
         */
        function parse_record($rec,&$rs)
        {
            foreach ($rec->attributes as $attr=>$val) {
                if(!is_object($val))
                    continue;


            }
        }


        if(isset($record->attributes))
            foreach ($record->attributes as $attribute=>$value) {
                if(gettype($value)=="object") {
                    $this->add_include($value);

                    $inclRecord = new Record($value->type,$value->id);
                    $record->attributes->$attribute = new JSONApiResponse($inclRecord, null,
                        new JSONApiLinks(current_url()."/".$record->id."/$attribute"));
                }
            }

        // rearrange relations
        if(count($record->relationships)) {
            foreach ($record->relationships as $label => $relRecords) {
                $tmp = [];
                foreach ($relRecords->records as $key=>$relRec) {
                    //print_r($relRec);
                    isset($_GET["dbg1979"]) && print_r($relRec);
                    $newRelRec = $this->add_include(clone $relRec);

                    $tmp[] = (object)[
                        "id" => $newRelRec->id,
                        "type" => $newRelRec->type
                    ];
                    unset($relRecords->records[$key]->attributes);
                }

                $record->relationships[$label] = new JSONApiResponse($relRecords,
                    new JSONApiMeta($relRecords->offset,$relRecords->total),
                    new JSONApiLinks(current_url()."/".$record->id."/_link/$label")
                );

            }
        }
        else
            unset($record->relationships);
        $this->data[] = $record;
        return $record;
    }

    /**
     * @param $record
     * @return bool|mixed
     */
    private function add_include($record) {
        foreach ($this->includes as $include) {
            if($include->id==$record->id && $include->type==$record->type)
                return $include;
        }
        $this->includes[] = $record;
        return $this->includes[count($this->includes)-1];
    }

}

/**
 * Class Record
 * @property string|int $id
 * @property string $type
 * @property object $attributes
 * @property array $relationships
 */
class Record {
    public $id;
    public $type;
    public $attributes;
    public $relationships=[];

    /**
     * Record constructor.
     * @param $type
     * @param $id
     * @param null $attributes
     */
    function __construct ($type, $id, $attributes=null)
    {
        $this->type = $type;
        $this->id = $id;
        if(is_null($attributes))
            unset($this->attributes);
        else
            $this->attributes = (object) $attributes;
    }

    /**
     * add relationship
     * @param $relationName
     * @param Record $record
     * @return bool
     */
    public function add_relation($relationName, &$record) {
        if(is_null($this->relationships))
            $this->relationships = [];
        if(!isset($this->relationships[$relationName]))
            $this->relationships[$relationName] = [];
        foreach ($this->relationships[$relationName] as $rel) {
            if($rel->id==$record->id) return false;
        }
        $this->relationships[$relationName][] = $record;
        return true;
    }

    /**
     * @param $relationName
     * @param $records
     * @param $type
     * @param $offset
     * @param $total
     */
    public  function add_relations($relationName,$records,$type,$offset,$total) {
        if(is_null($this->relationships))
            $this->relationships = [];

        $this->relationships[$relationName] = new RecordSet($records,$type, $offset,$total);
        isset($_GET["dbg1979"])  && print_r($this);
    }
}