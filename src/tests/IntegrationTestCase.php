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
}
