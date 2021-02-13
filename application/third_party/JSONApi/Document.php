<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 4:25 PM
 */
namespace JSONApi;

/**
 * Class Document
 * Document Object model as of JSONAPI
 *
 * @package JSONApi
 */
class Document extends  json_ready
{
    /**
     * @var Document static variable to store the single Document instance
     */
    protected static $doc;
    private $baseUrl = "https://dbapi.apiator/api/5cbaed2eb9a51";

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var Meta
     */
    protected $meta;

    /**
     * @var string JSONAPI Version
     */
    protected $jsonapi="1.0";

    /**
     * @var Links
     */
    protected $links;

    /**
     * @var array
     */
    protected $includes;


    /**
     * singleton method to create a JSONAPI document
     * @param array $options JSONAPI options: (bool) nolinks, (string) baseUrl
     * @param array|null $data
     * @param Meta|null $meta
     * @param array|null $errors
     * @param Links|null $links
     * @return Document
     * @throws \Exception
     */
    static function create($options=[], $data=null, Meta $meta=null, array $errors=[], Links $links=null)
    {
        if(isset(self::$doc))
            return self::$doc;

        self::$doc = new self($options);
        if(isset($options["baseUrl"]))
            self::$doc->baseUrl = $options["baseUrl"];
        else
            throw new \Exception("Missing Base URL when initializing JSONAPI Document",500);

        self::$doc->setData($data);
        if($meta)
            self::$doc->setMeta($meta);
        if($errors)
            self::$doc->setErrors($errors);
        if($links) {
            self::$doc->setLinks($links);
        }

        return self::$doc;
    }

    /**
     * @param array $options
     * @param $errors
     * @return Document
     */
    static function error_doc($options,$errors)
    {
        if(!isset(self::$doc))
            self::$doc = new self($options);
        self::$doc->setErrors($errors);
        return self::$doc;
    }

    /**
     * @param array $options
     * @param \Exception $exception
     * @return Document
     */
    static function from_exception($options,\Exception $exception)
    {
        /**
         * @param $errors
         * @param \Exception $exception
         */
        function parseRecursive(&$errors,$exception) {
            $errors[] = Error::factory(
                    [
                    "title"=>$exception->getMessage(),
                    "code"=>$exception->getCode()
                ]);
            if($lnk=$exception->getPrevious())
                parseRecursive($errors,$lnk);
        }

        if(!isset(self::$doc))
            self::$doc = new self($options);
        $errors = [];
        parseRecursive($errors,$exception);
        self::$doc->setErrors($errors);

        return self::$doc;
    }


    private function __construct ($options)
    {
        $this->options = $options;
    }

    public function get_baseUrl()
    {
        return $this->baseUrl;
    }
    /**
     * @return mixed
     */
    public function getData ()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return Document
     * @throws \Exception
     */
    public function &setData ($data)
    {
        if(is_null($data)) {
            $this->data = null;
            return $this;
        }

        if(is_object($data)) {
            $this->data = Resource::factory($data);
        }
        elseif(is_array($data)) {
            $this->data = [];
            foreach ($data as $item) {
                $newRes = Resource::factory($item);
                if($newRes) {
                    $this->data[] = $newRes;
                }
            }
        }

        return $this;
    }


    /**
     * @param mixed $errors
     * @return Document
     */
    function &setErrors ($errors)
    {
        $this->errors = $errors;
        return $this;
    }


    /**
     * @param Meta|null $meta
     * @return Document
     */
    function &setMeta ($meta)
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * @param mixed $jsonapi
     * @return Document
     */
    function &setJsonapi ($jsonapi)
    {
        $this->jsonapi = $jsonapi;
        return $this;
    }


    /**
     * @param Resource $resource
     * @return Resource|null
     * @throws \Exception
     */
    function addInclude($resource)
    {
        if(!($resource instanceof Resource))
            throw new \Exception("Invalid parameter: not a Resource object");

        if(!isset($this->includes))
            $this->includes = [];

        $uid = $resource->getType()."_".$resource->getId();
        if(!isset($this->includes[$uid]))
            $this->includes[$uid] = $resource;

        return $this->includes[$uid];
    }


    /**
     * @param mixed $links
     * @return Document
     */
    function &setLinks ($links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * @return array
     */
    function json_data ()
    {

        if(property_exists($this,"errors") && empty($this->errors))
            unset($this->errors);
        if(property_exists($this,"meta") && empty($this->meta))
            unset($this->meta);
        if(property_exists($this,"includes") && empty($this->includes))
            unset($this->includes);

        $data = parent::json_data(); // TODO: Change the autogenerated stub
        if(!isset($data["errors"]) && !isset($data["data"]))
            return ["data"=>null];
        return $data;
    }

    /**
     * @param Error $error
     * @return Document
     */
    function addError(Error $error)
    {
        if(!is_array($this->errors))
            $this->errors = [];
        $this->errors[] = $error;
        return $this;
    }

    /**
     * @param $options
     * @param $title
     * @param $code
     * @return Document
     * @throws \Exception
     */
    static function not_found($options,$title,$code)
    {
        return self::create($options)->addError(Error::factory(
            [
                "title" => $title,
                "code" => $code
            ]
        ));
    }
}