<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 4:38 PM
 */

namespace JSONApi;


class Relationship extends json_ready
{
    protected $data;
    /**
     * @var bool flag used to indicate that relation is of type 1:1
     */
    private $type_one2one;
    protected $links;
    /**
     * @var Meta
     */
    protected $meta;
    /**
     * @var bool flag used to indicate that for the specified relationship no data is available (valid for 1:n relations)
     */
    private $nodata=true;
    /**
     * @var int total number of relations when relation type is 1:n
     */
    private $total;





    /**
     * @param $data
     * @param null $links
     * @return mixed
     * @throws \Exception
     */
    static function factory($data,$links=null)
    {
        if(!is_null($links) && !($links instanceof  Links))
            throw new \Exception("Invalid Links object",500);

        if(is_null($data)) {
            return self::factory_one2one(null,$links);
        }
        switch (get_class($data)) {
            case "Record":
                return self::factory_one2one($data,
                    $links);
                break;
            case "RecordSet":
                return self::factory_one2many($data,$links);
                break;

        }
    }


    /**
     * @param array $data
     * @param int $total
     * @param Links $links
     * @return Relationship
     * @throws \Exception
     */
    static private function factory_one2many($data,$links)
    {
        $rs = new self(false);

        if(!is_null($links))
            $rs->setLinks($links);

        $rs->meta = Meta::factory(["total"=>$data->total]);

        $rs->setTotal($data->total);
        foreach ($data->records as $relData) {
                //echo  "sda";
            $rs->addRelation(
                ResourceIdentifier::factory($relData)
            );
        }

        return $rs;
    }

    /**
     * @param $data
     * @param Links $links
     * @return mixed
     * @throws \Exception
     */
    static private function factory_one2one($data,$links)
    {

        $rs = new self(true);
        if(!is_null($links))
            $rs->setLinks($links);

        if(is_null($data)) {
            $rs->addRelation(null);
            unset($rs->meta);
            return $rs;
        }

        $rs->addRelation(
            ResourceIdentifier::factory($data)
        );
        return $rs;
    }

    /**
     * Relationships constructor.
     * @param $type_one2one
     */
    private function __construct ($type_one2one)
    {
        $this->type_one2one = $type_one2one;
        if(!$type_one2one)
            $this->data = [];
    }

    /**
     * @param $links
     * @return $this
     */
    function &setLinks($links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * @param ResourceIdentifier $rel
     */
    function addRelation($rel)
    {
        $this->nodata = false;
        if($this->type_one2one)
            $this->data  = $rel;
        else
            $this->data[] = $rel;
    }

    /**
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    function getRelation($name)
    {
        if(isset($this->rels[$name]))
            return $this->rels[$name];
        throw new \Exception("Invalid relation $name",500);
    }

    function json_data ()
    {
        if(property_exists($this,"meta") && empty($this->meta))
            unset($this->meta);

        return parent::json_data();
    }

    private function setTotal ($total)
    {
        $this->total = $total;
    }


}