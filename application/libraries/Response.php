<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Class Response
 * @property-read int $code
 * @property-read bool $success
 * @property-read mixed $data
 */
class Response {
	private $success;
	private $code;
	private $data;
	
	private function __construct($success,$code,$data) {
		$this->data = $data;
		$this->code = $code;
		$this->success = $success;
	}

    /**
     * @param $name
     * @return mixed
     * @throws Exception
     */
	function __get($name) {
		if(property_exists($this,$name))
			return $this->$name;
		throw new Exception("Property '$name' not found",500);
	}

    /**
     * Creates a Response object
     * @param boolean $success
     * @param integer $code
     * @param mixed $data
     * @return Response
     */
	static function make($success,$code=null,$data=null) {
		return new Response($success,$code,$data);
	}
}
