<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../application/libraries/ApiSafety.php';

class TestApiSafety extends TestCase
{
    protected function setUp(): void
    {
        ApiSafety::configure([
            'default_page_size' => 10,
            'max_page_size' => 100,
            'bulk_insert_limit' => 3,
            'bulk_update_limit' => 2,
        ]);
    }

    public function testClampPageLimit(): void
    {
        $this->assertSame(10, ApiSafety::clampPageLimit(0, 10));
        $this->assertSame(50, ApiSafety::clampPageLimit(50, 10));
        $this->assertSame(100, ApiSafety::clampPageLimit(500, 10));
    }

    public function testBulkInsertLimit(): void
    {
        ApiSafety::assertBulkInsertCount(3);
        $this->expectException(\dbAPI\API\Exception::class);
        ApiSafety::assertBulkInsertCount(4);
    }
}
