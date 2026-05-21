<?php

/**
 * Data plane (/v1/apis/{apiId}/data/...) integration tests.
 *
 * Replaces src/test_data_plane.sh
 *
 * @see docs/data_plane_test_plan.md
 */
class TestDataPlaneAPI extends DataPlaneTestCase
{
    // --- Read / list ---

    public function testListCustomersReturnsJsonApiCollection(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers'));
        $body = $this->assertHttpStatus($resp, 200, 'list customers');
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
        $this->assertGreaterThanOrEqual(3, count($body['data']));
        $this->assertArrayHasKey('meta', $body);
    }

    public function testGetCustomerById(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers', '1'));
        $body = $this->assertHttpStatus($resp, 200, 'get customer 1');
        $this->assertEquals('customers', $body['data']['type']);
        $this->assertEquals('alice@example.com', $body['data']['attributes']['email']);
    }

    public function testGetNonexistentCustomerReturns404(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers', '999999'));
        $this->assertHttpStatus($resp, 404, 'missing customer');
    }

    public function testUnknownResourceReturns404(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('no_such_table'));
        $this->assertHttpStatus($resp, 404, 'unknown resource');
    }

    public function testSparseFieldset(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers', '1'), [
            'query' => ['fields' => ['customers' => 'name,email']],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'sparse fields');
        $attrs = $body['data']['attributes'];
        $this->assertArrayHasKey('name', $attrs);
        $this->assertArrayHasKey('email', $attrs);
        $this->assertArrayNotHasKey('country_code', $attrs);
    }

    public function testReadOnlyView(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('v_order_totals_by_day'));
        $body = $this->assertHttpStatus($resp, 200, 'list view');
        $this->assertArrayHasKey('data', $body);
        $this->assertNotEmpty($body['data']);
        $first = $body['data'][0];
        $this->assertArrayNotHasKey('id', $first, 'resources without a primary key omit id');
        $this->assertEquals('v_order_totals_by_day', $first['type']);
        $this->assertArrayHasKey('attributes', $first);
        $this->assertArrayHasKey('day', $first['attributes']);
    }

    public function testGetViewByIdWithoutPrimaryKeyReturns404(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('v_order_totals_by_day', 'abc123'));
        $this->assertHttpStatus($resp, 404, 'get view by id');
    }

    // --- Filters (filter_cases seed IDs 1-6) ---

    public function testFilterEquality(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('filter_cases'), [
            'query' => ['filter' => 'id=1'],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'filter eq');
        $this->assertCount(1, $body['data']);
        $this->assertEquals('alpha-low', $body['data'][0]['attributes']['label']);
    }

    /** @see test_data_plane.sh — filter=score>40 */
    public function testFilterScoreGreaterThan(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('filter_cases'), [
            'query' => ['filter' => 'score>40'],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'filter gt');
        $this->assertNotEmpty($body['data']);
        foreach ($body['data'] as $row) {
            $this->assertGreaterThan(40, (int) $row['attributes']['score']);
        }
    }

    public function testFilterGreaterThanAndSort(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('filter_cases'), [
            'query' => [
                'filter' => ['filter_cases' => 'score>40'],
                'sort' => ['filter_cases' => '-score'],
            ],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'filter gt + sort');
        $labels = array_column(array_column($body['data'], 'attributes'), 'label');
        $this->assertContains('alpha-high', $labels);
        $this->assertContains('epsilon-list', $labels);
        if (count($labels) >= 2) {
            $this->assertGreaterThanOrEqual(
                (int) $body['data'][1]['attributes']['score'],
                (int) $body['data'][0]['attributes']['score']
            );
        }
    }

    public function testFilterOneOf(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('filter_cases'), [
            'query' => ['filter' => 'country><US;DE'],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'filter in-list');
        foreach ($body['data'] as $row) {
            $this->assertContains($row['attributes']['country'], ['US', 'DE']);
        }
    }

    public function testFilterOrGrouping(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('filter_cases'), [
            'query' => ['filter' => '(status=closed||status=pending)'],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'filter or');
        $this->assertGreaterThanOrEqual(2, count($body['data']));
    }

    public function testFilterNoMatchReturnsEmptyData(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('filter_cases'), [
            'query' => ['filter' => 'label=nonexistent-xyz'],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'filter empty');
        $this->assertCount(0, $body['data']);
    }

    public function testInvalidFilterReturns400(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('filter_cases'), [
            'query' => ['filter' => '(((unclosed'],
        ]);
        $this->assertContains($resp->getStatusCode(), [400, 422], 'invalid filter');
    }

    // --- Pagination ---

    public function testPaginationLimitOffset(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers'), [
            'query' => ['page' => ['customers' => ['limit' => 1, 'offset' => 1]]],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'pagination');
        $this->assertCount(1, $body['data']);
    }

    // --- Relationships ---

    public function testIncludeOrdersOnCustomer(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers', '1'), [
            'query' => ['include' => 'orders'],
        ]);
        $body = $this->assertHttpStatus($resp, 200, 'include orders');
        $this->assertArrayHasKey('relationships', $body['data']);
        $this->assertArrayHasKey('orders', $body['data']['relationships']);
        if (isset($body['included'])) {
            $this->assertNotEmpty($body['included']);
        }
    }

    public function testRelatedRecordsEndpoint(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers', '1', 'orders'));
        $body = $this->assertHttpStatus($resp, 200, 'customer orders');
        $this->assertArrayHasKey('data', $body);
        $this->assertGreaterThanOrEqual(1, count($body['data']));
        $orderIds = array_map('strval', array_column($body['data'], 'id'));
        $this->assertNotEmpty(array_intersect($orderIds, ['1', '2', '4']));
    }

    // --- Create / update / delete ---

    public function testCreateAndDeleteCustomer(): void
    {
        $email = $this->uniqueEmail('create');
        $create = $this->dataRequest('POST', $this->dataUrl('customers'), [
            'json' => [
                'data' => [
                    'type' => 'customers',
                    'attributes' => [
                        'name' => 'PHPUnit Create',
                        'email' => $email,
                        'country_code' => 'US',
                    ],
                ],
            ],
        ]);
        $created = $this->assertHttpStatus($create, 201, 'create customer');
        $id = $created['data']['id'];

        $patch = $this->dataRequest('PATCH', $this->dataUrl('customers', $id), [
            'json' => [
                'data' => [
                    'type' => 'customers',
                    'id' => $id,
                    'attributes' => ['name' => 'PHPUnit Updated'],
                ],
            ],
        ]);
        $updated = $this->assertHttpStatus($patch, 200, 'patch customer');
        $this->assertEquals('PHPUnit Updated', $updated['data']['attributes']['name']);

        $del = $this->dataRequest('DELETE', $this->dataUrl('customers', $id));
        $this->assertContains($del->getStatusCode(), [200, 204], 'delete customer');
    }

    public function testDuplicateEmailReturns409(): void
    {
        $resp = $this->dataRequest('POST', $this->dataUrl('customers'), [
            'json' => [
                'data' => [
                    'type' => 'customers',
                    'attributes' => [
                        'name' => 'Dup',
                        'email' => 'alice@example.com',
                        'country_code' => 'US',
                    ],
                ],
            ],
        ]);
        $this->assertEquals(409, $resp->getStatusCode(), 'duplicate email');
    }

    public function testOnDuplicateIgnoreAllowsSecondInsert(): void
    {
        $sku = 'DUP-' . bin2hex(random_bytes(3));
        $payload = [
            'data' => [
                'type' => 'products',
                'attributes' => [
                    'sku' => $sku,
                    'name' => 'First',
                    'price' => 1.00,
                    'is_active' => 1,
                ],
            ],
        ];
        $first = $this->dataRequest('POST', $this->dataUrl('products') . '?onduplicate=ignore', [
            'json' => $payload,
        ]);
        $this->assertHttpStatus($first, 201, 'first product');

        $payload['data']['attributes']['name'] = 'Second';
        $second = $this->dataRequest('POST', $this->dataUrl('products') . '?onduplicate=ignore', [
            'json' => $payload,
        ]);
        $this->assertContains($second->getStatusCode(), [200, 201], 'ignore duplicate');
    }

    public function testInvalidForeignKeyOnOrderReturnsError(): void
    {
        $resp = $this->dataRequest('POST', $this->dataUrl('orders'), [
            'json' => [
                'data' => [
                    'type' => 'orders',
                    'attributes' => [
                        'customer_id' => 999999,
                        'status' => 'draft',
                        'total' => 1.00,
                    ],
                ],
            ],
        ]);
        $this->assertGreaterThanOrEqual(400, $resp->getStatusCode());
        $this->assertLessThan(600, $resp->getStatusCode());
    }

    public function testDeleteCustomerWithOrdersFails(): void
    {
        $resp = $this->dataRequest('DELETE', $this->dataUrl('customers', '1'));
        $this->assertGreaterThanOrEqual(400, $resp->getStatusCode());
    }

    public function testBulkPatchWithoutFilterReturns400(): void
    {
        $resp = $this->dataRequest('PATCH', $this->dataUrl('customers'), [
            'json' => [
                'data' => [
                    'type' => 'customers',
                    'attributes' => ['name' => 'Bulk'],
                ],
            ],
        ]);
        $this->assertEquals(400, $resp->getStatusCode(), 'bulk patch without filter');
    }

    public function testEmptyPostBodyReturns400(): void
    {
        $resp = $this->dataRequest('POST', $this->dataUrl('customers'), [
            'body' => '',
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertEquals(400, $resp->getStatusCode(), 'empty body');
    }

    public function testPostToViewFails(): void
    {
        $resp = $this->dataRequest('POST', $this->dataUrl('v_order_totals_by_day'), [
            'json' => [
                'data' => [
                    'type' => 'v_order_totals_by_day',
                    'attributes' => ['day' => '2026-01-01', 'order_count' => 1, 'revenue' => 0],
                ],
            ],
        ]);
        $this->assertGreaterThanOrEqual(400, $resp->getStatusCode());
    }

    public function testRequestIdHeaderPresent(): void
    {
        $resp = $this->dataRequest('GET', $this->dataUrl('customers', '1'), [
            'headers' => ['X-Request-Id' => 'phpunit-req-1'],
        ]);
        $this->assertHttpStatus($resp, 200);
        $this->assertEquals('phpunit-req-1', $resp->getHeaderLine('X-Request-Id'));
    }
}
