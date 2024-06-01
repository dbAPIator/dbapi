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
//        echo "Resiucasdiasd as ffa factory -------------------";
//        print_r($data);

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