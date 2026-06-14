<?php

use GuzzleHttp\Client;

/**
 * Management API integration tests for access-control policies.
 */
class TestAccessControlManagement extends IntegrationTestCase
{
    use AccessControlIntegrationTrait;

    private Client $mgmtClient;
    private string $mgmtApiName = '';
    private ?string $mgmtApiConfigKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mgmtClient = self::createHttpClient();
        $this->accessConnection = self::loadConnection();
        if ($schemaError = self::probeDataplaneDatabase($this->accessConnection)) {
            $this->markTestSkipped($schemaError);
        }
        $this->mgmtApiName = 'phpunit-acl-mgmt-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->mgmtApiName !== '') {
            $this->deleteApi($this->mgmtClient, $this->mgmtApiName, $this->mgmtApiConfigKey);
        }
        parent::tearDown();
    }

    private function mgmtHeaders(): array
    {
        return self::managementHeaders($this->mgmtApiConfigKey);
    }

    private function createDraftWithSchema(): void
    {
        $create = $this->httpRequest($this->mgmtClient, 'POST', 'mgmt/v1/apis', [
            'headers' => self::managementHeaders(),
            'json' => ['name' => $this->mgmtApiName, 'description' => 'ACL mgmt'],
        ]);
        $created = $this->assertHttpStatus($create, 201, 'create');
        $this->mgmtApiConfigKey = $created['managementCredential']['secret'];

        foreach ([
            ['PUT', "mgmt/v1/apis/{$this->mgmtApiName}/connection", ['json' => $this->accessConnection]],
            ['POST', "mgmt/v1/apis/{$this->mgmtApiName}/connection:test", []],
            ['POST', "mgmt/v1/apis/{$this->mgmtApiName}/schema:introspect", []],
            ['POST', "mgmt/v1/apis/{$this->mgmtApiName}/schema:rebuild", []],
        ] as [$method, $uri, $opts]) {
            $opts['headers'] = $this->mgmtHeaders();
            $this->assertHttpStatus($this->httpRequest($this->mgmtClient, $method, $uri, $opts), 200, "{$method} {$uri}");
        }
    }

    public function testPutAuthPolicyStoresDefaultAccessRulePrivate(): void
    {
        $this->createDraftWithSchema();

        $put = $this->httpRequest($this->mgmtClient, 'PUT', "mgmt/v1/apis/{$this->mgmtApiName}/policies/auth", [
            'headers' => $this->mgmtHeaders(),
            'json' => [
                'mode' => 'dbAuth',
                'default_access_rule' => 'private',
                'filterBypassRoles' => ['admin'],
                'dbAuth' => [
                    'validity' => 7200,
                    'loginMethods' => [
                        'password' => [
                            'sql' => "SELECT id AS userId, role FROM app_users WHERE username='[[login]]'",
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertHttpStatus($put, 200, 'put auth');

        $get = $this->httpRequest($this->mgmtClient, 'GET', "mgmt/v1/apis/{$this->mgmtApiName}/policies/auth", [
            'headers' => $this->mgmtHeaders(),
        ]);
        $body = $this->assertHttpStatus($get, 200, 'get auth');
        $this->assertSame('dbAuth', $body['mode']);
        $this->assertSame('private', $body['default_access_rule']);
        $this->assertEquals(['admin'], $body['filterBypassRoles']);
        $this->assertArrayHasKey('jwt_key', $body);
    }

    public function testPutAuthModeNoneStoresPublicDefault(): void
    {
        $this->createDraftWithSchema();

        $put = $this->httpRequest($this->mgmtClient, 'PUT', "mgmt/v1/apis/{$this->mgmtApiName}/policies/auth", [
            'headers' => $this->mgmtHeaders(),
            'json' => ['mode' => 'none'],
        ]);
        $this->assertHttpStatus($put, 200, 'put auth none');

        $get = $this->httpRequest($this->mgmtClient, 'GET', "mgmt/v1/apis/{$this->mgmtApiName}/policies/auth", [
            'headers' => $this->mgmtHeaders(),
        ]);
        $body = $this->assertHttpStatus($get, 200, 'get auth none');
        $this->assertSame('none', $body['mode']);
        $this->assertSame('public', $body['default_access_rule']);
    }

    public function testPutDataNetworkPathRuleWithWhen(): void
    {
        $this->createDraftWithSchema();

        $policy = [
            'defaultAction' => 'deny',
            'rules' => [['cidr' => '0.0.0.0/0', 'action' => 'allow']],
            'path' => [
                ['pattern' => '/*', 'methods' => '*', 'allow' => true, 'when' => ['role' => 'admin']],
                ['pattern' => '/users/{{userId}}/*', 'methods' => 'GET', 'allow' => true],
                ['pattern' => '/*', 'methods' => '*', 'allow' => false],
            ],
        ];
        $put = $this->httpRequest($this->mgmtClient, 'PUT', "mgmt/v1/apis/{$this->mgmtApiName}/policies/data-network", [
            'headers' => $this->mgmtHeaders(),
            'json' => $policy,
        ]);
        $this->assertHttpStatus($put, 200, 'put data-network');

        $get = $this->httpRequest($this->mgmtClient, 'GET', "mgmt/v1/apis/{$this->mgmtApiName}/policies/data-network", [
            'headers' => $this->mgmtHeaders(),
        ]);
        $body = $this->assertHttpStatus($get, 200, 'get data-network');
        $this->assertArrayHasKey('path', $body);
        $this->assertCount(3, $body['path']);
        $this->assertEquals(['role' => 'admin'], $body['path'][0]['when']);
    }

    public function testPatchSchemaOverridesAccessFields(): void
    {
        $this->createDraftWithSchema();

        $patch = $this->httpRequest($this->mgmtClient, 'PATCH', "mgmt/v1/apis/{$this->mgmtApiName}/schema/overrides", [
            'headers' => $this->mgmtHeaders(),
            'json' => $this->defaultSchemaOverrides(),
        ]);
        $patched = $this->assertHttpStatus($patch, 200, 'patch overrides');
        $this->assertSame('public', $patched['products']['access']);
        $this->assertSame('scoped', $patched['users']['access']);
        $this->assertSame('customer_id={{userId}}', $patched['orders']['mandatoryFilter']);

        $rebuild = $this->httpRequest($this->mgmtClient, 'POST', "mgmt/v1/apis/{$this->mgmtApiName}/schema:rebuild", [
            'headers' => $this->mgmtHeaders(),
        ]);
        $this->assertHttpStatus($rebuild, 200, 'rebuild');

        $effective = $this->httpRequest($this->mgmtClient, 'GET', "mgmt/v1/apis/{$this->mgmtApiName}/schema/effective", [
            'headers' => $this->mgmtHeaders(),
        ]);
        $eff = $this->assertHttpStatus($effective, 200, 'effective schema');
        $entities = $eff['entities'] ?? [];
        $this->assertSame('public', $entities['products']['access'] ?? null);
        $this->assertSame('/users/{{userId}}', $entities['users']['scopePattern'] ?? null);
        $this->assertSame('customer_id={{userId}}', $entities['orders']['mandatoryFilter'] ?? null);
    }
}
