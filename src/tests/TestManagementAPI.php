<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;

/**
 * Management API (/mgmt/v1) integration tests.
 *
 * Requires:
 * - dbAPI running at http://localhost/dbapi/src/ (or BASE_URL env)
 * - MySQL dbapi_test loaded (src/tests/dbapi_test.sql)
 * - Credentials in src/tests/connection.json
 */
class TestManagementAPI extends TestCase
{
    private Client $client;
    private string $mgmtKey;
    private string $apiName;
    private ?string $apiConfigKey = null;
    private array $connection;

    protected function setUp(): void
    {
        $base = getenv('BASE_URL') ?: 'http://localhost/dbapi/src/';
        $this->client = new Client(['base_uri' => rtrim($base, '/') . '/', 'timeout' => 30.0]);
        $this->mgmtKey = getenv('MGMT_KEY') ?: 'myverysecuresecret';
        $this->apiName = 'phpunit-mgmt-' . bin2hex(random_bytes(4));
        $connFile = __DIR__ . '/connection.json';
        $this->assertFileExists($connFile, 'Missing tests/connection.json');
        $this->connection = json_decode(file_get_contents($connFile), true);
    }

    protected function tearDown(): void
    {
        if ($this->apiName) {
            try {
                $this->client->delete("mgmt/v1/apis/{$this->apiName}", [
                    'headers' => $this->mgmtHeaders(),
                    'query' => ['force' => 'true'],
                    'http_errors' => false,
                ]);
            } catch (\Throwable $e) {
                // ignore cleanup errors
            }
        }
    }

    private function mgmtHeaders(): array
    {
        return ['X-Management-Key' => $this->mgmtKey, 'Content-Type' => 'application/json'];
    }

    private function apiHeaders(): array
    {
        $h = $this->mgmtHeaders();
        if ($this->apiConfigKey) {
            $h['X-Api-Config-Key'] = $this->apiConfigKey;
        }
        return $h;
    }

    private function decode($response): array
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertIsArray($data, "Invalid JSON: {$body}");
        return $data;
    }

    public function testSteppedLifecycleAndDataApi(): void
    {
        $create = $this->client->post('mgmt/v1/apis', [
            'headers' => $this->mgmtHeaders(),
            'json' => ['name' => $this->apiName, 'description' => 'PHPUnit e2e'],
        ]);
        $this->assertEquals(201, $create->getStatusCode());
        $created = $this->decode($create);
        $this->assertArrayHasKey('managementCredential', $created);
        $this->apiConfigKey = $created['managementCredential']['secret'];
        $this->assertEquals('draft', $created['api']['status']);

        $conn = $this->client->put("mgmt/v1/apis/{$this->apiName}/connection", [
            'headers' => $this->apiHeaders(),
            'json' => $this->connection,
        ]);
        $this->assertEquals(200, $conn->getStatusCode());

        $test = $this->client->post("mgmt/v1/apis/{$this->apiName}/connection:test", [
            'headers' => $this->apiHeaders(),
        ]);
        $this->assertEquals(200, $test->getStatusCode());
        $testBody = $this->decode($test);
        $this->assertEquals('ok', $testBody['status']);

        $intro = $this->client->post("mgmt/v1/apis/{$this->apiName}/schema:introspect", [
            'headers' => $this->apiHeaders(),
        ]);
        $this->assertEquals(200, $intro->getStatusCode());

        $rebuild = $this->client->post("mgmt/v1/apis/{$this->apiName}/schema:rebuild", [
            'headers' => $this->apiHeaders(),
        ]);
        $this->assertEquals(200, $rebuild->getStatusCode());

        $auth = $this->client->put("mgmt/v1/apis/{$this->apiName}/policies/auth", [
            'headers' => $this->apiHeaders(),
            'json' => ['mode' => 'none'],
        ]);
        $this->assertEquals(200, $auth->getStatusCode());

        $validate = $this->client->post("mgmt/v1/apis/{$this->apiName}:validate", [
            'headers' => $this->apiHeaders(),
        ]);
        $this->assertEquals(200, $validate->getStatusCode());
        $valBody = $this->decode($validate);
        $this->assertTrue($valBody['ready'], json_encode($valBody['checks'] ?? []));

        $activate = $this->client->post("mgmt/v1/apis/{$this->apiName}:activate", [
            'headers' => $this->apiHeaders(),
        ]);
        $this->assertEquals(200, $activate->getStatusCode());
        $api = $this->decode($activate);
        $this->assertEquals('active', $api['status']);

        $customers = $this->client->get("v1/apis/{$this->apiName}/data/customers", [
            'headers' => $this->mgmtHeaders(),
            'http_errors' => false,
        ]);
        $this->assertEquals(200, $customers->getStatusCode());
        $custBody = $this->decode($customers);
        $this->assertArrayHasKey('data', $custBody);

        $deactivate = $this->client->post("mgmt/v1/apis/{$this->apiName}:deactivate", [
            'headers' => $this->apiHeaders(),
        ]);
        $this->assertEquals(200, $deactivate->getStatusCode());

        $blocked = $this->client->get("v1/apis/{$this->apiName}/data/customers", [
            'headers' => $this->mgmtHeaders(),
            'http_errors' => false,
        ]);
        $this->assertEquals(409, $blocked->getStatusCode());
    }

    public function testQuickCreateImmediate(): void
    {
        $name = 'phpunit-quick-' . bin2hex(random_bytes(4));
        try {
            $response = $this->client->post('mgmt/v1/apis', [
                'headers' => $this->mgmtHeaders(),
                'query' => ['provision' => 'immediate'],
                'json' => [
                    'name' => $name,
                    'connection' => $this->connection,
                ],
                'http_errors' => false,
            ]);
            $this->assertContains($response->getStatusCode(), [201, 422]);
            if ($response->getStatusCode() === 201) {
                $body = $this->decode($response);
                $this->assertEquals('active', $body['api']['status'] ?? '');
            }
        } finally {
            $this->client->delete("mgmt/v1/apis/{$name}", [
                'headers' => $this->mgmtHeaders(),
                'query' => ['force' => 'true'],
                'http_errors' => false,
            ]);
        }
    }
}
