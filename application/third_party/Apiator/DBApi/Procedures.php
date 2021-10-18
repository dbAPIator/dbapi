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
     * @throws \Exception
     */
    function call($procedureName, $parameters) {

        if(!is_array($parameters)) {
            throw new \Exception("Invalid input",400);
        }
        $execSql = [];

        $args =  [];
        foreach ($parameters as $para) {
            $args[] = "@".$para->name;
            if($para->dir=="in") {
                $execSql[] = "SET @$para->name='$para->value';";
            }
            else {
                $result[] = "@$para->name $para->name";
            }
        }
        $execSql[] = "CALL $procedureName(".implode(",",$args).");";
        if(count($result)) {
            $execSql[] = "SELECT ".implode(", ",$result).";";
        }
        foreach ($execSql as $sql) {
            $res = $this->db->query($sql);
        }

        if(count($result)) {
            return $res->row();
        }

        return null;



//        echo($sql);

//        $res = $this->db->query($sql);
//        if($res) {
//            print_r($res);
//        }
//        else {
//            print_r($this->db->error());
//        }
    }
}