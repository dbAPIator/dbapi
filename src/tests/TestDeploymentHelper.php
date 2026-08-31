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

class TestDeploymentHelper extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DEPLOYMENT_MODE');
        putenv('DB_HOST');
        putenv('DB_NAME');
        putenv('DB_USER');
        putenv('DB_PASSWORD');
        putenv('DB_PORT');
        putenv('CONFIG_API_SECRET');
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
        $this->assertSame('default', default_api_id());
        $this->assertSame(DBAPI_DEFAULT_API_ID, default_api_id());
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

    public function testApplySingleModeConnectionEnvOverlaysDbVars(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        putenv('DB_HOST=mysql');
        putenv('DB_NAME=appdb');
        putenv('DB_USER=app');
        putenv('DB_PASSWORD=s3cret');
        putenv('DB_PORT=3306');

        $out = apply_single_mode_connection_env([
            'dbdriver' => 'mysqli',
            'hostname' => 'localhost:3306',
            'username' => 'placeholder',
            'password' => 'placeholder',
            'database' => 'placeholder',
        ]);
        $this->assertSame('mysql:3306', $out['hostname']);
        $this->assertSame('app', $out['username']);
        $this->assertSame('s3cret', $out['password']);
        $this->assertSame('appdb', $out['database']);
    }

    public function testApplySingleModeConnectionEnvIgnoredInMultiMode(): void
    {
        putenv('DEPLOYMENT_MODE=multi');
        putenv('DB_HOST=mysql');
        putenv('DB_NAME=appdb');
        $disk = [
            'dbdriver' => 'mysqli',
            'hostname' => 'localhost:3306',
            'username' => 'on-disk',
            'password' => 'on-disk',
            'database' => 'on-disk',
        ];
        $this->assertSame($disk, apply_single_mode_connection_env($disk));
    }

    public function testApplySingleModeAdminConfigOverlaysSecret(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        putenv('CONFIG_API_SECRET=from-env');
        $admin = apply_single_mode_admin_config([
            'acls' => [['ip' => '0.0.0.0/0', 'allow' => true]],
            'secret' => 'on-disk',
        ]);
        $this->assertSame('from-env', $admin['secret']);
    }

    public function testMgmtApiPathSingleModeOmitsDefaultApiId(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        $this->assertSame('/mgmt/v1', mgmt_api_path());
        $this->assertSame('/mgmt/v1/connection', mgmt_api_path('connection'));
        $this->assertSame('/mgmt/v1:activate', mgmt_api_path(':activate'));
        $this->assertSame('/mgmt/v1/schema:sync', mgmt_api_path('schema:sync'));
    }

    public function testMgmtApiPathMultiModeIncludesApiId(): void
    {
        putenv('DEPLOYMENT_MODE');
        $this->assertSame('/mgmt/v1/apis/demo', mgmt_api_path('', 'demo'));
        $this->assertSame('/mgmt/v1/apis/demo/connection', mgmt_api_path('connection', 'demo'));
        $this->assertSame('/mgmt/v1/apis/demo:activate', mgmt_api_path(':activate', 'demo'));
    }

    public function testMgmtOpenapiCanonicalPathMapsSingleModeAliases(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        $this->assertSame('/mgmt/v1', mgmt_openapi_canonical_path('/mgmt/v1'));
        $this->assertSame('/mgmt/v1/connection', mgmt_openapi_canonical_path('/mgmt/v1/connection'));
        $this->assertSame('/mgmt/v1:activate', mgmt_openapi_canonical_path('/mgmt/v1:activate'));
        $this->assertSame('/mgmt/v1/connection', mgmt_openapi_canonical_path('/mgmt/v1/apis/default/connection'));
        $this->assertSame('/mgmt/v1/apis', mgmt_openapi_canonical_path('/mgmt/v1/apis'));
    }

    public function testSingleModeMetaFromEnv(): void
    {
        putenv('API_TITLE=My API');
        putenv('API_DESCRIPTION=Demo instance');
        putenv('API_VERSION=2.0.0');
        putenv('API_CONTACT_NAME=Ops');
        putenv('API_CONTACT_EMAIL=ops@example.com');

        $meta = single_mode_meta_from_env('default');
        $this->assertSame('default', $meta['name']);
        $this->assertSame('My API', $meta['title']);
        $this->assertSame('Demo instance', $meta['description']);
        $this->assertSame('2.0.0', $meta['version']);
        $this->assertSame('Ops', $meta['contact']['name']);
        $this->assertSame('ops@example.com', $meta['contact']['email']);

        putenv('API_TITLE');
        putenv('API_DESCRIPTION');
        putenv('API_VERSION');
        putenv('API_CONTACT_NAME');
        putenv('API_CONTACT_EMAIL');
    }
}
