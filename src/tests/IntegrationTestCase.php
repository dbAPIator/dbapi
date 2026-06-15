<?php

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Shared HTTP helpers for dbAPI integration tests (Management + Data plane).
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static function baseUri(): string
    {
        return rtrim(getenv('BASE_URL') ?: 'http://localhost/dbapi/src/', '/') . '/';
    }

    protected static function managementKey(): string
    {
        return getenv('MGMT_KEY') ?: 'myverysecuresecret';
    }

    protected static function managementApiSkipMessage(): string
    {
        return 'dbAPI management API not reachable at ' . self::baseUri()
            . ' — start the web server (Apache, Docker, or php -S with src/public/ci-router.php).';
    }

    protected static function probeManagementApi(?Client $client = null): bool
    {
        $client = $client ?? self::createHttpClient();
        try {
            $resp = $client->get('mgmt/v1/apis', [
                'headers' => self::managementHeaders(),
                'http_errors' => false,
                'connect_timeout' => 2.0,
                'timeout' => 5.0,
            ]);
            return $resp->getStatusCode() === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function createHttpClient(): Client
    {
        return new Client([
            'base_uri' => self::baseUri(),
            'timeout' => 30.0,
        ]);
    }

    /**
     * Connection config for Management API PUT .../connection.
     */
    protected static function loadConnection(): array
    {
        if ($json = getenv('CONNECTION_JSON')) {
            $conn = json_decode($json, true);
            if (is_array($conn)) {
                return $conn;
            }
        }

        $connFile = __DIR__ . '/connection.json';
        if (is_readable($connFile)) {
            $conn = json_decode(file_get_contents($connFile), true);
            if (is_array($conn)) {
                return $conn;
            }
        }

        return [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_NAME') ?: 'dbapi_test',
            'username' => getenv('DB_USER') ?: 'dbapi',
            'password' => getenv('DB_PASS') ?: 'dbapi',
        ];
    }

    protected static function managementHeaders(?string $apiConfigKey = null): array
    {
        $h = [
            'X-Management-Key' => self::managementKey(),
            'Content-Type' => 'application/json',
        ];
        if ($apiConfigKey !== null && $apiConfigKey !== '') {
            $h['X-Api-Config-Key'] = $apiConfigKey;
        }
        return $h;
    }

    /** Bash management e2e: allow all IPs (path rules set on activate). */
    protected static function allowAllIpDataNetworkPolicy(): array
    {
        return [
            'defaultAction' => 'deny',
            'rules' => [['cidr' => '0.0.0.0/0', 'action' => 'allow']],
        ];
    }

    /** Data-plane write tests: allow all IPs and methods (apply after :activate). */
    protected static function permissiveDataNetworkPolicy(): array
    {
        return [
            'defaultAction' => 'deny',
            'rules' => [['cidr' => '0.0.0.0/0', 'action' => 'allow']],
            'path' => [
                ['pattern' => '/*', 'methods' => 'GET', 'allow' => true],
                ['pattern' => '/*', 'methods' => 'OPTIONS', 'allow' => true],
                ['pattern' => '/*', 'methods' => '*', 'allow' => true],
            ],
        ];
    }

    protected function decodeResponse($response): array
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data, 'Invalid JSON: ' . $body);
        return $data;
    }

    protected function assertHttpStatus($response, int $expected, string $label = ''): array
    {
        $code = $response->getStatusCode();
        $body = (string) $response->getBody();
        $this->assertEquals(
            $expected,
            $code,
            ($label !== '' ? $label . ': ' : '') . "expected HTTP {$expected}, got {$code}. Body: {$body}"
        );
        if ($body === '') {
            return [];
        }
        return $this->decodeResponse($response);
    }

    protected function httpRequest(Client $client, string $method, string $uri, array $options = [])
    {
        $options['http_errors'] = false;
        return $client->request($method, $uri, $options);
    }

    protected function deleteApi(Client $client, string $apiName, ?string $apiConfigKey = null): void
    {
        $client->delete('mgmt/v1/apis/' . $apiName, [
            'headers' => self::managementHeaders($apiConfigKey),
            'query' => ['force' => 'true'],
            'http_errors' => false,
        ]);
    }

    /**
     * Minimum filter_cases rows required by DataPlaneTestsTrait fixtures.
     */
    protected static function dataplaneFilterCasesMinimum(): int
    {
        return 20;
    }

    /**
     * Returns a skip message when the dataplane DB schema is missing or stale; null when OK.
     */
    protected static function probeDataplaneDatabase(?array $connection = null): ?string
    {
        if (!extension_loaded('mysqli')) {
            return null;
        }

        $connection = $connection ?? self::loadConnection();
        $host = $connection['host'] ?? '127.0.0.1';
        $port = (int) ($connection['port'] ?? 3306);
        $database = $connection['database'] ?? 'dbapi_dataplane';
        $username = $connection['username'] ?? 'root';
        $password = $connection['password'] ?? '';

        $mysqli = @new mysqli($host, $username, $password, $database, $port);
        if ($mysqli->connect_errno) {
            return 'Cannot connect to dataplane database '
                . "{$database}@{$host}:{$port} ({$mysqli->connect_error}). "
                . 'Run: composer test:dataplane-setup (or scripts/load-dataplane-schema-local.sh).';
        }

        try {
            $minRows = self::dataplaneFilterCasesMinimum();
            $countResult = $mysqli->query('SELECT COUNT(*) AS c FROM `filter_cases`');
            if (!$countResult) {
                return 'Table filter_cases is missing. Reload schema: composer test:dataplane-setup';
            }
            $count = (int) ($countResult->fetch_assoc()['c'] ?? 0);
            if ($count < $minRows) {
                return "filter_cases has {$count} rows (need >= {$minRows}). "
                    . 'Reload schema: composer test:dataplane-setup';
            }

            $columnResult = $mysqli->query(
                "SHOW COLUMNS FROM `customers` LIKE 'account_manager_id'"
            );
            if (!$columnResult || $columnResult->num_rows === 0) {
                return 'customers.account_manager_id column is missing (stale schema). '
                    . 'Reload schema: composer test:dataplane-setup';
            }
        } finally {
            $mysqli->close();
        }

        return null;
    }

    /**
     * Probe filter_cases total via a live data-plane HTTP endpoint.
     */
    protected static function probeDataPlaneFilterCasesViaHttp(Client $client, string $listPath): bool
    {
        try {
            $resp = $client->get($listPath, [
                'query' => ['page' => ['filter_cases' => ['limit' => 1]]],
                'http_errors' => false,
                'timeout' => 5.0,
            ]);
            if ($resp->getStatusCode() !== 200) {
                return false;
            }
            $body = json_decode((string) $resp->getBody(), true);
            if (!is_array($body)) {
                return false;
            }
            $total = (int) ($body['meta']['totalRecords'] ?? $body['meta']['total'] ?? 0);
            return $total >= self::dataplaneFilterCasesMinimum();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
