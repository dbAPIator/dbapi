<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 8/30/19
 * Time: 6:30 PM
 */

namespace Softaccel\Apiator\DBApi;

use Exception;

/**
 * Class DBParsers
 * @package Softaccel\Apiator\Admin\DBApi
 */
class DBWalk
{
   
    static function parse($db, $dbName) {
        switch($db->dbdriver) {
            case "mysqli":
                return self::parse_mysql($db, $dbName);
            case "postgresql":
                return self::parse_postgresql($db, $dbName);
            case "sqlsrv":
                return self::parse_sqlserver($db, $dbName);
            default:
                throw new \Exception("Unsupported database driver: " . $db->dbdriver);
        }
    }
    /**
     * Parses MySQL database structure to extract table and view definitions
     * @param \CI_DB_driver $db CodeIgniter database connection object
     * @param string $dbName Name of the database to parse
     * @return array Array containing database structure with tables, views, fields and permissions
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
                //"description"=>"",
                //"comment"=>$rec->TABLE_COMMENT,
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
                //"description"=>"",
                //"comment"=>"",
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
                //"description"=>"",
                "name"=>$item->COLUMN_NAME,
                //"comment"=>$item->COLUMN_COMMENT,
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

    /**
     * Parses PostgreSQL database structure to extract table and view definitions
     * @param \CI_DB_driver $db CodeIgniter database connection object
     * @param string $dbName Name of the database schema to parse
     * @return array Array containing database structure with tables, views, fields and permissions
     */
    static function parse_postgresql($db, $dbName)
    {
        $structure = [];
        $permissions = [];

        // Get tables and views
        $sql = "
            SELECT table_name, table_type 
            FROM information_schema.tables 
            WHERE table_schema = '$dbName' 
            AND table_type IN ('BASE TABLE', 'VIEW')";
        
        $res = $db->query($sql)->result();
        foreach ($res as $rec) {
            $isView = ($rec->table_type === 'VIEW');
            $permissions[$rec->table_name] = [
                "fields" => [],
                "relations" => [],
                "update" => !$isView,
                "delete" => !$isView,
                "insert" => true,
                "read" => true,
            ];
            
            $structure[$rec->table_name] = [
                "fields" => [],
                "relations" => [],
                "name" => $rec->table_name,
                "type" => $isView ? "view" : "table",
                "keyFld" => null
            ];
        }

        // Get columns
        $sql = "
            SELECT 
                c.table_name,
                c.column_name,
                c.data_type,
                c.character_maximum_length,
                c.is_nullable,
                c.column_default,
                tc.constraint_type,
                c.numeric_precision,
                c.numeric_scale
            FROM information_schema.columns c
            LEFT JOIN information_schema.key_column_usage kcu 
                ON c.table_name = kcu.table_name 
                AND c.column_name = kcu.column_name
                AND c.table_schema = kcu.table_schema
            LEFT JOIN information_schema.table_constraints tc
                ON kcu.constraint_name = tc.constraint_name
                AND kcu.table_schema = tc.table_schema
            WHERE c.table_schema = '$dbName'";

        $res = $db->query($sql)->result();
        foreach ($res as $item) {
            $isAutoIncrement = strpos($item->column_default, 'nextval') !== false;
            
            $permissions[$item->table_name]["fields"][$item->column_name] = [
                "insert" => !$isAutoIncrement,
                "update" => !$isAutoIncrement,
                "select" => true,
                "sortable" => true,
                "searchable" => true,
            ];

            $structure[$item->table_name]["fields"][$item->column_name] = [
                "name" => $item->column_name,
                "type" => self::postgresqlParseType($item->data_type, $item->character_maximum_length, 
                    $item->numeric_precision, $item->numeric_scale),
                "iskey" => $item->constraint_type === 'PRIMARY KEY' || $item->constraint_type === 'UNIQUE',
                "required" => !($item->is_nullable === 'YES' || $isAutoIncrement || $item->column_default),
                "default" => $item->column_default,
            ];

            if ($item->constraint_type === 'PRIMARY KEY') {
                $structure[$item->table_name]["keyFld"] = $item->column_name;
            } elseif ($item->constraint_type === 'UNIQUE' && !$structure[$item->table_name]["keyFld"]) {
                $structure[$item->table_name]["keyFld"] = $item->column_name;
            }
        }

        // Get foreign keys
        $sql = "
            SELECT
                tc.table_name,
                kcu.column_name,
                ccu.table_name AS referenced_table_name,
                ccu.column_name AS referenced_column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = '$dbName'";

        $fKeys = $db->query($sql)->result();
        foreach ($fKeys as $fk) {
            $srcTable = $fk->table_name;
            $srcFld = $fk->column_name;
            $tgtTable = $fk->referenced_table_name;
            $tgtFld = $fk->referenced_column_name;

            $structure[$srcTable]["fields"][$srcFld]["foreignKey"] = [
                "table" => $tgtTable,
                "field" => $tgtFld
            ];

            $structure[$srcTable]["relations"][$srcFld] = [
                "table" => $tgtTable,
                "field" => $tgtFld,
                "type" => "outbound",
                "fkfield" => $srcFld
            ];

            $permissions[$srcTable]["relations"][$srcFld] = [
                "insert" => true,
                "update" => true,
                "select" => true,
                "searchable" => true,
            ];

            $structure[$tgtTable]["fields"][$tgtFld]["referencedBy"][] = [
                "table" => $srcTable,
                "field" => $srcFld,
            ];
        }

        // Process relations (similar to MySQL version)
        foreach ($structure as $tableName => $table) {
            foreach ($table["fields"] as $fldName => $fldSpec) {
                if (array_key_exists("foreignKey", $fldSpec)) {
                    $relName = $tableName;
                    if (isset($structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName])) {
                        $tmp = $structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName];
                        $structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName."_".$tmp["field"]] = $tmp;
                        $tmpFld = $tmp["field"];

                        $tmp = $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$tableName];
                        $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$tableName."_".$tmpFld] = $tmp;

