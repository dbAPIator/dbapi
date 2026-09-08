<?php

use GuzzleHttp\Client;

/**
 * Multi-method login via POST .../auth/login/{loginMethod}
 */
class TestAuthLoginMethods extends IntegrationTestCase
{
    private Client $client;
    private ?string $apiName = null;
    private ?string $apiConfigKey = null;
    private array $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createHttpClient();
        if (!self::probeManagementApi($this->client)) {
            $this->markTestSkipped(self::managementApiSkipMessage());
        }
        $this->connection = self::loadConnection();
        $this->apiName = 'phpunit-auth-' . bin2hex(random_bytes(4));
        $this->provisionApiWithMultiLogin();
    }

    protected function tearDown(): void
    {
        if ($this->apiName !== null && $this->apiName !== '') {
            $this->deleteApi($this->client, $this->apiName, $this->apiConfigKey);
        }
        parent::tearDown();
    }

    private function provisionApiWithMultiLogin(): void
    {
        $create = $this->httpRequest($this->client, 'POST', 'mgmt/v1/apis', [
            'headers' => self::managementHeaders(),
            'json' => ['name' => $this->apiName, 'description' => 'Auth login methods'],
        ]);
        $created = $this->assertHttpStatus($create, 201, 'create draft');
        $this->apiConfigKey = $created['managementCredential']['secret'];

        $headers = self::managementHeaders($this->apiConfigKey);
        $steps = [
            ['PUT', "mgmt/v1/apis/{$this->apiName}/connection", ['json' => $this->connection]],
            ['POST', "mgmt/v1/apis/{$this->apiName}/connection:test", []],
            ['POST', "mgmt/v1/apis/{$this->apiName}/schema:introspect", []],
            ['POST', "mgmt/v1/apis/{$this->apiName}/schema:rebuild", []],
            ['PUT', "mgmt/v1/apis/{$this->apiName}/policies/auth", ['json' => [
                'mode' => 'dbAuth',
                'dbAuth' => [
                    'validity' => 3600,
                    'refresh_validity' => 2592000,
                    'loginMethods' => [
                        'password' => [
                            'sql' => "SELECT username AS unm, role FROM app_users WHERE username='[[login]]' AND password='[[password]]'",
                        ],
                        'pin' => [
                            'sql' => "SELECT username AS unm, role FROM app_users WHERE pin='[[pin]]'",
                            'validity' => 900,
                            'refresh_validity' => 0,
                        ],
                    ],
                ],
            ]]],
            ['PUT', "mgmt/v1/apis/{$this->apiName}/policies/data-network", ['json' => self::allowAllIpDataNetworkPolicy()]],
            ['POST', "mgmt/v1/apis/{$this->apiName}:validate", []],
            ['POST', "mgmt/v1/apis/{$this->apiName}:activate", []],
        ];

        foreach ($steps as [$method, $uri, $opts]) {
            $opts['headers'] = $headers;
            $resp = $this->httpRequest($this->client, $method, $uri, $opts);
            $this->assertHttpStatus($resp, 200, "{$method} {$uri}");
        }
    }

    private function loginForm(string $loginMethod, array $fields): array
    {
        $response = $this->client->post("apis/{$this->apiName}/auth/login/{$loginMethod}", [
            'form_params' => $fields,
            'http_errors' => false,
        ]);
        return [
            'status' => $response->getStatusCode(),
            'body' => json_decode((string) $response->getBody(), true),
        ];
    }

    public function testPasswordLoginViaPath(): void
    {
        $result = $this->loginForm('password', [
            'login' => 'testuser',
            'password' => 'testpass',
        ]);
        $this->assertSame(200, $result['status']);
        $this->assertArrayHasKey('access_token', $result['body']);
        $this->assertSame(3600, $result['body']['expires_in']);
        $this->assertArrayHasKey('refresh_token', $result['body']);
        $this->assertSame(2592000, $result['body']['refresh_expires_in']);
    }

    public function testPinLoginViaPath(): void
    {
        $result = $this->loginForm('pin', [
            'pin' => '1234',
        ]);
        $this->assertSame(200, $result['status']);
        $this->assertArrayHasKey('access_token', $result['body']);
        $this->assertSame(900, $result['body']['expires_in']);
        $this->assertArrayNotHasKey('refresh_token', $result['body']);
    }

    public function testGetLoginMethodsListsConfiguredMethods(): void
    {
        $response = $this->client->get("apis/{$this->apiName}/auth/login", [
            'http_errors' => false,
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('loginMethods', $body);
        $this->assertCount(2, $body['loginMethods']);

        $byName = [];
        foreach ($body['loginMethods'] as $method) {
            $byName[$method['name']] = $method;
        }

        $this->assertArrayHasKey('password', $byName);
        $this->assertSame(['login', 'password'], $byName['password']['fields']);
        $this->assertSame(3600, $byName['password']['expiresIn']);
        $this->assertSame(2592000, $byName['password']['refreshExpiresIn']);

        $this->assertArrayHasKey('pin', $byName);
        $this->assertSame(['pin'], $byName['pin']['fields']);
        $this->assertSame(900, $byName['pin']['expiresIn']);
        $this->assertArrayNotHasKey('refreshExpiresIn', $byName['pin']);
    }

    public function testBareLoginUrlReturns404OnPost(): void
    {
        $response = $this->client->post("apis/{$this->apiName}/auth/login", [
            'form_params' => ['login' => 'testuser', 'password' => 'testpass'],
            'http_errors' => false,
        ]);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUnknownLoginMethodInPathReturns404(): void
    {
        $result = $this->loginForm('oauth', ['token' => 'x']);
        $this->assertSame(404, $result['status']);
    }

    public function testInvalidCredentialsReturn404(): void
    {
        $result = $this->loginForm('pin', ['pin' => '0000']);
        $this->assertSame(404, $result['status']);
    }

    public function testRefreshRotatesTokenAndRejectsTheOldOne(): void
    {
        $login = $this->loginForm('password', [
            'login' => 'testuser',
            'password' => 'testpass',
        ]);
        $this->assertSame(200, $login['status']);
        $oldRefresh = $login['body']['refresh_token'];

        $first = $this->client->post("apis/{$this->apiName}/auth/refresh", [
            'form_params' => ['refresh_token' => $oldRefresh],
            'http_errors' => false,
        ]);
        $this->assertSame(200, $first->getStatusCode());
        $body = json_decode((string) $first->getBody(), true);
        $this->assertArrayHasKey('access_token', $body);
        $this->assertArrayHasKey('refresh_token', $body);
        $this->assertNotSame($oldRefresh, $body['refresh_token']);

        $reuse = $this->client->post("apis/{$this->apiName}/auth/refresh", [
            'form_params' => ['refresh_token' => $oldRefresh],
            'http_errors' => false,
        ]);
        $this->assertSame(401, $reuse->getStatusCode());

        $second = $this->client->post("apis/{$this->apiName}/auth/refresh", [
            'form_params' => ['refresh_token' => $body['refresh_token']],
            'http_errors' => false,
        ]);
        $this->assertSame(200, $second->getStatusCode());
    }

    public function testRefreshMissingTokenReturns400(): void
    {
        $response = $this->client->post("apis/{$this->apiName}/auth/refresh", [
            'form_params' => [],
            'http_errors' => false,
        ]);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $login = $this->loginForm('password', [
            'login' => 'testuser',
            'password' => 'testpass',
        ]);
        $refresh = $login['body']['refresh_token'];

        $logout = $this->client->post("apis/{$this->apiName}/auth/logout", [
            'form_params' => ['refresh_token' => $refresh],
            'http_errors' => false,
        ]);
        $this->assertSame(204, $logout->getStatusCode());

        $reuse = $this->client->post("apis/{$this->apiName}/auth/refresh", [
            'form_params' => ['refresh_token' => $refresh],
            'http_errors' => false,
        ]);
        $this->assertSame(401, $reuse->getStatusCode());
    }
}
