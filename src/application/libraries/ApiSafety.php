<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'third_party/dbAPI/Autoloader.php';
\dbAPI\Autoloader::register();

use dbAPI\API\FilterParser;

/**
 * Data-plane guardrails: paging, filters, bulk writes, query timeouts.
 */
class ApiSafety
{
    /** @var array<string,int> */
    private static array $limits = [];

    /** @var array<string,int> */
    private static array $defaults = [
        'default_page_size' => 100,
        'max_page_size' => 1000,
        'max_filter_expression_length' => 4096,
        'max_filter_ast_depth' => 20,
        'max_filter_ast_nodes' => 100,
        'bulk_insert_limit' => 100,
        'bulk_update_limit' => 50,
        'request_timeout_seconds' => 60,
        'max_include_depth' => 5,
    ];

    /**
     * @param array<string,mixed>|object $config CodeIgniter config items or plain array.
     */
    public static function configure($config = null): void
    {
        $items = [];
        if ($config !== null && is_object($config) && method_exists($config, 'item')) {
            foreach (array_keys(self::$defaults) as $key) {
                $val = $config->item($key);
                if ($val !== null && $val !== false) {
                    $items[$key] = (int) $val;
                }
            }
        } elseif (is_array($config)) {
            foreach ($config as $key => $val) {
                if (isset(self::$defaults[$key]) && $val !== null) {
                    $items[$key] = (int) $val;
                }
            }
        }

        self::$limits = array_merge(self::$defaults, $items);
        FilterParser::setGuardLimits([
            'maxExpressionLength' => self::limit('max_filter_expression_length'),
            'maxAstDepth' => self::limit('max_filter_ast_depth'),
            'maxAstNodes' => self::limit('max_filter_ast_nodes'),
        ]);
    }

    public static function limit(string $key): int
    {
        if (self::$limits === []) {
            self::configure([]);
        }
        return (int) (self::$limits[$key] ?? self::$defaults[$key] ?? 0);
    }

    public static function clampPageLimit(int $requested, int $default): int
    {
        if ($requested <= 0) {
            $requested = $default;
        }
        $max = self::limit('max_page_size');
        return min($requested, $max);
    }

    public static function clampPageOffset(int $offset): int
    {
        return max(0, $offset);
    }

    public static function maxIncludeDepth(): int
    {
        return self::limit('max_include_depth');
    }

    /**
     * @throws \dbAPI\API\Exception
     */
    public static function assertBulkInsertCount(int $count): void
    {
        $max = self::limit('bulk_insert_limit');
        if ($count > $max) {
            throw new \dbAPI\API\Exception(
                "Bulk insert limit exceeded: {$count} records (maximum {$max})",
                400
            );
        }
    }

    /**
     * @throws \dbAPI\API\Exception
     */
    public static function assertBulkUpdateCount(int $count): void
    {
        $max = self::limit('bulk_update_limit');
        if ($count > $max) {
            throw new \dbAPI\API\Exception(
                "Bulk update limit exceeded: {$count} records (maximum {$max})",
                400
            );
        }
    }

    /**
     * Apply per-request PHP time limit and DB session timeout when supported.
     *
     * @param object $db CI DB driver
     * @param array<string,mixed> $connection
     */
    public static function applyQueryTimeout($db, array $connection = []): void
    {
        $seconds = (int) ($connection['query_timeout_seconds'] ?? self::limit('request_timeout_seconds'));
        if ($seconds <= 0) {
            return;
        }

        @set_time_limit($seconds + 5);

        if (!is_object($db) || !method_exists($db, 'query')) {
            return;
        }

        $driver = strtolower((string) ($connection['dbdriver'] ?? $db->dbdriver ?? ''));
        try {
            if (strpos($driver, 'mysql') !== false || strpos($driver, 'mysqli') !== false) {
                $db->query('SET SESSION max_execution_time = ' . ($seconds * 1000));
            } elseif (strpos($driver, 'postgre') !== false) {
                $db->query("SET statement_timeout = '" . (int) $seconds . "s'");
            } elseif (strpos($driver, 'sqlsrv') !== false) {
                $db->query('SET LOCK_TIMEOUT ' . ((int) $seconds * 1000));
            }
        } catch (\Throwable $e) {
            RequestContext::log('debug', 'Could not set DB query timeout', [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
