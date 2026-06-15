<?php

use GuzzleHttp\Client;

/**
 * Management API (/mgmt/v1) integration tests.
 *
 * Replaces src/test_management_api.sh
 *
 * Requires:
 * - dbAPI at BASE_URL (default http://localhost/dbapi/src/)
 * - MySQL dbapi_test loaded (src/tests/dbapi_test.sql)
 * - src/tests/connection.json (or test.env / CONNECTION_JSON)
 */
class TestManagementAPI extends IntegrationTestCase
{
    private Client $client;
    private string $apiName;
    private ?string $apiConfigKey = null;
    private array $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createHttpClient();
        if (!self::probeManagementApi($this->client)) {
            $this->markTestSkipped(self::managementApiSkipMessage());
        }
        $this->apiName = 'phpunit-mgmt-' . bin2hex(random_bytes(4));
        $this->connection = self::loadConnection();
    }

    protected function tearDown(): void
    {
        if (isset($this->apiName) && $this->apiName !== '') {
            $this->deleteApi($this->client, $this->apiName, $this->apiConfigKey);
        }
        parent::tearDown();
    }

    private function apiHeaders(): array
    {
        return self::managementHeaders($this->apiConfigKey);
    }

    /**
     * Full stepped lifecycle from test_management_api.sh (steps 1–8).
     */
    public function testSteppedLifecycleAndDataApi(): void
    {
        $create = $this->httpRequest($this->client, 'POST', 'mgmt/v1/apis', [
            'headers' => self::managementHeaders(),
            'json' => ['name' => $this->apiName, 'description' => 'PHPUnit e2e'],
        ]);
        $created = $this->assertHttpStatus($create, 201, 'create draft');
        $this->assertArrayHasKey('managementCredential', $created);
        $this->apiConfigKey = $created['managementCredential']['secret'];
        $this->assertEquals('draft', $created['api']['status']);

        $conn = $this->httpRequest($this->client, 'PUT', "mgmt/v1/apis/{$this->apiName}/connection", [
            'headers' => $this->apiHeaders(),
            'json' => $this->connection,
        ]);
        $this->assertHttpStatus($conn, 200, 'put connection');

        $test = $this->httpRequest($this->client, 'POST', "mgmt/v1/apis/{$this->apiName}/connection:test", [
            'headers' => $this->apiHeaders(),
        ]);
        $testBody = $this->assertHttpStatus($test, 200, 'connection:test');
        $this->assertEquals('ok', $testBody['status']);

        $intro = $this->httpRequest($this->client, 'POST', "mgmt/v1/apis/{$this->apiName}/schema:sync", [
            'headers' => $this->apiHeaders(),
            'query' => ['activate' => 'true'],
        ]);
        $syncBody = $this->assertHttpStatus($intro, 200, 'schema sync + activate');
        $this->assertArrayHasKey('entityCount', $syncBody);
        $this->assertTrue($syncBody['activation']['activated'] ?? false);

        $getApi = $this->httpRequest($this->client, 'GET', "mgmt/v1/apis/{$this->apiName}", [
            'headers' => $this->apiHeaders(),
        ]);
        $api = $this->assertHttpStatus($getApi, 200, 'get api after sync');
        $this->assertEquals('active', $api['status']);

        $customers = $this->httpRequest($this->client, 'GET', "v1/apis/{$this->apiName}/data/customers", [
            'headers' => self::managementHeaders(),
        ]);
        $custBody = $this->assertHttpStatus($customers, 200, 'data api customers');
        $this->assertArrayHasKey('data', $custBody);

        $deactivate = $this->httpRequest($this->client, 'POST', "mgmt/v1/apis/{$this->apiName}:deactivate", [
            'headers' => $this->apiHeaders(),
        ]);
        $this->assertHttpStatus($deactivate, 200, 'deactivate');

        $blocked = $this->httpRequest($this->client, 'GET', "v1/apis/{$this->apiName}/data/customers", [
            'headers' => self::managementHeaders(),
        ]);
        $this->assertHttpStatus($blocked, 409, 'data api blocked when inactive');

        $delete = $this->httpRequest($this->client, 'DELETE', "mgmt/v1/apis/{$this->apiName}", [
            'headers' => self::managementHeaders(),
            'query' => ['force' => 'true'],
        ]);
        $this->assertHttpStatus($delete, 204, 'delete api');
        $this->apiName = '';
    }

    public function testQuickCreateImmediate(): void
    {
        $name = 'phpunit-quick-' . bin2hex(random_bytes(4));
        try {
            $response = $this->httpRequest($this->client, 'POST', 'mgmt/v1/apis', [
                'headers' => self::managementHeaders(),
                'query' => ['provision' => 'immediate'],
                'json' => [
                    'name' => $name,
                    'connection' => $this->connection,
                ],
            ]);
            $this->assertContains($response->getStatusCode(), [201, 422]);
            if ($response->getStatusCode() === 201) {
                $body = $this->decodeResponse($response);
                $this->assertEquals('active', $body['api']['status'] ?? '');
            }
        } finally {
            $this->deleteApi($this->client, $name);
        }
    }
}
