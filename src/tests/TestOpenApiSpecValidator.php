<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../application/libraries/OpenApiSpecValidator.php';

class TestOpenApiSpecValidator extends TestCase
{
    public function testValidMinimalSpec(): void
    {
        $result = OpenApiSpecValidator::validate([
            'openapi' => '3.0.2',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'servers' => [['url' => 'http://localhost/apis/demo/data']],
            'paths' => [
                '/widgets' => [
                    'get' => ['responses' => ['200' => ['description' => 'ok']]],
                ],
            ],
            'components' => ['schemas' => ['widget' => ['type' => 'object']]],
        ]);
        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }

    public function testRejectsEmptyPaths(): void
    {
        $result = OpenApiSpecValidator::validate([
            'openapi' => '3.0.2',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
        ]);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}
