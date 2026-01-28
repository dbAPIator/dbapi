<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
/**
 * config test:
 * - create api
 * - get API structure
 * - get Endpoints names
 * - get Endpoint config by name
 * - get Endpoint config by name and table name
 * 
 * user test:
 * - create user
 * - get user by id
 * - delete user by id
 */
class TestDataAPI extends TestCase
{
    private Client $client;
    private string $apiToken = "myverysecuresecret";
    private string $configApiKey;
    private $userId;
    private $customerId;
    private string $apiName = "dbapiator_demo";

    protected function setUp(): void
    {
        // Initialize Guzzle client before each test
        $this->client = new Client([
            'base_uri' => 'http://localhost:8888/', // Change this to your API base URL
            'timeout'  => 5.0,
        ]);
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

    /**
     * Helper method for making requests with form data
     */
    private function makeFormRequest($method, $uri, $formData = [], $headers = []) {
        $options = [
            'form_params' => $formData
        ];
        
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }
        
        return $this->makeRequest($method, $uri, $options);
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
    public function testCreateApiAndGetToken()
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


    // public function testDeleteApi()
    // {
    //     $response = $this->client->request('DELETE', "apis/{$this->apiName}", [
    //         'headers' => [
    //             'Authorization' => 'Bearer ' . $this->configApiKey
    //         ]
    //     ]);
    //     $this->assertEquals(204, $response->getStatusCode());
    //     return $this->configApiKey;
    // }

    /**
     * @depends testCreateApiAndGetToken
     */
    public function testCreateSingleUser(string $token)
    {
        // $this->markTestSkipped('must be revisited.');
        $response = $this->makeRequest('POST', "apis/{$this->apiName}/data/Users/?onduplicate=ignore", [

            'json' => [
                "data"=> [
                    "type"=> "Users",
                    "attributes"=> [
                        "username"=> "testuser",
                        "password_hash"=> "parola123",
                        "role"=> "customer"
                    ]
                ],
                "jsonapi"=> "1.0"
            ]
        ]);

        $responseData = $this->assert_response($response, 201);
        
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertArrayHasKey('attributes', $responseData['data']);
        $this->assertEquals('testuser', $responseData['data']['attributes']['username']);
            
        $this->userId = $responseData['data']['id'];
        return ['userId' => "testuser"];
    }

    /**
     * @depends testCreateSingleUser
     */
    public function testLogIn($data)
    {
        $response = $this->makeFormRequest('POST', "apis/{$this->apiName}/auth/login", [
            "login" => "testuser",
            "password" => "parola123"
        ]);
        $responseData = $this->assert_response($response, 200);
        $this->assertArrayHasKey('access_token', $responseData);
        $this->assertArrayHasKey('expires_in', $responseData);
        $this->assertArrayHasKey('token_type', $responseData);
        echo "token: " ;
        print_r($responseData);
        return ['token' => $responseData['access_token']];
    }



    /**
     * @depends testLogIn
     */
    public function testGetUserById(array $data)
    {
        //$this->markTestSkipped('must be revisited.');
        $response = $this->makeRequest('GET', "apis/{$this->apiName}/data/Users/testuser/", [
            'headers' => [
                'Authorization' => "Bearer {$data['token']}"
            ],
        ]);

        $responseData = $this->assert_response($response, 200);
        
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertArrayHasKey('attributes', $responseData['data']);
        $this->assertEquals('testuser', $responseData['data']['attributes']['username']);
            
        $this->userId = $responseData['data']['id'];
        return ['userId' => $this->userId, 'token' => $data['token']];
    }

    /**
     * @depends testGetUserById
     */
    public function testDeleteUserById(array $data)
    {
        // $this->markTestSkipped('must be revisited.');
        //$this->markTestSkipped('must be revisited.');
        $response = $this->makeRequest('DELETE', "apis/{$this->apiName}/data/Users/{$data['userId']}/", [
            'headers' => [
                'Authorization' => "Bearer {$data['token']}"
            ],
        ]);

        $this->assertEquals(204, $response->getStatusCode());
        return ['userId' => $this->userId, 'token' => $data['token']];
    }

    /**
     * @depends testDeleteUserById
     */
    public function testCreateCustomerWithUser(array $data)
    {   
        $customerData = [
            "data"=> [
                "type"=> "Customers",
                "attributes"=> [
                    "name"=> "Sergiu",
                    "email"=> "sergiu@voicu.ro"
                ],
                "relationships"=>[
                    "user_id"=>[
                        "data"=>[
                            "type"=>"Users",
                            "attributes"=>[
                                "username"=> "svoicu",
                                "password_hash"=> "parola123",
                                "role"=> "customer"
                            ]
                        ]
                    ]
                ]
            ],
            "jsonapi"=> "1.0"
        ];  
        $response = $this->makeRequest('POST', "apis/{$this->apiName}/data/customers", [
            'headers' => [
                'Authorization' => "Bearer {$data['token']}"
            ],
            'json' => $customerData
        ]);

        $responseData = $this->assert_response($response, 201);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('attributes', $data['data']);
        $this->assertArrayHasKey('relationships', $data['data']);
        
        $this->assertEquals($customerData['data']['attributes']['name'], $responseData['data']['attributes']['name']);
        $this->assertEquals($customerData['data']['attributes']['email'], $responseData['data']['attributes']['email']);
        $this->assertEquals($customerData['data']['relationships']['user_id']['data']['attributes']['username'], $responseData['data']['relationships']['user_id']['data']['attributes']['username']);

                
        $this->customerId = $responseData['data']['id'];
        $data['customerId'] = $this->customerId;
        $data['customerData'] = $customerData;
        return $data;
    }

      /**
     * @depends testCreateSingleUser
     */
    public function testGetUserByIdwithIncludes(array $data)
    {
        $this->markTestSkipped('must be revisited.');
        $response = $this->client->request('GET', "apis/{$this->apiName}/data/users/{$data['customerId']}?include=user_id", [
            'headers' => [
                'Authorization' => "Bearer {$data['token']}"
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertArrayHasKey('attributes', $responseData['data']);
        $this->assertArrayHasKey('relationships', $responseData['data']);
        $this->assertArrayHasKey('data', $responseData['data']['relationships']);
        $this->assertArrayHasKey('id', $responseData['data']['relationships']['data']);
        
        $this->assertEquals($data['customerData']['data']['attributes']['name'], $responseData['data']['attributes']['name']);
        $this->assertEquals($data['customerData']['data']['attributes']['email'], $responseData['data']['attributes']['email']);
        
        $this->assertArrayHasKey('includes', $responseData);
        $this->assertEquals($data['customerData']['relationships']['user_id']['data']['attributes']['username'], $responseData['data']['relationships']['user_id']['data']['attributes']['username']);

        return ['userId' => $this->userId, 'token' => $data['token']];
    }

    /**
     * @depends testCreateCustomerWithUser
     */
    public function testListUsers(array $data)
    {
        $this->markTestSkipped('must be revisited.');
        $response = $this->client->request('GET', "apis/{$this->apiName}/data/users", [
            'headers' => [
                'Authorization' => 'Bearer ' . $data['token']
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $users = json_decode($response->getBody(), true);
        
        $this->assertIsArray($users);
        $this->assertGreaterThanOrEqual(1, count($users));
        
        // Check if our created user is in the list
        $foundUser = false;
        foreach ($users as $user) {
            if ($user['id'] === $data['userId']) {
                $foundUser = true;
                break;
            }
        }
        $this->assertTrue($foundUser, 'Created user not found in users list');
        
        return $data;
    }

    /**
     * @depends testCreateCustomerWithUser
     */
    public function testGetUserAssociatedWithCustomer(array $data)
    {
        $this->markTestSkipped('must be revisited.');
        $response = $this->client->request('GET', "apis/{$this->apiName}/data/customers/{$data['customerId']}/user", [
            'headers' => [
                'Authorization' => 'Bearer ' . $data['token']
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $userData = json_decode($response->getBody(), true);
        
        $this->assertEquals($data['userId'], $userData['id']);
        $this->assertEquals('John Doe', $userData['name']);
        $this->assertEquals('john@example.com', $userData['email']);
    }
}
