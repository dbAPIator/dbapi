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
    protected $title;
    /**
     * @var Links
     */
    protected $links;
    /**
     * @var string
     */
    protected $status;
    /**
     * @var string
     */
    protected $code;
    /**
     * @var string
     */
    protected $detail;
    /**
     * @var Meta
     */
    protected $meta;

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
            "title"=>$data["title"]
        ]);
    }

    /**
     * @param \Exception $e
     * @return Error
     */
    static function from_exception($e)
    {
        return new self(["title"=>$e->getMessage(),"code"=>$e->getCode()]);
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
    public function getTitle ()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     * @return Error
     */
    public function &setTitle ($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLinks ()
    {
        return $this->links;
    }

    /**
     * @param mixed $links
     * @return Error
     */
    public function &setLinks (Links $links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatus ()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     * @return Error
     */
    public function &setStatus ($status)
    {
        $this->status = $status;
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

    /**
     * @return mixed
     */
    public function getDetail ()
    {
        return $this->detail;
    }

    /**
     * @param mixed $detail
     * @return Error
     */
    public function &setDetail ($detail)
    {
        $this->detail = $detail;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMeta ()
    {
        return $this->meta;
    }

    /**
     * @param mixed $meta
     * @return Error
     */
    public function &setMeta (Meta $meta)
    {
        $this->meta = $meta;
        return $this;
    }

}