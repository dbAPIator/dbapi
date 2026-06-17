<?php

use PHPUnit\Framework\TestCase;

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('ENVIRONMENT', 'testing');

require_once APPPATH . 'helpers/deployment_helper.php';

class TestApiEnvironment extends TestCase
{
    public function testDefaultsToDevelopment(): void
    {
        $this->assertSame('development', api_environment([]));
        $this->assertFalse(api_is_production_environment([]));
    }

    public function testRecognizesProduction(): void
    {
        $meta = ['environment' => 'production'];
        $this->assertSame('production', api_environment($meta));
        $this->assertTrue(api_is_production_environment($meta));
    }

    public function testInvalidEnvironmentFallsBackToDevelopment(): void
    {
        $this->assertSame('development', api_environment(['environment' => 'staging']));
    }
}
