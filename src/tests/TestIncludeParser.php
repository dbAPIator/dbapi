<?php

use PHPUnit\Framework\TestCase;

if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__) . '/system/');
}
require_once __DIR__ . '/../application/helpers/dbapi_request_helper.php';

class TestIncludeParser extends TestCase
{
    public function testParseIncludeTreeMergesNestedPaths(): void
    {
        $tree = parse_include_tree('orders_created_by,orders_created_by.document_id');
        $this->assertSame(['orders_created_by' => ['document_id' => []]], $tree);
    }

    public function testFlattenIncludePaths(): void
    {
        $tree = [
            'orders' => [
                'order_lines' => [
                    'products' => [],
                ],
            ],
        ];
        $this->assertSame(
            ['orders', 'orders.order_lines', 'orders.order_lines.products'],
            flatten_include_paths($tree)
        );
    }

    public function testMergeIncludeForResource(): void
    {
        $inputs = ['include' => ['security_users/orders_created_by' => 'state']];
        merge_include_for_resource($inputs, 'security_users/orders_created_by', ['document_id']);
        $this->assertSame('state,document_id', $inputs['include']['security_users/orders_created_by']);
    }
}
