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

    /**
     * Enhanced response assertion with full error details
     */
    private function assert_response($response, $expectedStatus = 200) {
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        
        // If status code doesn't match expected, show full error details
        if ($statusCode !== $expectedStatus) {
            $this->fail(sprintf(
                "Expected status code %d, got %d\nFull response body:\n%s",
                $expectedStatus,
                $statusCode,
                $body
            ));
        }
        
        // Try to decode JSON and show full response if it fails
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail(sprintf(
                "Invalid JSON response. JSON error: %s\nFull response body:\n%s",
                json_last_error_msg(),
                $body
            ));
        }
        
        $this->assertIsArray($data);
        return $data;
    }

    /**
     * Helper method to handle exceptions and show full error details
     */
    private function makeRequest($method, $uri, $options = []) {
        try {
            return $this->client->request($method, $uri, $options);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response) {
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $headers = $response->getHeaders();
                
                $errorMessage = sprintf(
                    "Request failed with status %d\nFull response body:\n%s\nResponse headers:\n%s",
                    $statusCode,
                    $body,
                    json_encode($headers, JSON_PRETTY_PRINT)
                );
                
                $this->fail($errorMessage);
            } else {
                $this->fail("Request failed: " . $e->getMessage());
            }
        }
    }

    public function testCreateApi()
    {
        $response = $this->makeRequest('POST', 'apis', [
            'headers' => [
                'X-Api-Key' => $this->apiToken
            ],
            'json' => json_decode(file_get_contents(__DIR__ . '/setup.json'))
        ]);

        $data = $this->assert_response($response);
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
            if ($response) {
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                
                // Show full error details for debugging
                if ($statusCode !== 409) {
                    $this->fail(sprintf(
                        "Expected status code 409, got %d\nFull response body:\n%s",
                        $statusCode,
                        $body
                    ));
                }
            }
        }

        $this->assertEquals(409, $response->getStatusCode());
        return $apiKey;
    }

    /**
     * @depends testCreateApi
     */
    public function testGetStructure(string $apiKey)
    {
        $response = $this->makeRequest('GET', "apis/{$this->apiName}/config/structure", [
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
        $response = $this->makeRequest('PUT', "apis/{$this->apiName}/config/structure", [
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
        $response = $this->makeRequest('PATCH', "apis/{$this->apiName}/config/structure", [
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
        $response = $this->makeRequest('POST', "apis/{$this->apiName}/config/structure/regen", [
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
        $response = $this->makeRequest('PUT', "apis/{$this->apiName}/config/hooks/Customers", [
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
        $response = $this->makeRequest('GET', "apis/{$this->apiName}/config/hooks/Customers", [
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
        $response = $this->makeRequest('PUT', "apis/{$this->apiName}/config/auth", [
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
        $response = $this->makeRequest('PATCH', "apis/{$this->apiName}/config/auth", [
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
        $response = $this->makeRequest('GET', "apis/{$this->apiName}/config/acls/ip", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(4, count($data));
        return [$apiKey, $data];
    }
    
    /**
     * @depends testGetAclsIp
     */
    public function testSetAclsIp(array $data) {
        $acls = json_decode(file_get_contents(__DIR__ . '/acls_ip.json'));
        $apiKey = $data[0];
        $response = $this->makeRequest('PUT', "apis/{$this->apiName}/config/acls/ip", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => $acls
        ]);
        
        $data = $this->assert_response($response);
        $this->assertEquals(count($acls), count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testSetAclsIp
     */
    public function testGetAclsPath(array $data) {
        $apiKey = $data[0];
        $response = $this->makeRequest('GET', "apis/{$this->apiName}/config/acls/path", [
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
        $response = $this->makeRequest('PUT', "apis/{$this->apiName}/config/acls/path", [
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
        $response = $this->makeRequest('GET', "apis/{$this->apiName}/config/admin/acls", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);
        
        $data = $this->assert_response($response);
        $this->assertEquals(3, count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testGetAdminAclsIp
     */
    public function testSetAdminAclsIp(array $data) {
        $apiKey = $data[0];
        $acls = json_decode(file_get_contents(__DIR__ . '/acls_ip.json'));
        $response = $this->makeRequest('PUT', "apis/{$this->apiName}/config/admin/acls", [
            'headers' => ['X-Api-Key' => $apiKey],
            'json' => $acls
        ]);

        $data = $this->assert_response($response);
        $this->assertEquals(count($acls), count($data));
        return [$apiKey, $data];
    }

    /**
     * @depends testSetAdminAclsIp
     */
    public function testAdminResetSecret() {
        $response = $this->makeRequest('POST', "apis/{$this->apiName}/config/admin/secret/reset", [
            'headers' => ['X-Api-Key' => $this->apiToken]
        ]);
        
        $data = $this->assert_response($response);
        return $data['apiKey'];
    }

    /**
     * @depends testAdminResetSecret
     */
    public function testDeleteApi($apiKey) {
        $response = $this->makeRequest('DELETE', "apis/{$this->apiName}", [
            'headers' => ['X-Api-Key' => $apiKey]
        ]);
        
        $this->assertEquals(204, $response->getStatusCode());
        return $apiKey;
    }
}
