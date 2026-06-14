<?php

/**
 * Data-plane integration tests for access control (table access, mandatoryFilter, path when).
 *
 * Requires dataplane DB (see docs/data_plane_test_plan.md).
 */
class TestAccessControlDataPlane extends IntegrationTestCase
{
    use AccessControlIntegrationTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAccessControlClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownAccessControlApi();
        parent::tearDown();
    }

    private function provisionTableAccessApi(): void
    {
        $this->provisionAccessControlApi([
            'pathRules' => [],
            'schemaOverrides' => $this->defaultSchemaOverrides(),
            'defaultAccessRule' => 'private',
            'filterBypassRoles' => ['admin'],
        ]);
    }

    public function testAnonymousCanListPublicProducts(): void
    {
        $this->provisionTableAccessApi();
        $result = $this->accessDataRequest('GET', $this->accessDataUrl('products'));
        $this->assertSame(200, $result['status']);
        $this->assertNotEmpty($result['body']['data'] ?? []);
    }

    public function testAnonymousCannotListPrivateOrders(): void
    {
        $this->provisionTableAccessApi();
        $result = $this->accessDataRequest('GET', $this->accessDataUrl('orders'));
        $this->assertSame(401, $result['status']);
    }

    public function testUserScopedCanGetOwnRecord(): void
    {
        $this->provisionTableAccessApi();
        $token = $this->accessLogin('testuser', 'testpass');
        $result = $this->accessDataRequest('GET', $this->accessDataUrl('users', '1'), $token);
        $this->assertSame(200, $result['status']);
        $this->assertSame('mgr-alice', $result['body']['data']['attributes']['username'] ?? null);
    }

    public function testUserScopedCannotGetOtherUser(): void
    {
        $this->provisionTableAccessApi();
        $token = $this->accessLogin('testuser', 'testpass');
        $result = $this->accessDataRequest('GET', $this->accessDataUrl('users', '2'), $token);
        $this->assertSame(401, $result['status']);
    }

    public function testMandatoryFilterLimitsOrdersToOwnCustomer(): void
    {
        $this->provisionTableAccessApi();
        $token = $this->accessLogin('testuser', 'testpass');
        $result = $this->accessDataRequest('GET', $this->accessDataUrl('orders'), $token);
        $this->assertSame(200, $result['status']);
        $ids = $this->extractOrderIds($result['body']);
        $this->assertEquals([1, 2, 4], $ids);
    }

    public function testMandatoryFilterOverridesClientFilterOnSameField(): void
    {
        $this->provisionTableAccessApi();
        $token = $this->accessLogin('testuser', 'testpass');
        $result = $this->accessDataRequest('GET', $this->accessDataUrl('orders'), $token, [
            'query' => ['filter' => ['orders' => 'customer_id=2']],
        ]);
        $this->assertSame(200, $result['status']);
        $ids = $this->extractOrderIds($result['body']);
        $this->assertNotContains(3, $ids, 'client filter must not expose other customer orders');
        $this->assertEquals([1, 2, 4], $ids);
    }

    public function testAdminBypassesMandatoryFilter(): void
    {
        $this->provisionTableAccessApi();
        $token = $this->accessLogin('admin', 'adminpass');
        $result = $this->accessDataRequest('GET', $this->accessDataUrl('orders'), $token);
        $this->assertSame(200, $result['status']);
        $ids = $this->extractOrderIds($result['body']);
        $this->assertContains(3, $ids);
        $this->assertGreaterThanOrEqual(4, count($ids));
    }

    public function testPatchOrderDeniedForOtherCustomersRow(): void
    {
        $this->provisionTableAccessApi();
        $token = $this->accessLogin('testuser', 'testpass');
        $result = $this->accessDataRequest('PATCH', $this->accessDataUrl('orders', '3'), $token, [
            'json' => [
                'data' => [
                    'type' => 'orders',
                    'id' => '3',
                    'attributes' => ['status' => 'shipped'],
                ],
            ],
        ]);
        $this->assertContains($result['status'], [404, 401], 'update other customer order');
    }

    public function testPathRuleWhenAllowsAdminOnlyOnGlobalList(): void
    {
        $this->provisionAccessControlApi([
            'pathRules' => [
                ['pattern' => '/*', 'methods' => '*', 'allow' => true, 'when' => ['role' => 'admin']],
                ['pattern' => '/products', 'methods' => 'GET', 'allow' => true],
                ['pattern' => '/products/*', 'methods' => 'GET', 'allow' => true],
                ['pattern' => '/*', 'methods' => '*', 'allow' => false],
            ],
            'schemaOverrides' => ['products' => ['access' => 'public']],
            'defaultAccessRule' => 'private',
        ]);

        $userToken = $this->accessLogin('testuser', 'testpass');
        $denied = $this->accessDataRequest('GET', $this->accessDataUrl('customers'), $userToken);
        $this->assertSame(401, $denied['status']);

        $adminToken = $this->accessLogin('admin', 'adminpass');
        $allowed = $this->accessDataRequest('GET', $this->accessDataUrl('customers'), $adminToken);
        $this->assertSame(200, $allowed['status']);
    }

    public function testAnonymousPostUsersAllowedByPathRule(): void
    {
        $this->provisionAccessControlApi([
            'pathRules' => [
                ['pattern' => '/users', 'methods' => 'POST', 'allow' => true],
                ['pattern' => '/users', 'methods' => 'GET', 'allow' => false],
                ['pattern' => '/users/*', 'methods' => 'GET', 'allow' => false],
                ['pattern' => '/*', 'methods' => '*', 'allow' => false],
            ],
            'schemaOverrides' => [
                'users' => ['access' => 'scoped', 'scopePattern' => '/users/{{userId}}'],
            ],
            'defaultAccessRule' => 'private',
        ]);

        $post = $this->accessDataRequest('POST', $this->accessDataUrl('users'), null, [
            'json' => [
                'data' => [
                    'type' => 'users',
                    'attributes' => [
                        'username' => 'reg-' . bin2hex(random_bytes(3)),
                        'full_name' => 'Registered User',
                    ],
                ],
            ],
        ]);
        $this->assertContains($post['status'], [200, 201], 'anonymous registration POST');

        $list = $this->accessDataRequest('GET', $this->accessDataUrl('users'));
        $this->assertSame(401, $list['status']);
    }
}
