<?php

use GuzzleHttp\Client;

/**
 * Provisions one active API for all data-plane tests in the class.
 */
abstract class DataPlaneTestCase extends IntegrationTestCase
{
    protected static Client $client;
    protected static string $apiName;
    protected static ?string $apiConfigKey = null;
    protected static array $connection;

    public static function setUpBeforeClass(): void
    {
        self::$client = self::createHttpClient();
        self::$connection = self::loadConnection();

        if ($schemaError = self::probeDataplaneDatabase(self::$connection)) {
            self::markTestSkipped($schemaError);
        }

        if (!self::probeManagementApi(self::$client)) {
            self::markTestSkipped(self::managementApiSkipMessage());
        }

        self::$apiName = 'phpunit-data-' . bin2hex(random_bytes(4));
        self::provisionActiveApi();
    }

    public static function tearDownAfterClass(): void
    {
        if (!isset(self::$apiName) || self::$apiName === '') {
            return;
        }
        try {
            self::$client->delete('mgmt/v1/apis/' . self::$apiName, [
                'headers' => self::managementHeaders(self::$apiConfigKey),
                'query' => ['force' => 'true'],
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }
    }

    protected static function provisionActiveApi(): void
    {
        $create = self::$client->post('mgmt/v1/apis', [
            'headers' => self::managementHeaders(),
            'json' => ['name' => self::$apiName, 'description' => 'Data plane PHPUnit'],
            'http_errors' => false,
        ]);
        if ($create->getStatusCode() !== 201) {
            self::fail('Failed to create API: ' . $create->getBody());
        }
        $created = json_decode((string) $create->getBody(), true);
        self::$apiConfigKey = $created['managementCredential']['secret'] ?? null;

        $steps = [
            ['put', 'mgmt/v1/apis/' . self::$apiName . '/connection', ['json' => self::$connection]],
            ['post', 'mgmt/v1/apis/' . self::$apiName . '/connection:test', []],
            ['post', 'mgmt/v1/apis/' . self::$apiName . '/schema:sync', ['query' => ['activate' => 'true']]],
        ];

        foreach ($steps as [$method, $uri, $opts]) {
            $opts['headers'] = self::managementHeaders(self::$apiConfigKey);
            $opts['http_errors'] = false;
            $resp = self::$client->request($method, $uri, $opts);
            if ($resp->getStatusCode() >= 400) {
                self::fail("Provision step {$method} {$uri} failed: " . $resp->getBody());
            }
        }
    }

    protected function dataUrl(
        string $resource,
        ?string $id = null,
        ?string $relation = null,
        ?string $relId = null,
        ?string $subRelation = null,
        ?string $subRelId = null
    ): string {
        $path = 'v1/apis/' . self::$apiName . '/data/' . $resource;
        if ($id !== null) {
            $path .= '/' . $id;
        }
        if ($relation !== null) {
            $path .= '/' . $relation;
        }
        if ($relId !== null) {
            $path .= '/' . $relId;
        }
        if ($subRelation !== null) {
            $path .= '/' . $subRelation;
        }
        if ($subRelId !== null) {
            $path .= '/' . $subRelId;
        }
        return $path;
    }

    protected function dataRequest(string $method, string $uri, array $options = [])
    {
        $options['headers'] = array_merge(
            self::managementHeaders(),
            $options['headers'] ?? []
        );
        return self::$client->request($method, $uri, array_merge($options, ['http_errors' => false]));
    }

    protected function uniqueEmail(string $prefix = 'test'): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4)) . '@dataplane.test';
    }
}
