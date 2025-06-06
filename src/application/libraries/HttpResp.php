<?php
/**
 * Creates and sends a HTTP response. Singleton implementation
 * User: vsergiu
 * Date: 10/3/18
 * Time: 11:59 AM
 */
class HttpResp{
    private static $self=null;

    private $header = [
        "Content-Type"=>"text/html",
        "Access-Control-Allow-Credentials"=>"true",
        // "Pragma"=>"no-cache"
    ];

    private $responseCode = 200;
    private $body;

    private function __construct ()
    {

    }

    /**
     * set header
     * @param string $name header name or complete header
     * @param string|int $value header value or null when $name contains complete header
     * @return $this
     */
    public function &header($name, $value=null)
    {
        if(is_null($value) && strpos(":",$name)!=-1) {
            list($name,$value) = explode(":",$name);
            $value = trim($value);
        }
        $this->header[$name] = $value;
        return $this;
    }

    /**
     * set body
     * @param string $value
     * @return HttpResp $this
     */
    public function &body($value)
    {
        $this->body = $value;
        return $this;
    }

    /**
     * set response code
     * @param string|int $statusCode HTTP response code
     * @return HttpResp $this
     */
    public function &response_code($statusCode)
    {
        $this->responseCode = $statusCode;
        return $this;
    }

    /**
     * output the response
     * @return bool
     */
    public function output()
    {
        http_response_code($this->responseCode);
        foreach ($this->header as $header=>$value)
        {
            if(!is_null($value)) {
                @header("$header: $value");
            }
        }
        echo $this->body;
        return true;
    }

    /**
     * set no caching
     * @return HttpResp
     */
    public function &no_cache()
    {
        return $this
            ->header("Cache-Control","no-cache, no-store, must-revalidate")
            ->header("Expires","0");
    }

    /**
     * set allowed headers
     * @param $headers
     * @return mixed
     */
    public function &allow_headers($headers)
    {
        if($headers)
            return $this->header("Access-Control-Allow-Headers",$headers);
        return $this;
    }

    /**
     * shortcut for allowing all headers
     * @param $headers
     * @return mixed
     */
    public function &allow_all_headers($headers)
    {
        if($headers)
            return $this->allow_headers($headers);
        return $this;
    }

    /**
     * set allowed origins
     * @param $origin
     * @return HttpResp
     */
    public function &allow_origin($origin)
    {
        if($origin)
            return $this->header("Access-Control-Allow-Origin",$origin);
        return $this;
    }

    /**
     * set content type
     * @param $type
     * @return HttpResp
     */
    public function &content_type($type)
    {
        if($type)
            return $this->header("Content-Type",$type);
        return $this;
    }

    /**
     * shorthand for allowing all origins
     * @return HttpResp
     */
    public function &allow_all_origin()
    {
        return $this->allow_origin("*");
    }

    /**
     * set allowed methods
     * @param $methods
     * @return HttpResp
     */
    public function &allow_methods($methods)
    {
        return $this->header("Access-Control-Allow-Methods",$methods);
    }

    /**
     * shorthand for allowing all methods
     * @return HttpResp
     */
    public function &allow_all_methods()
    {
        return $this->allow_methods("*");
    }

    /**
     * singleton method for getting the response instance
     * @return HttpResp
     */
    static function init()
    {
        if(isset(HttpResp::$self))
            return HttpResp::$self;

        return new HttpResp();
    }

    /**
     * singleton method for getting the response instance
     * @return HttpResp
     */
    static function instance()
    {
        if(isset(HttpResp::$self))
            return HttpResp::$self;

        return new HttpResp();
    }



    /**
     * shorthand method for making a quick response
     * @param string|int $statusCode HTTP response code
     * @param string $contentType HTTP content type
     * @param string $body HTTP response body
     */
    static function quick($statusCode, $contentType=null, $body=null,$doExit=true)
    {
        $resp = HttpResp::init();
        if($contentType)
            $resp->content_type($contentType);
        $resp
            ->response_code($statusCode)
            ->body($body)
            ->output();
        if($doExit) exit();
    }


    /**
     * @param $statusCode
     * @param $contentType
     * @param null $body
     * @param null $headers
     */
    static private function ctype_out($statusCode, $contentType, $body=null, $headers=[],$doExit=true)
    {
        if(is_null($headers))
            HttpResp::quick($statusCode, $contentType,  $body);

        $resp = HttpResp::init();
        if(is_array($headers))
            foreach ($headers as $header=>$value)
                $resp->header($header,$value);
        else
            $resp->header($headers);
        $resp->response_code($statusCode)->content_type($contentType)->body($body)->output();
        if($doExit) exit();
    }

    /**
     * returns a 401 Not authorized and exists execution
     * @param null $body
     */
    static function not_authorized($body=null,$doExit=true)
    {
        HttpResp::out_autodetect(401,$body);
        if($doExit) exit();
    }

