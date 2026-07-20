<?php

use PHPUnit\Framework\TestCase;

if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__) . '/system/');
}
if (!defined('APPPATH')) {
    define('APPPATH', dirname(__DIR__) . '/application/');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

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
