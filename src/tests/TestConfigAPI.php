<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TestConfigAPI extends TestCase
{
    private Client $client;
    private string $apiToken = "myverysecuresecret"; 
    private string $apiName = "dbapiator_demo";

    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost:8888/',
            'timeout' => 5.0,
        ]);
    }

    private function assert_response($response) {
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getBody());
        $data = json_decode($response->getBody(), true);
        $this->assertIsArray($data);
        return $data;
    }

    public function testCreateApi()
    {
        $response = $this->client->request('POST', 'apis', [
            'headers' => [
                'X-Api-Key' => $this->apiToken
            ],
            'json' => json_decode(file_get_contents(__DIR__ . '/setup.json'))
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        return $data['apiKey'];
    }

    /**
     * @depends testCreateApi
     */
    public function testFailedCreateApi($apiKey)
    {
        try {
            $response = $this->client->request('POST', 'apis', [
                'headers' => [
                    'X-Api-Key' => $this->apiToken
                ],
                'json' => json_decode(file_get_contents(__DIR__ . '/setup.json'))
            ]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
        }

        $this->assertEquals(409, $response->getStatusCode());
        return $apiKey;
    }

    
    /**
     * @depends testCreateApi
     */
    public function testGetStructure(string $apiKey)
    {
        $response = $this->client->request('GET', "apis/{$this->apiName}/config/structure", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);
        
        $data = $this->assert_response($response);
        $this->assertEquals(5, count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testGetStructure
     */
    public function testReplaceStructure(array $data)
    {
        $structure = $data[1];
        $apiKey = $data[0];
        $structure["Orders"]["fields"]["order_date"]["required"] = true;
        $response = $this->client->request('PUT', "apis/{$this->apiName}/config/structure", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => $structure
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(true, $data["Orders"]["fields"]["order_date"]["required"]);
        return [$apiKey, $data];
    }

    /**
     * @depends testReplaceStructure
     */
    public function testPatchStructure(array $data)
    {
        $structure = $data[1];
        $apiKey = $data[0];
        $data = [
            "Orders" => $structure["Orders"]
        ];
        $data["Orders"]["fields"]["order_date"]["read"] = true;
        $response = $this->client->request('PATCH', "apis/{$this->apiName}/config/structure", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => $data
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(true, $data["Orders"]["fields"]["order_date"]["read"]);
        return [$apiKey, $data];
    }

    /**
     * @depends testPatchStructure
     */
    public function testRegenApi(array $data)
    {
        $apiKey = $data[0];
        $response = $this->client->request('POST', "apis/{$this->apiName}/config/structure/regen", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(5, count($data));
        $this->assertEquals(true, $data["Orders"]["fields"]["order_date"]["read"]);
        $this->assertEquals(true, $data["Orders"]["fields"]["order_date"]["required"]);
        return [$apiKey, $data];
    }

    private function assert_hooks(array $data) {
        $this->assertEquals(3, count($data));
        return $data;
    }
    /**
     * @depends testRegenApi
     */
    public function testSetHooks(array $data)
    {
        $apiKey = $data[0];
        $data = json_decode(file_get_contents(__DIR__ . '/hooks.json'));
        $response = $this->client->request('PUT', "apis/{$this->apiName}/config/hooks/Customers", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => $data
        ]);

        $data = $this->assert_response($response);
        $this->assert_hooks($data);
        return [$apiKey, $data];
    }

    
    /**
     * @depends testSetHooks
     */
    public function testGetHooks(array $data)
    {
        $apiKey = $data[0];
        $response = $this->client->request('GET', "apis/{$this->apiName}/config/hooks/Customers", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);

        $data = $this->assert_response($response);
        $this->assert_hooks($data);
        return [$apiKey, $data];
    }

    /**
     * @depends testSetHooks
     */
    public function testSetAuth(array $data) {
        $apiKey = $data[0];
        $response = $this->client->request('PUT', "apis/{$this->apiName}/config/auth", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => json_decode(file_get_contents(__DIR__ . '/auth.json'))
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(false, $data["allowGuest"]);
        return [$apiKey, $data];
    }

    /**
     * @depends testSetAuth
     */
    public function testPatchAuth(array $data) {
        $apiKey = $data[0];
        $response = $this->client->request('PATCH', "apis/{$this->apiName}/config/auth", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => [
                "allowGuest" => true
            ]
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(true, $data["allowGuest"]);
        return [$apiKey, $data];
    }

    /**
     * @depends testSetAuth
     */
    public function testGetAclsIp(array $data) {
        $apiKey = $data[0];
        $response = $this->client->request('GET', "apis/{$this->apiName}/config/acls/ip", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(2, count($data));
        return [$apiKey, $data];
    }
    /**
     * @depends testGetAclsIp
     */
    public function testSetAclsIp(array $data) {
        // return $data; 
        $apiKey = $data[0];
        $response = $this->client->request('PUT', "apis/{$this->apiName}/config/acls/ip", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => json_decode(file_get_contents(__DIR__ . '/acls_ip.json'))
        ]);
        
        $data = $this->assert_response($response);
        $this->assertEquals(3, count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testSetAclsIp
     */
    public function testGetAclsPath(array $data) {
        $apiKey = $data[0];
        $response = $this->client->request('GET', "apis/{$this->apiName}/config/acls/path", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);
        
        $data = $this->assert_response($response);
        $this->assertEquals(7, count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testGetAclsPath
     */
    public function testSetAclsPath(array $data) {
        $apiKey = $data[0];
        $response = $this->client->request('PUT', "apis/{$this->apiName}/config/acls/path", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => json_decode(file_get_contents(__DIR__ . '/acls_path.json'))
        ]);
        
        $data = $this->assert_response($response);
        $this->assertEquals(5, count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testGetAclsPath
     */
    public function testGetAdminAclsIp(array $data) {
        $apiKey = $data[0];
        $response = $this->client->request('GET', "apis/{$this->apiName}/config/admin/acls", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);
        
        $data = $this->assert_response($response);
        $this->assertEquals(2, count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testGetAdminAclsIp
     */
    public function testSetAdminAclsIp(array $data) {
        $apiKey = $data[0];
        $response = $this->client->request('PUT', "apis/{$this->apiName}/config/admin/acls", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => json_decode(file_get_contents(__DIR__ . '/acls_ip.json'))
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(3, count($data));
        return [$apiKey, $data];
    }


    /**
     * @depends testSetAdminAclsIp
     */
    public function testAdminResetSecret() {
        $response = $this->client->request('POST', "apis/{$this->apiName}/config/admin/secret/reset", [
            'headers' => ['X-Api-Key' => $this->apiToken]
        ]);
        
        $data = $this->assert_response($response);
        
        $data = json_decode($response->getBody(), true);
        return $data['apiKey'];
    }

    /**
     * @depends testAdminResetSecret
     */
    public function testDeleteApi($apiKey) {
        $response = $this->client->request('DELETE', "apis/{$this->apiName}", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);
        $this->assertEquals(204, $response->getStatusCode());
        return $apiKey;
    }
}
