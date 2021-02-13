<?php
namespace Apiator\DBApi;

require_once(__DIR__."/../../../libraries/Errors.php");
require_once(__DIR__.'/../../../libraries/RecordSet.php');

$req = require_once (APPPATH."/third_party/JSONApi/Autoloader.php");

use http\Exception;
use JSONApi\Autoloader;
Autoloader::register();


class Procedures
{
    /**
     * @var \CI_DB_query_builder
     */
    private $db;

    /**
     * @var Datamodel
     */
    private $dm;

    /**
     * Records constructor.
     * @param \CI_DB_query_builder $dbDriver
     * @param Datamodel $dataModel
     */
    private function __construct($dbDriver,$dataModel) {
        $this->dm = $dataModel;
        $this->db = $dbDriver;
    }

    /**
     * factory constructor
     * @param $dbDriver
     * @param $dataModel
     * @return Procedures
     */
    static function init($dbDriver,$dataModel) {
        return new self($dbDriver,$dataModel);
    }

    /**
     * @param $procedureName
     * @param $parameters
     */
    function call($procedureName, $parameters) {
        $args =  "";
        if(!is_null($parameters))
            $args = "'".implode("','",$parameters)."'";
        $this->db->db_debug = false;
        $res = $this->db->query("CALL $procedureName($args)");
        if($res) {
            print_r($res);
        }
        else {
            print_r($this->db->error());
        }
    }
}