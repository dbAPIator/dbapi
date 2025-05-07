<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/24/19
 * Time: 4:02 PM
 */

namespace JSONApi;


class Relationships extends json_ready
{
    function __set ($name, $value)
    {
        $this->$name = $value;
    }
}