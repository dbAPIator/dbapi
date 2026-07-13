<?php

use PHPUnit\Framework\TestCase;
use dbAPI\API\DBAPIRequest;

use function dbAPI\API\recursive_generate_select_and_joins;

if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__) . '/system/');
}
if (!defined('APPPATH')) {
    define('APPPATH', dirname(__DIR__) . '/application/');
}

require_once APPPATH . 'helpers/config_util_helper.php';
require_once APPPATH . 'third_party/dbAPI/API/Records.php';

class TestSelectOffset extends TestCase
{
    private function outboundRel(string $table, string $field): array
    {
        return ['table' => $table, 'field' => $field, 'type' => 'outbound'];
    }

    private function buildOrdersIncludeTree(): DBAPIRequest
    {
        $address = new DBAPIRequest('addresses', 10);
        $address->relSpec = $this->outboundRel('addresses', 'id');
        $address->relName = 'default_shipping_address_id';
        $address->add_field('id');
        $address->add_field('address1');
        $address->add_field('city');
        $address->add_field('zip');

        $customer = new DBAPIRequest('partners', 10);
        $customer->relSpec = $this->outboundRel('partners', 'id');
        $customer->relName = 'customer_id';
        $customer->add_field('id');
        $customer->add_field('name');
        $customer->add_field('default_shipping_address_id');
        $customer->include['default_shipping_address_id'] = $address;

        $document = new DBAPIRequest('documents', 10);
        $document->relSpec = $this->outboundRel('documents', 'id');
        $document->relName = 'document_id';
        $document->add_field('id');
        $document->add_field('doc_num');

        $state = new DBAPIRequest('config_order_states', 10);
        $state->relSpec = $this->outboundRel('config_order_states', 'state');
        $state->relName = 'state';
        $state->add_field('state');
        $state->add_field('label');

        $orders = new DBAPIRequest('orders', 10);
        $orders->add_field('id');
        $orders->add_field('state');
        $orders->add_field('document_id');
        $orders->add_field('customer_id');
        $orders->include['customer_id'] = $customer;
        $orders->include['document_id'] = $document;
        $orders->include['state'] = $state;

        return $orders;
    }

    public function testNestedIncludeSelectOffsetsForSiblingRelations(): void
    {
        $orders = $this->buildOrdersIncludeTree();
        $fields = [];
        $join = [];
        recursive_generate_select_and_joins($orders, $fields, $join, $orders->resourceName);

        $this->assertSame(0, $orders->selectFieldsOffset);
        $this->assertSame(4, $orders->include['customer_id']->selectFieldsOffset);
        $this->assertSame(
            7,
            $orders->include['customer_id']->include['default_shipping_address_id']->selectFieldsOffset
        );
        $this->assertSame(11, $orders->include['document_id']->selectFieldsOffset);
        $this->assertSame(13, $orders->include['state']->selectFieldsOffset);
        $this->assertCount(15, $fields);
    }
}
