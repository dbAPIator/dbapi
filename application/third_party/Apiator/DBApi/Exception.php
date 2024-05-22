<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 9/23/19
 * Time: 10:52 AM
 */

namespace Apiator\DBApi;

/**
 * Class Exception
 * @package Apiator\DBApi
 */
class Exception extends \Exception
{
    /**
     * @var string $title
     */
    protected $title;
    /**
     * @var string|int $code
     */
    protected $code;
    /**
     * @var string $description
     */
    protected $description;

    /**
     * @param $data
     */
    public static function  from_error_catalog($data)
    {
        $e = new self($data["title"],$data["code"]);
        $e->title = $data["title"];
        $e->description = $data["description"];
    }

    /**
     * @return mixed
     */
    function getDescription() {
        return $this->description;
    }

    /**
     * @return mixed
     */
    function getTitle()
    {
        return $this->title;
    }

}