<?php

namespace dbAPI\Config;

require_once __DIR__ . '/../../../helpers/config_util_helper.php';

/**
 * Introspects a MySQL/MariaDB schema via information_schema.
 */
class DBWalk
{
    /**
     * @param \CI_DB_driver $db
     * @param string $dbName Database name (TABLE_SCHEMA)
     * @return array{structure: array, permissions: array, warnings: list}
     */
    public static function parse($db, $dbName)
    {
        $driver = $db->dbdriver ?? '';
        if (!in_array($driver, ['mysqli', 'mysql'], true)) {
            throw new \Exception(
                'Unsupported database driver: ' . $driver . '. Only MySQL (mysqli) is supported.'
            );
        }

        return self::parse_mysql($db, $dbName);
    }

    /**
     * @param \CI_DB_driver $db
     * @param string $dbName
     * @return array{structure: array, permissions: array, warnings: list}
     */
    private static function parse_mysql($db, $dbName)
    {
        $schema = self::escLiteral($db, $dbName);
        $structure = [];
        $permissions = [];

        $sql = "SELECT TABLE_NAME FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = {$schema} AND TABLE_TYPE = 'BASE TABLE'";
        foreach ($db->query($sql)->result() as $rec) {
            $permissions[$rec->TABLE_NAME] = self::defaultTablePermissions(true);
            $structure[$rec->TABLE_NAME] = self::emptyEntity('table');
        }

        $sql = "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = {$schema}";
        foreach ($db->query($sql)->result() as $rec) {
            $permissions[$rec->TABLE_NAME] = self::defaultTablePermissions(false);
            $structure[$rec->TABLE_NAME] = self::emptyEntity('view');
        }

        $sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY, IS_NULLABLE, EXTRA,
                       COLUMN_DEFAULT, ORDINAL_POSITION
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = {$schema}
                ORDER BY TABLE_NAME, ORDINAL_POSITION";
        foreach ($db->query($sql)->result() as $item) {
            if (!isset($structure[$item->TABLE_NAME])) {
                continue;
            }

            $autoInc = stripos($item->EXTRA, 'auto_increment') !== false;
            $hasDefault = $item->COLUMN_DEFAULT !== null;

            $permissions[$item->TABLE_NAME]['fields'][$item->COLUMN_NAME] = [
                'insert' => !$autoInc,
                'update' => !$autoInc,
                'select' => true,
                'sortable' => true,
                'searchable' => true,
            ];

            $structure[$item->TABLE_NAME]['fields'][$item->COLUMN_NAME] = [
                'type' => self::mysqlParseType($item->COLUMN_TYPE),
                'iskey' => in_array($item->COLUMN_KEY, ['PRI', 'UNI'], true),
                'required' => !(
                    $item->IS_NULLABLE === 'YES'
                    || $autoInc
                    || $hasDefault
                ),
                'default' => $item->COLUMN_DEFAULT,
            ];

            if ($item->COLUMN_KEY === 'PRI' && $structure[$item->TABLE_NAME]['keyFld'] === null) {
                $structure[$item->TABLE_NAME]['keyFld'] = $item->COLUMN_NAME;
            } elseif ($item->COLUMN_KEY === 'UNI' && $structure[$item->TABLE_NAME]['keyFld'] === null) {
                $structure[$item->TABLE_NAME]['keyFld'] = $item->COLUMN_NAME;
            }
        }

        $sql = "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = {$schema}
                  AND REFERENCED_TABLE_SCHEMA = {$schema}
                  AND REFERENCED_TABLE_NAME IS NOT NULL";
        foreach ($db->query($sql)->result() as $fk) {
            if (!isset($structure[$fk->TABLE_NAME])) {
                continue;
            }
            $srcTable = $fk->TABLE_NAME;
            $srcFld = $fk->COLUMN_NAME;
            $structure[$srcTable]['relations'][$srcFld] = [
                'table' => $fk->REFERENCED_TABLE_NAME,
                'field' => $fk->REFERENCED_COLUMN_NAME,
                'type' => 'outbound',
            ];
            $permissions[$srcTable]['relations'][$srcFld] = [
                'insert' => true,
                'update' => true,
                'select' => true,
                'searchable' => true,
            ];
        }

        $warnings = structure_apply_inbound_relations(
            $structure,
            structure_collect_inbound_edges($structure),
            $permissions
        );

        return ['structure' => $structure, 'permissions' => $permissions, 'warnings' => $warnings];
    }

    /**
     * @param \CI_DB_driver $db
     */
    private static function escLiteral($db, string $value): string
    {
        return $db->escape($value);
    }

    private static function emptyEntity(string $type): array
    {
        return [
            'fields' => [],
            'relations' => [],
            'type' => $type,
            'keyFld' => null,
        ];
    }

    private static function defaultTablePermissions(bool $isTable): array
    {
        return [
            'fields' => [],
            'relations' => [],
            'update' => $isTable,
            'delete' => $isTable,
            'insert' => $isTable,
            'read' => true,
        ];
    }

    /**
     * @param string $str COLUMN_TYPE from information_schema
     * @return array
     */
    public static function mysqlParseType($str)
    {
        $str = trim($str);
        if (!preg_match('/^([a-z]+)(?:\((.*)\))?(?:\s+unsigned)?$/i', $str, $m)) {
            return ['proto' => strtolower(preg_replace('/\s+/', '_', $str) ?: 'unknown')];
        }

        $proto = strtolower($m[1]);
        $args = isset($m[2]) ? $m[2] : null;

        if ($proto === 'enum' || $proto === 'set') {
            return [
                'proto' => $proto,
                'vals' => self::mysqlParseQuotedList($args ?? ''),
            ];
        }

        if ($args !== null && $args !== '') {
            return ['proto' => $proto, 'length' => $args];
        }

        return ['proto' => $proto];
    }

    /**
     * Parse single-quoted enum/set member list from COLUMN_TYPE.
     *
     * @return list<string>
     */
    private static function mysqlParseQuotedList(string $inner): array
    {
        $vals = [];
        $len = strlen($inner);
        $i = 0;
        while ($i < $len) {
            while ($i < $len && ($inner[$i] === ' ' || $inner[$i] === ',')) {
                $i++;
            }
            if ($i >= $len || $inner[$i] !== "'") {
                break;
            }
            $i++;
            $buf = '';
            while ($i < $len) {
                if ($inner[$i] === '\\' && $i + 1 < $len) {
                    $buf .= $inner[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($inner[$i] === "'") {
                    $i++;
                    break;
                }
                $buf .= $inner[$i];
                $i++;
            }
            $vals[] = $buf;
        }

        if (count($vals) > 0) {
            return $vals;
        }

        return array_values(array_filter(array_map('trim', explode(',', $inner)), 'strlen'));
    }
}
