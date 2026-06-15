<?php

use GuzzleHttp\Client;

/**
 * Shared provisioning and HTTP helpers for access-control integration tests.
 */
trait AccessControlIntegrationTrait
{
    protected Client $accessClient;
    protected string $accessApiName = '';
    protected ?string $accessApiConfigKey = null;
    protected array $accessConnection = [];

    protected function setUpAccessControlClient(): void
    {
        $this->accessClient = self::createHttpClient();
        $this->accessConnection = self::loadConnection();
        if ($schemaError = self::probeDataplaneDatabase($this->accessConnection)) {
            $this->markTestSkipped($schemaError);
        }
        if (!self::probeManagementApi($this->accessClient)) {
            $this->markTestSkipped(self::managementApiSkipMessage());
        }
        $this->accessApiName = 'phpunit-acl-' . bin2hex(random_bytes(4));
    }

    protected function tearDownAccessControlApi(): void
    {
        if ($this->accessApiName !== '') {
            $this->deleteApi($this->accessClient, $this->accessApiName, $this->accessApiConfigKey);
            $this->accessApiName = '';
        }
    }

    /**
     * @param array{
     *   pathRules?: array<int, array<string, mixed>>|null,
     *   schemaOverrides?: array<string, mixed>,
     *   defaultAccessRule?: string,
     *   filterBypassRoles?: string[],
     *   loginSql?: string
     * } $options
     */
    protected function provisionAccessControlApi(array $options = []): void
    {
        $create = $this->httpRequest($this->accessClient, 'POST', 'mgmt/v1/apis', [
            'headers' => self::managementHeaders(),
            'json' => ['name' => $this->accessApiName, 'description' => 'Access control PHPUnit'],
        ]);
        $created = $this->assertHttpStatus($create, 201, 'create draft');
        $this->accessApiConfigKey = $created['managementCredential']['secret'];
        $headers = self::managementHeaders($this->accessApiConfigKey);

        $loginSql = $options['loginSql']
            ?? "SELECT id AS userId, role FROM app_users WHERE username='[[login]]' AND password='[[password]]'";

        $steps = [
            ['PUT', "mgmt/v1/apis/{$this->accessApiName}/connection", ['json' => $this->accessConnection]],
            ['POST', "mgmt/v1/apis/{$this->accessApiName}/connection:test", []],
            ['POST', "mgmt/v1/apis/{$this->accessApiName}/schema:introspect", []],
            ['POST', "mgmt/v1/apis/{$this->accessApiName}/schema:rebuild", []],
        ];

        foreach ($steps as [$method, $uri, $opts]) {
            $opts['headers'] = $headers;
            $this->assertHttpStatus($this->httpRequest($this->accessClient, $method, $uri, $opts), 200, "{$method} {$uri}");
        }

        if (!empty($options['schemaOverrides'])) {
            $patch = $this->httpRequest($this->accessClient, 'PATCH', "mgmt/v1/apis/{$this->accessApiName}/schema/overrides", [
                'headers' => $headers,
                'json' => $options['schemaOverrides'],
            ]);
            $this->assertHttpStatus($patch, 200, 'patch schema overrides');
            $rebuild = $this->httpRequest($this->accessClient, 'POST', "mgmt/v1/apis/{$this->accessApiName}/schema:rebuild", [
                'headers' => $headers,
            ]);
            $this->assertHttpStatus($rebuild, 200, 'rebuild after overrides');
        }

        $authBody = [
            'mode' => 'dbAuth',
            'default_access_rule' => $options['defaultAccessRule'] ?? 'private',
            'dbAuth' => [
                'validity' => 3600,
                'loginMethods' => [
                    'password' => ['sql' => $loginSql],
                ],
            ],
        ];
        if (!empty($options['filterBypassRoles'])) {
            $authBody['filterBypassRoles'] = $options['filterBypassRoles'];
            $authBody['dbAuth']['filterBypassRoles'] = $options['filterBypassRoles'];
        }

        $authSteps = [
            ['PUT', "mgmt/v1/apis/{$this->accessApiName}/policies/auth", ['json' => $authBody]],
            ['POST', "mgmt/v1/apis/{$this->accessApiName}:validate", []],
            ['POST', "mgmt/v1/apis/{$this->accessApiName}:activate", []],
        ];
        foreach ($authSteps as [$method, $uri, $opts]) {
            $opts['headers'] = $headers;
            $this->assertHttpStatus($this->httpRequest($this->accessClient, $method, $uri, $opts), 200, "{$method} {$uri}");
        }

        $pathRules = array_key_exists('pathRules', $options) ? $options['pathRules'] : [];
        $dataNet = self::allowAllIpDataNetworkPolicy();
        $dataNet['path'] = $pathRules;
        $net = $this->httpRequest($this->accessClient, 'PUT', "mgmt/v1/apis/{$this->accessApiName}/policies/data-network", [
            'headers' => $headers,
            'json' => $dataNet,
        ]);
        $this->assertHttpStatus($net, 200, 'put data-network');
    }

    protected function accessDataUrl(string $resource, ?string $id = null): string
    {
        $path = 'v1/apis/' . $this->accessApiName . '/data/' . $resource;
        if ($id !== null) {
            $path .= '/' . $id;
        }
        return $path;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function accessDataRequest(string $method, string $uri, ?string $bearer = null, array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        if ($bearer !== null && $bearer !== '') {
            $bearerHeader = 'Bearer ' . $bearer;
            // Standard header; X-Authorization when Apache/PHP-FPM strips Authorization (CGIPassAuth).
            $headers['Authorization'] = $bearerHeader;
            $headers['X-Authorization'] = $bearerHeader;
        }
        $req = array_merge($options, [
            'headers' => array_merge(self::managementHeaders(), $headers),
            'http_errors' => false,
        ]);
        $response = $this->accessClient->request($method, $uri, $req);
        $raw = (string) $response->getBody();
        $body = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }
        return ['status' => $response->getStatusCode(), 'body' => $body];
    }

    protected function accessLogin(string $login, string $password): string
    {
        $response = $this->accessClient->post("apis/{$this->accessApiName}/auth/login/password", [
            'form_params' => ['login' => $login, 'password' => $password],
            'http_errors' => false,
        ]);
        $this->assertSame(200, $response->getStatusCode(), 'login failed: ' . $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('access_token', $body);
        return $body['access_token'];
    }

    /**
     * @return int[]
     */
    protected function extractOrderIds(array $body): array
    {
        $ids = [];
        foreach ($body['data'] ?? [] as $row) {
            if (isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }
        sort($ids);
        return $ids;
    }

    protected function defaultSchemaOverrides(): array
    {
        return [
            'products' => ['access' => 'public'],
            'users' => [
                'access' => 'scoped',
                'scopePattern' => '/users/{{userId}}',
            ],
            'orders' => [
                'mandatoryFilter' => 'customer_id={{userId}}',
            ],
        ];
    }
}
