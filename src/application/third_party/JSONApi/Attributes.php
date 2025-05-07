<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 4:33 PM
 */

namespace JSONApi;

/**
 * Class Attributes
 * @package JSONApi
 */
class Attributes extends json_ready
{
    /**
     * @var object
     */
    private $attributes;

    static function factory($attributes)
    {
        return new self($attributes);
    }

    /**
     * @param object $attributes
     */
    function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @return object
     */
    function getAttributes()
    {
        return $this->attributes;
    }

    private function __construct ($attributes)
    {
        foreach ($attributes as $attr=>$value) {
            $this->$attr = $value;
        }
    }
}