<?php

use PHPUnit\Framework\TestCase;

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('ENVIRONMENT', 'testing');

require_once APPPATH . 'helpers/deployment_helper.php';

class TestDeploymentHelper extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DEPLOYMENT_MODE');
        putenv('DEFAULT_API_ID');
        putenv('DB_HOST');
        putenv('DB_NAME');
    }

    public function testDefaultDeploymentModeIsMulti(): void
    {
        putenv('DEPLOYMENT_MODE');
        $this->assertSame('multi', deployment_mode());
        $this->assertFalse(is_single_deployment_mode());
    }

    public function testSingleDeploymentMode(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        $this->assertSame('single', deployment_mode());
        $this->assertTrue(is_single_deployment_mode());
    }

    public function testDefaultApiId(): void
    {
        putenv('DEFAULT_API_ID');
        $this->assertSame('default', default_api_id());
        putenv('DEFAULT_API_ID=custom');
        $this->assertSame('custom', default_api_id());
    }

    public function testSingleModeConnectionFromEnv(): void
    {
        putenv('DB_HOST=mysql');
        putenv('DB_NAME=myapp');
        putenv('DB_USER=user');
        putenv('DB_PASSWORD=secret');
        putenv('DB_PORT=3307');

        $conn = single_mode_connection_from_env();
        $this->assertIsArray($conn);
        $this->assertSame('mysql', $conn['driver']);
        $this->assertSame('mysql', $conn['host']);
        $this->assertSame(3307, $conn['port']);
        $this->assertSame('myapp', $conn['database']);
        $this->assertSame('user', $conn['username']);
        $this->assertSame('secret', $conn['password']);
    }

    public function testSingleModeConnectionFromEnvRequiresHostAndDatabase(): void
    {
        putenv('DB_HOST');
        putenv('DB_NAME');
        $this->assertNull(single_mode_connection_from_env());
    }
}
