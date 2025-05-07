<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 4:46 PM
 */

namespace JSONApi;


class Error extends json_ready
{
    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $code;


    /**
     * @param $data
     * @return Error
     */
    static function factory($data)
    {
        if(is_array($data) || is_object($data))
            return new self($data);
        return null;
    }

    /**
     * @param array $data
     * @return Error
     */
    static function from_error_catalog($data)
    {
        return new self([
            "code"=>$data["code"],
            "message"=>$data["message"]
        ]);
    }

    /**
     * @param \Exception $e
     * @return Error
     */
    static function from_exception($e)
    {
        return new self(["message"=>$e->getMessage(),"code"=>$e->getCode()]);
    }

    private function __construct ($data)
    {
        foreach ($data as $key=>$val) {
            if(property_exists(__CLASS__,$key))
                $this->$key = $val;
        }
    }

    /**
     * @return mixed
     */
    public function getMessage ()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     * @return Error
     */
    public function &setMessage ($message)
    {
        $this->message = $message;
        return $this;
    }



    /**
     * @return mixed
     */
    public function getCode ()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     * @return Error
     */
    public function &setCode ($code)
    {
        $this->code = $code;
        return $this;
    }


}