                        $relName = $tableName."_".$fldName;
                    }

                    $structure[$fldSpec["foreignKey"]["table"]]["relations"][$relName] = [
                        "table" => $tableName,
                        "field" => $fldName,
                        "type" => "inbound"
                    ];
                    $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$relName] = [
                        "insert" => true,
                        "update" => true,
                        "select" => true,
                        "searchable" => true,
                    ];
                }
            }
        }

        return ["structure" => $structure, "permissions" => $permissions];
    }

    /**
     * Parse PostgreSQL data type
     * @param string $type PostgreSQL data type
     * @param int|null $maxLength Maximum length for character types
     * @param int|null $precision Numeric precision
     * @param int|null $scale Numeric scale
     * @return array
     */
    static function postgresqlParseType($type, $maxLength = null, $precision = null, $scale = null) {
        $result = ["proto" => strtolower($type)];
        
        if ($maxLength) {
            $result["length"] = $maxLength;
        }
        
        if ($precision) {
            $result["precision"] = $precision;
            if ($scale) {
                $result["scale"] = $scale;
            }
        }

        return $result;
    }

    /**
     * Parses SQL Server database structure to extract table and view definitions
     * @param \CI_DB_driver $db CodeIgniter database connection object
     * @param string $dbName Name of the database to parse
     * @return array Array containing database structure with tables, views, fields and permissions
     */
    static function parse_sqlserver($db, $dbName)
    {
        $structure = [];
        $permissions = [];

        // Get tables and views
        $sql = "
            SELECT 
                t.name AS table_name,
                t.type AS table_type
            FROM sys.objects t
            WHERE t.type IN ('U', 'V')
            AND t.is_ms_shipped = 0";
        
        $res = $db->query($sql)->result();
        foreach ($res as $rec) {
            $isView = ($rec->table_type === 'V');
            $permissions[$rec->table_name] = [
                "fields" => [],
                "relations" => [],
                "update" => !$isView,
                "delete" => !$isView,
                "insert" => true,
                "read" => true,
            ];
            
            $structure[$rec->table_name] = [
                "fields" => [],
                "relations" => [],
                "name" => $rec->table_name,
                "type" => $isView ? "view" : "table",
                "keyFld" => null
            ];
        }

        // Get columns and their properties
        $sql = "
            SELECT 
                t.name AS table_name,
                c.name AS column_name,
                tp.name AS data_type,
                c.max_length,
                c.precision,
                c.scale,
                c.is_nullable,
                c.is_identity,
                object_definition(c.default_object_id) as column_default,
                CASE 
                    WHEN pk.column_id IS NOT NULL THEN 'PRIMARY KEY'
                    WHEN uk.column_id IS NOT NULL THEN 'UNIQUE'
                    ELSE NULL
                END as constraint_type
            FROM sys.objects t
            INNER JOIN sys.columns c ON t.object_id = c.object_id
            INNER JOIN sys.types tp ON c.user_type_id = tp.user_type_id
            LEFT JOIN (
                SELECT ic.object_id, ic.column_id
                FROM sys.indexes i
                JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                WHERE i.is_primary_key = 1
            ) pk ON t.object_id = pk.object_id AND c.column_id = pk.column_id
            LEFT JOIN (
                SELECT ic.object_id, ic.column_id
                FROM sys.indexes i
                JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                WHERE i.is_unique = 1 AND i.is_primary_key = 0
            ) uk ON t.object_id = uk.object_id AND c.column_id = uk.column_id
            WHERE t.type IN ('U', 'V')
            AND t.is_ms_shipped = 0";

        $res = $db->query($sql)->result();
        foreach ($res as $item) {
            $permissions[$item->table_name]["fields"][$item->column_name] = [
                "insert" => !$item->is_identity,
                "update" => !$item->is_identity,
                "select" => true,
                "sortable" => true,
                "searchable" => true,
            ];

            $structure[$item->table_name]["fields"][$item->column_name] = [
                "name" => $item->column_name,
                "type" => self::sqlserverParseType($item->data_type, $item->max_length, 
                    $item->precision, $item->scale),
                "iskey" => $item->constraint_type === 'PRIMARY KEY' || $item->constraint_type === 'UNIQUE',
                "required" => !($item->is_nullable || $item->is_identity || $item->column_default),
                "default" => $item->column_default,
            ];

            if ($item->constraint_type === 'PRIMARY KEY') {
                $structure[$item->table_name]["keyFld"] = $item->column_name;
            } elseif ($item->constraint_type === 'UNIQUE' && !$structure[$item->table_name]["keyFld"]) {
                $structure[$item->table_name]["keyFld"] = $item->column_name;
            }
        }

        // Get foreign keys
        $sql = "
            SELECT 
                fk.name AS FK_NAME,
                tp.name AS table_name,
                cp.name AS column_name,
                tr.name AS referenced_table_name,
                cr.name AS referenced_column_name
            FROM sys.foreign_keys fk
            INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
            INNER JOIN sys.tables tp ON fk.parent_object_id = tp.object_id
            INNER JOIN sys.tables tr ON fk.referenced_object_id = tr.object_id
            INNER JOIN sys.columns cp ON fkc.parent_object_id = cp.object_id AND fkc.parent_column_id = cp.column_id
            INNER JOIN sys.columns cr ON fkc.referenced_object_id = cr.object_id AND fkc.referenced_column_id = cr.column_id";

        $fKeys = $db->query($sql)->result();
        foreach ($fKeys as $fk) {
            $srcTable = $fk->table_name;
            $srcFld = $fk->column_name;
            $tgtTable = $fk->referenced_table_name;
            $tgtFld = $fk->referenced_column_name;

            $structure[$srcTable]["fields"][$srcFld]["foreignKey"] = [
                "table" => $tgtTable,
                "field" => $tgtFld
            ];

            $structure[$srcTable]["relations"][$srcFld] = [
                "table" => $tgtTable,
                "field" => $tgtFld,
                "type" => "outbound",
                "fkfield" => $srcFld
            ];

            $permissions[$srcTable]["relations"][$srcFld] = [
                "insert" => true,
                "update" => true,
                "select" => true,
                "searchable" => true,
            ];

            $structure[$tgtTable]["fields"][$tgtFld]["referencedBy"][] = [
                "table" => $srcTable,
                "field" => $srcFld,
            ];
        }

        // Process relations (similar to MySQL version)
        foreach ($structure as $tableName => $table) {
            foreach ($table["fields"] as $fldName => $fldSpec) {
                if (array_key_exists("foreignKey", $fldSpec)) {
                    $relName = $tableName;
                    if (isset($structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName])) {
                        $tmp = $structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName];
                        $structure[$fldSpec["foreignKey"]["table"]]["relations"][$tableName."_".$tmp["field"]] = $tmp;
                        $tmpFld = $tmp["field"];

                        $tmp = $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$tableName];
                        $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$tableName."_".$tmpFld] = $tmp;

                        $relName = $tableName."_".$fldName;
                    }

                    $structure[$fldSpec["foreignKey"]["table"]]["relations"][$relName] = [
                        "table" => $tableName,
                        "field" => $fldName,
                        "type" => "inbound"
                    ];
                    $permissions[$fldSpec["foreignKey"]["table"]]["relations"][$relName] = [
                        "insert" => true,
                        "update" => true,
                        "select" => true,
                        "searchable" => true,
                    ];
                }
            }
        }

        return ["structure" => $structure, "permissions" => $permissions];
    }

    /**
     * Parse SQL Server data type
     * @param string $type SQL Server data type
     * @param int|null $maxLength Maximum length for character types
     * @param int|null $precision Numeric precision
     * @param int|null $scale Numeric scale
     * @return array
     */
    static function sqlserverParseType($type, $maxLength = null, $precision = null, $scale = null) {
        $result = ["proto" => strtolower($type)];
        
        // Handle special case for nvarchar/varchar max
        if ($maxLength === -1) {
            $result["length"] = "max";
        } elseif ($maxLength !== null) {
            // For nchar/nvarchar, length is stored in bytes, so divide by 2
            if (stripos($type, 'n') === 0) {
                $maxLength = $maxLength / 2;
            }
            $result["length"] = $maxLength;
        }
        
        if ($precision !== null) {
            $result["precision"] = $precision;
            if ($scale !== null) {
                $result["scale"] = $scale;
            }
        }

        return $result;
    }
}