    /**
     * returns a 400 Invalid request and exists execution
     * @param null $body
     */
    static function invalid_request($body=null,$doExit=true)
    {
        HttpResp::out_autodetect(400,$body);
        if($doExit) exit();
    }


    /**
     * returns a 401 Not authorized and exists the script
     * @param string $body
     */
    static function not_found($body=null,$doExit=true)
    {
        HttpResp::out_autodetect(404,$body);
        if($doExit) exit();

    }

    /**
     * returns a 401 Not authorized
     * @param string $body
     */
    static function bad_request($body=null,$doExit=true)
    {
        HttpResp::out_autodetect(400,$body);
        if($doExit) exit();
    }

    /**
     *
     */
    static function method_not_allowed($body=null,$doExit=true)
    {
        HttpResp::out_autodetect(405,$body);
        if($doExit) exit();
    }

    /**
     * returns a 500 Server error
     * @param string $body
     */
    static function server_error($body=null,$doExit=true)
    {
        HttpResp::out_autodetect(500,$body);
        if($doExit) exit();
    }

    static function out_autodetect($code,$body=null) {
        $http = HttpResp::init();
        if(is_array($body) || is_object($body)){
            $body = json_encode($body);
            $http->content_type("application/json");
        }

        $http->response_code($code)->body($body)->output();
    }

    /**
     * returns a 401 Not authorized
     * @param string $body
     */
    static function service_unavailable($body=null,$doExit=true)
    {
        if(is_array($body) || is_object($body)) {
            $body = json_encode($body);
            HttpResp::init()->content_type("application/json")->response_code(503)->body($body)->output();
            if($doExit) exit();
        }

        HttpResp::init()->response_code(503)->body($body)->output();
        if($doExit) exit();
    }

    /**
     * helper method for creating a response for json cType. JSON encodes the body when not encoded already.
     * Ends the script after output
     * @param string|int $statusCode HTTP response code
     * @param string|array|object $body
     * @param array|string $headers assoc array of key->value or string containing header string
     * @param int $jsonOptions
     */
    static function json_out($statusCode, $body=null, $headers=[],$jsonOptions=0)
    {
        if(is_array($body) || is_object($body))
            $body = json_encode($body,$jsonOptions);
        
        HttpResp::ctype_out($statusCode,"application/json",$body,$headers);
    }

    /**
     * @param $statusCode
     * @param \JSONApi\Document $doc
     */
    static function jsonapi_out($statusCode, $doc)
    {
        $body = json_encode($doc->json_data(),JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        HttpResp::ctype_out($statusCode,"application/json",$body);
    }

    static function error_out_json($message, $statusCode,$doExit=true)
    {
        HttpResp::json_out($statusCode,[
            "errors"=>[
                ["message"=>$message]
            ]
        ]);
        if($doExit) exit();
    }

    /**
     * helper to output an exception as a JSONAPI error
     * @param Exception $e
     */
    static function exception_out($e,$doExit=true)
    {
        HttpResp::json_out(500,["errors"=>[["message"=>$e->getMessage()]]]);
        if($doExit) exit();
    }

    /**
     * shorthand method for generating & sending an XML response
     * @param string|int $statusCode HTTP response code
     * @param string|array|object $body
     * @param string|array|null $headers
     * @return bool
     */
    static function xml_out($statusCode, $body=null, $headers=null)
    {
        // TODO: implement XML output
        return true;
    }

    /**
     * shorthand method for generating & sending a test response
     * @param string|int $statusCode HTTP response code
     * @param string $body
     */
    static function text_out($statusCode, $body=null)
    {
        HttpResp::quick($statusCode,"text/plain",$body);
    }

    /**
     * shorthand method for generating & sending a test response
     * @param string|int $statusCode HTTP response code
     * @param string $body
     */
    static function csv_out($statusCode, $body=null)
    {
        HttpResp::quick($statusCode,"text/csv",$body);
    }





    /**
     * shorthand method for generating & sending a html response
     * @param string|int $statusCode HTTP response code
     * @param string $body
     */
    static function html_out($statusCode, $body=null)
    {
        HttpResp::quick($statusCode,"text/html",$body);
    }

    /**
     * @param $statusCode
     */
    static function no_content($statusCode=204,$doExit=true)
    {
        HttpResp::quick($statusCode);
        if($doExit) exit();
    }

    /**
     * shorthand method for performing a redirect
     * @param string $location
     * @param string|int $statusCode HTTP response code; defaults to 301 Moved permanently
     */
    static function redirect($location, $statusCode=301,$doExit=true)
    {
        HttpResp::init()
            ->response_code($statusCode)
            ->header("Location",$location)
            ->output();
        if($doExit) exit();
    }
}

