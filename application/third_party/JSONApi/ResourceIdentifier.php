<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 8:49 PM
 */

namespace JSONApi;


class ResourceIdentifier extends json_ready
{
    /**
     * @var string|int
     */
    protected $id;
    /**
     * @var string
     */
    protected $type;
    /**
     * @var Meta
     */
    protected $meta;



    /**
     * @param $data
     * @return ResourceIdentifier
     * @throws \Exception
     */
    static function factory($data)
    {
        if(!is_object($data))
            throw new \Exception("Invalid ResourceIdentifier init data: not an object");
        if(!isset($data->type))
            throw new \Exception("Invalid ResourceIdentifier init data: no type attr");
        if(!isset($data->id))
            return null;

        $ri = new self($data->type,$data->id);

        if(isset($data->attributes)) {
            $res = Resource::factory($data);
            Document::create()->addInclude($res);
        }
        return $ri;
    }

    /**
     * ResourceLinkage constructor.
     * @param $type
     * @param $id
     * @param $meta
     */
    function __construct ($type,$id)
    {
        $this->type = $type;
        $this->id = $id;
    }

    function json_data ()
    {
        if(property_exists($this,"meta") && empty($this->meta))
            unset($this->meta);

        return parent::json_data();
    }
}