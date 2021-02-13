<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 8/30/19
 * Time: 6:30 PM
 */

namespace Softaccel\Apiator\DBApi;


/**
 * Class DBParsers
 * @package Softaccel\Apiator\Admin\DBApi
 */
class DBWalk
{
    /**
     * @param \CI_DB_driver $db
     * @param $dbName
     * @return array
     */
    static function parse_mysql($db, $dbName)
    {
        $structure = [];
        $permissions  = [];

        // read DB structure
        $sql = "SELECT * FROM `information_schema`.`TABLES` where TABLE_SCHEMA='$dbName'";
        $res = $db->query($sql)->result();
        foreach($res as $rec) {
            $permissions[$rec->TABLE_NAME] = [
                "fields"=>[],
                "update"=>true,
                "delete"=>true,
                "insert"=>true,
                "read"=>true,
            ];
            $structure[$rec->TABLE_NAME] = [
                "fields"=>[],
                "name"=>$rec->TABLE_NAME,
                "description"=>"",
                "comment"=>$rec->TABLE_COMMENT,
                "type"=>"table",
                "keyFld"=>null
            ];

        }

        // get views list
        $sql = "SELECT * FROM `information_schema`.`VIEWS` where TABLE_SCHEMA='$dbName'";
        $res = $db->query($sql)->result();
        foreach($res as $rec) {
            $permissions[$rec->TABLE_NAME] = [
                "fields"=>[],
                "relations"=>[],
                "update"=>false,
                "delete"=>false,
                "insert"=>true,
                "read"=>true,
            ];
            $structure[$rec->TABLE_NAME] = [
                "fields"=>[],
                "relations"=>[],
                "description"=>"",
                "comment"=>"",
                "type"=>"view",
                "keyFld"=>null
            ];
        }

        // get fields
        $sql = "SELECT * FROM `information_schema`.`COLUMNS` WHERE TABLE_SCHEMA='$dbName'";
        $res = $db->query($sql)->result();
        foreach($res as $item) {
            $permissions[$item->TABLE_NAME]["fields"][$item->COLUMN_NAME] = [
                "insert" => $item->EXTRA=="auto_increment"?false:true,
                "update" => $item->EXTRA=="auto_increment"?false:true,
                "select" => true,
                "sortable"  => true,
                "searchable"    => true,
            ];
            $structure[$item->TABLE_NAME]["fields"][$item->COLUMN_NAME] = [
                "description"=>"",
                "name"=>$item->COLUMN_NAME,
                "comment"=>$item->COLUMN_COMMENT,
                "type" => self::mysqlParseType($item->COLUMN_TYPE),
                "iskey" => in_array($item->COLUMN_KEY, ["PRI","UNI"]),
                "required" => !($item->IS_NULLABLE=="YES" ||  $item->EXTRA=="auto_increment" || $item->COLUMN_DEFAULT),
                "default" => $item->COLUMN_DEFAULT,
            ];

            if($item->COLUMN_KEY==="PRI")
                $structure[$item->TABLE_NAME]["keyFld"] = $item->COLUMN_NAME;
            if($item->COLUMN_KEY==="UNI" && !$structure[$item->TABLE_NAME]["keyFld"])
                $structure[$item->TABLE_NAME]["keyFld"] = $item->COLUMN_NAME;
        }

        // fetch foreign keys
        $sql = "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                            FROM information_schema.KEY_COLUMN_USAGE
                            WHERE TABLE_SCHEMA='$dbName' and REFERENCED_TABLE_SCHEMA='$dbName';";
        $fKeys = $db->query($sql)->result();
        foreach ($fKeys as $fk) {
            $srcTable = $fk->TABLE_NAME;
            $srcFld = $fk->COLUMN_NAME;
            $tgtTable = $fk->REFERENCED_TABLE_NAME;
            $tgtFld = $fk->REFERENCED_COLUMN_NAME;
            $structure[$srcTable]["fields"][$srcFld]["foreignKey"] = [
                "table" => $tgtTable,
                "field" => $tgtFld
            ];

            $structure[$srcTable]["relations"][$srcFld] = [
                "table" => $tgtTable,
                "field" => $tgtFld,
                "type" => "outbound",
                "fkfield"=>$srcFld
            ];

            $permissions[$srcTable]["relations"][$srcFld] = [
                "insert" => true,
                "update" => true,
                "select" => true,
                "searchable"    => true,
            ];

            $structure[$tgtTable]["fields"][$tgtFld]["referencedBy"][] =  [
                "table" => $srcTable,
                "field" => $srcFld,
            ];
        }


        // save relations
        foreach($structure as $tableName=>$table) {
            foreach ($table["fields"] as $fldName=>$fldSpec){
                if(array_key_exists("foreignKey",$fldSpec)) {
                    $relName = $tableName;
                    if(isset($structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName])) {
                        $tmp = $structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName];
                        $structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName."_".$tmp["field"]] = $tmp;
                        $tmpFld = $tmp["field"];

                        $tmp = $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$tableName];
                        $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$tableName."_".$tmpFld] = $tmp;

                        $relName = $tableName."_".$fldName;
                    }

                    $structure[$fldSpec["foreignKey"]["table"]]["relations"][$relName] = [
                        "table"=>$tableName,
                        "field"=>$fldName,
                        "type" => "inbound"
                    ];
                    $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$relName] = [
                        "insert" => true,
                        "update" => true,
                        "select" => true,
                        "searchable"    => true,
                    ];
                }
            }
        }

        return ["structure"=>$structure,"permissions"=>$permissions];
    }

    /**
     * parse type of $str
     * @param $str
     * @return array
     */
    static function mysqlParseType($str) {
        preg_match("/([a-z]+)(\(([a-z0-9\_\*\, \']+)\))?/i",$str,$m);

        //echo $str."\n";
        //print_r($m);
        if($m[1]=="set" || $m[1]=="enum") {
            $quotes = explode(",",$m[3]);
            return array("proto"=>$m[1],
                "vals"=>array_map(
                    function($str) {
                        return str_replace(array('"', "'"), '', $str);
                    },$quotes));
        }


        return count($m)>2?array("proto"=>$m[1],"length"=>$m[3]):array("proto"=>$m[1]);
    }
}