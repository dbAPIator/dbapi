<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 4:46 PM
 */

namespace JSONApi;


class Links extends json_ready
{
    /**
     * @param $data
     * @return Links
     */
    static function factory($data)
    {

        if(is_object($data) || is_array($data)) {
            return new self($data);
        }
        return null;


    }

    private function __construct ($data)
    {

        foreach ($data as $key=>$val)
            $this->$key = $val;
    }

    public function __get ($name)
    {
        if(isset($this->$name))
            return $this->$name;
        return null;
    }

}