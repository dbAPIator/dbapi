<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

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
    private int $userId;
    private int $customerId;

    protected function setUp(): void
    {
        // Initialize Guzzle client before each test
        $this->client = new Client([
            'base_uri' => 'http://localhost/dbapi/', // Change this to your API base URL
            'timeout'  => 5.0,
        ]);
    }

    public function testCreateApiAndGetToken()
    {
        $response = $this->client->request('POST', 'apis', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken
            ],
            'json' => [
                "name"=>"example",
                "connection"=>[
                    "dbdriver"=>"mysqli",
                    "hostname"=> "localhost",
                    "username"=> "vsergiu",
                    "password"=> "parola123",
                    "database"=> "book_management"
                ],
                "create"=> [
                    "sql"=>"SET SQL_MODE = \\\"NO_AUTO_VALUE_ON_ZERO\\\";\\nSTART TRANSACTION;\\nSET time_zone = \\\"+00:00\\\";\\n\\n--\\n-- Database: `dbapiator_demo`\\n--\\n\\n-- --------------------------------------------------------\\n\\n--\\n-- Table structure for table `Customers`\\n--\\n\\nCREATE TABLE `Customers` (\\n  `id` int NOT NULL,\\n  `user_id` int DEFAULT NULL,\\n  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,\\n  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL\\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\\n\\n-- --------------------------------------------------------\\n\\n--\\n-- Table structure for table `OrderItems`\\n--\\n\\nCREATE TABLE `OrderItems` (\\n  `id` int NOT NULL,\\n  `order_id` int DEFAULT NULL,\\n  `product_id` int DEFAULT NULL,\\n  `quantity` int NOT NULL\\n) ;\\n\\n-- --------------------------------------------------------\\n\\n--\\n-- Table structure for table `Orders`\\n--\\n\\nCREATE TABLE `Orders` (\\n  `id` int NOT NULL,\\n  `customer_id` int DEFAULT NULL,\\n  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP\\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\\n\\n-- --------------------------------------------------------\\n\\n--\\n-- Table structure for table `Products`\\n--\\n\\nCREATE TABLE `Products` (\\n  `id` int NOT NULL,\\n  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,\\n  `price` decimal(10,2) NOT NULL\\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\\n\\n-- --------------------------------------------------------\\n\\n--\\n-- Table structure for table `Users`\\n--\\n\\nCREATE TABLE `Users` (\\n  `id` int NOT NULL,\\n  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,\\n  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,\\n  `role` enum('admin','customer') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'customer'\\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\\n\\n--\\n-- Indexes for dumped tables\\n--\\n\\n--\\n-- Indexes for table `Customers`\\n--\\nALTER TABLE `Customers`\\n  ADD PRIMARY KEY (`id`),\\n  ADD UNIQUE KEY `email` (`email`),\\n  ADD UNIQUE KEY `user_id` (`user_id`);\\n\\n--\\n-- Indexes for table `OrderItems`\\n--\\nALTER TABLE `OrderItems`\\n  ADD PRIMARY KEY (`id`),\\n  ADD KEY `order_id` (`order_id`),\\n  ADD KEY `product_id` (`product_id`);\\n\\n--\\n-- Indexes for table `Orders`\\n--\\nALTER TABLE `Orders`\\n  ADD PRIMARY KEY (`id`),\\n  ADD KEY `customer_id` (`customer_id`);\\n\\n--\\n-- Indexes for table `Products`\\n--\\nALTER TABLE `Products`\\n  ADD PRIMARY KEY (`id`);\\n\\n--\\n-- Indexes for table `Users`\\n--\\nALTER TABLE `Users`\\n  ADD PRIMARY KEY (`id`),\\n  ADD UNIQUE KEY `username` (`username`);\\n\\n--\\n-- AUTO_INCREMENT for dumped tables\\n--\\n\\n--\\n-- AUTO_INCREMENT for table `Customers`\\n--\\nALTER TABLE `Customers`\\n  MODIFY `id` int NOT NULL AUTO_INCREMENT;\\n\\n--\\n-- AUTO_INCREMENT for table `OrderItems`\\n--\\nALTER TABLE `OrderItems`\\n  MODIFY `id` int NOT NULL AUTO_INCREMENT;\\n\\n--\\n-- AUTO_INCREMENT for table `Orders`\\n--\\nALTER TABLE `Orders`\\n  MODIFY `id` int NOT NULL AUTO_INCREMENT;\\n\\n--\\n-- AUTO_INCREMENT for table `Products`\\n--\\nALTER TABLE `Products`\\n  MODIFY `id` int NOT NULL AUTO_INCREMENT;\\n\\n--\\n-- AUTO_INCREMENT for table `Users`\\n--\\nALTER TABLE `Users`\\n  MODIFY `id` int NOT NULL AUTO_INCREMENT;\\n\\n--\\n-- Constraints for dumped tables\\n--\\n\\n--\\n-- Constraints for table `Customers`\\n--\\nALTER TABLE `Customers`\\n  ADD CONSTRAINT `Customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`id`) ON DELETE CASCADE;\\n\\n--\\n-- Constraints for table `OrderItems`\\n--\\nALTER TABLE `OrderItems`\\n  ADD CONSTRAINT `OrderItems_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `Orders` (`id`) ON DELETE CASCADE,\\n  ADD CONSTRAINT `OrderItems_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `Products` (`id`) ON DELETE CASCADE;\\n\\n--\\n-- Constraints for table `Orders`\\n--\\nALTER TABLE `Orders`\\n  ADD CONSTRAINT `Orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `Customers` (`id`) ON DELETE CASCADE;\\nCOMMIT;\\n\"",
                    "drop_before_create"=> true
                ],
                "security"=>[
                    "config"=> [
                        "from"=> [
                            ["action"=>"allow", "ip"=>"127.0.0.1"],
                            ["action"=>"allow", "ip"=>"0.0.0.0/0"]
                        ]
                    ],
                    "api"=> [
                        "from"=> [
                            ["action"=>"allow", "ip"=>"127.0.0.1"],
                            ["action"=>"allow", "ip"=>"0.0.0.0/0"]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('result', $data);
        $this->configApiKey = $data['result'];
        
        // Store token for subsequent tests
        return $this->configApiKey;
    }

    public function testDeleteApi()
    {
        $response = $this->client->request('DELETE', 'apis/example', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->configApiKey
            ]
        ]);
        $this->assertEquals(204, $response->getStatusCode());
        return $this->configApiKey;
    }

    /**
     * @depends testCreateApiAndGetToken
     */
    public function testCreateSingleUser(string $token)
    {
        $this->markTestSkipped('must be revisited.');
        $response = $this->client->request('POST', 'apis/example/data/users', [
            'json' => [
                "data"=> [
                    "type"=> "Users",
                    "attributes"=> [
                        "username"=> "vsergione",
                        "password_hash"=> "parola123",
                        "role"=> "customer"
                    ]
                ],
                "jsonapi"=> "1.0"
            ]
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertArrayHasKey('attributes', $responseData['data']);
        $this->assertEquals('vsergione', $responseData['data']['attributes']['username']);
            
        $this->userId = $responseData['data']['id'];
        return ['userId' => $this->userId, 'token' => $token];
    }

    /**
     * @depends testCreateSingleUser
     */
    public function testGetUserById(array $data)
    {
        $this->markTestSkipped('must be revisited.');
        $response = $this->client->request('GET', "apis/example/data/users/{$data['userId']}", [
            'headers' => [
                
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);
        
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertArrayHasKey('attributes', $responseData['data']);
        $this->assertEquals('vsergione', $responseData['data']['attributes']['username']);
            
        $this->userId = $responseData['data']['id'];
        return ['userId' => $this->userId, 'token' => $data['token']];
    }

    /**
     * @depends testCreateSingleUser
     */
    public function testDeleteUserById(array $data)
    {
        $this->markTestSkipped('must be revisited.');
        $response = $this->client->request('DELETE', "apis/example/data/users/{$data['userId']}", [
            'headers' => [
                
            ]
        ]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getBody());
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
        $response = $this->client->request('POST', 'apis/example/data/customers', [
            'headers' => [
                //'Authorization' => 'Bearer ' . $data['token']
            ],
            'json' => $customerData
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getBody(), true);

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
        $response = $this->client->request('GET', "apis/example/data/users/{$data['customerId']}?include=user_id", [
            'headers' => [
                
            ]
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
        $response = $this->client->request('GET', 'apis/example/data/users', [
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
        $response = $this->client->request('GET', "customers/{$data['customerId']}/user", [
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
