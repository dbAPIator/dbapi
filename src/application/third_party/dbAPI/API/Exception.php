<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 9/23/19
 * Time: 10:52 AM
 */

namespace dbAPI\API;

/**
 * Class Exception
 * @package dbAPI
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

    protected $httpCode;

    /**
     * @param $data
     */
    public static function  from_error_catalog($data)
    {
        $e = new self($data["title"],$data["code"]);
        $e->title = $data["title"];
        $e->description = $data["description"];
        $e->httpCode = $data["httpCode"] ?? 500;
        return $e;
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