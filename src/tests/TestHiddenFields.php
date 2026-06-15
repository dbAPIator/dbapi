<?php

use GuzzleHttp\Client;

/**
 * Integration tests for hiddenFields / hiddenEntities schema overrides.
 */
class TestHiddenFields extends IntegrationTestCase
{
    private Client $client;
    private string $apiName = '';
    private ?string $apiConfigKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createHttpClient();
        if ($schemaError = self::probeDataplaneDatabase(self::loadConnection())) {
            $this->markTestSkipped($schemaError);
        }
        $this->apiName = 'phpunit-hidden-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->apiName !== '') {
            $this->deleteApi($this->client, $this->apiName, $this->apiConfigKey);
        }
        parent::tearDown();
    }

    private function mgmtHeaders(): array
    {
        return self::managementHeaders($this->apiConfigKey);
    }

    private function provisionApiWithHiddenPassword(): void
    {
        $create = $this->httpRequest($this->client, 'POST', 'mgmt/v1/apis', [
            'headers' => self::managementHeaders(),
            'json' => ['name' => $this->apiName, 'description' => 'hidden fields PHPUnit'],
        ]);
        $created = $this->assertHttpStatus($create, 201, 'create');
        $this->apiConfigKey = $created['managementCredential']['secret'];
        $headers = $this->mgmtHeaders();

        foreach ([
            ['PUT', "mgmt/v1/apis/{$this->apiName}/connection", ['json' => self::loadConnection()]],
            ['POST', "mgmt/v1/apis/{$this->apiName}/connection:test", []],
            ['POST', "mgmt/v1/apis/{$this->apiName}/schema:introspect", []],
            ['POST', "mgmt/v1/apis/{$this->apiName}/schema:rebuild", []],
        ] as [$method, $uri, $opts]) {
            $opts['headers'] = $headers;
            $this->assertHttpStatus($this->httpRequest($this->client, $method, $uri, $opts), 200, "{$method} {$uri}");
        }

        $patch = $this->httpRequest($this->client, 'PATCH', "mgmt/v1/apis/{$this->apiName}/schema/overrides", [
            'headers' => $headers,
            'json' => [
                'hiddenFields' => [
                    'app_users' => ['password'],
                ],
            ],
        ]);
        $patched = $this->assertHttpStatus($patch, 200, 'patch hiddenFields');
        $this->assertSame(['password'], $patched['hiddenFields']['app_users'] ?? null);

        foreach ([
            ['POST', "mgmt/v1/apis/{$this->apiName}/schema:rebuild", []],
        ] as [$method, $uri, $opts]) {
            $opts['headers'] = $headers;
            $this->assertHttpStatus($this->httpRequest($this->client, $method, $uri, $opts), 200, "{$method} {$uri}");
        }

        $effective = $this->httpRequest($this->client, 'GET', "mgmt/v1/apis/{$this->apiName}/schema/effective", [
            'headers' => $headers,
        ]);
        $eff = $this->assertHttpStatus($effective, 200, 'effective schema');
        $this->assertFalse($eff['entities']['app_users']['fields']['password']['select'] ?? true);

        foreach ([
            ['PUT', "mgmt/v1/apis/{$this->apiName}/policies/auth", ['json' => ['mode' => 'none']]],
            ['POST', "mgmt/v1/apis/{$this->apiName}:validate", []],
            ['POST', "mgmt/v1/apis/{$this->apiName}:activate", []],
            ['PUT', "mgmt/v1/apis/{$this->apiName}/policies/data-network", [
                'json' => self::permissiveDataNetworkPolicy(),
            ]],
        ] as [$method, $uri, $opts]) {
            $opts['headers'] = $headers;
            $this->assertHttpStatus($this->httpRequest($this->client, $method, $uri, $opts), 200, "{$method} {$uri}");
        }
    }

    public function testHiddenFieldsEffectiveSchema(): void
    {
        $this->provisionApiWithHiddenPassword();
    }

    public function testHiddenFieldOmittedFromDefaultGet(): void
    {
        $this->provisionApiWithHiddenPassword();

        $resp = $this->client->get("v1/apis/{$this->apiName}/data/app_users/1", [
            'headers' => self::managementHeaders(),
            'http_errors' => false,
        ]);
        $this->assertSame(200, $resp->getStatusCode(), (string) $resp->getBody());
        $body = json_decode((string) $resp->getBody(), true);
        $attrs = $body['data']['attributes'] ?? [];
        $this->assertArrayHasKey('username', $attrs);
        $this->assertArrayNotHasKey('password', $attrs);
    }

    public function testHiddenFieldRejectedInSparseFieldset(): void
    {
        $this->provisionApiWithHiddenPassword();

        $resp = $this->client->get("v1/apis/{$this->apiName}/data/app_users/1", [
            'headers' => self::managementHeaders(),
            'query' => ['fields' => ['app_users' => 'password,username']],
            'http_errors' => false,
        ]);
        $this->assertSame(404, $resp->getStatusCode(), (string) $resp->getBody());
    }
}
