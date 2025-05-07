<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 4:46 PM
 */

namespace JSONApi;


class Meta extends json_ready
{

    static function factory($data)
    {
        if(is_object($data) || is_array($data))
            return new self($data);
        throw new \Exception("Invalid Meta ".json_encode($data),500);
    }

    private function __construct ($data)
    {
        foreach ($data as $key=>$val) {
            $this->$key = $val;
        }
    }

}