<?php

use PHPUnit\Framework\TestCase;

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('ENVIRONMENT', 'testing');

require_once APPPATH . 'helpers/swagger_helper.php';
require_once APPPATH . 'third_party/dbAPI/Autoloader.php';
\dbAPI\Autoloader::register();

class TestSwaggerGenerator extends TestCase
{
    private static function sampleStructure(): array
    {
        return [
            'customers' => [
                'type' => 'table',
                'keyFld' => 'id',
                'fields' => [
                    'id' => [
                        'type' => ['proto' => 'int'],
                        'required' => false,
                        'select' => true,
                    ],
                    'name' => [
                        'type' => ['proto' => 'varchar', 'length' => '100'],
                        'required' => true,
                        'select' => true,
                    ],
                ],
                'relations' => [
                    'orders' => [
                        'type' => 'inbound',
                        'table' => 'orders',
                        'select' => true,
                    ],
                ],
            ],
            'orders' => [
                'type' => 'table',
                'keyFld' => 'id',
                'fields' => [
                    'id' => [
                        'type' => ['proto' => 'int'],
                        'required' => false,
                        'select' => true,
                    ],
                    'customer_id' => [
                        'type' => ['proto' => 'int'],
                        'required' => true,
                        'select' => true,
                    ],
                ],
                'relations' => [],
            ],
        ];
    }

    public function testApiOpenapiDataUrlUsesV1Path(): void
    {
        putenv('DEPLOYMENT_MODE');
        $this->assertSame(
            'http://localhost/v1/apis/demo/data',
            api_openapi_data_url('http://localhost', 'demo')
        );
        $this->assertSame(
            'http://localhost/v1/apis/my%20api/data',
            api_openapi_data_url('http://localhost/', 'my api')
        );
    }

    public function testApiOpenapiDataUrlUsesSingleModePath(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        $this->assertSame(
            'http://localhost/v1/data',
            api_openapi_data_url('http://localhost', 'default')
        );
        putenv('DEPLOYMENT_MODE');
    }

    public function testWithApiOpenapiServersUrlRewritesCachedServer(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        $spec = [
            'openapi' => '3.0.2',
            'servers' => [['url' => 'http://172.18.0.3/v1/data']],
            'paths' => [],
        ];
        $out = with_api_openapi_servers_url($spec, 'default', 'http://localhost:8888');
        $this->assertSame('http://localhost:8888/v1/data', $out['servers'][0]['url']);
        putenv('DEPLOYMENT_MODE');
    }

    public function testWithMgmtOpenapiServersUrlRewritesCachedServer(): void
    {
        $spec = [
            'openapi' => '3.1.0',
            'servers' => [['url' => 'http://localhost/dbapi/src']],
            'paths' => [],
        ];
        $out = with_mgmt_openapi_servers_url($spec, 'http://api.example.com');
        $this->assertSame('http://api.example.com', $out['servers'][0]['url']);
    }

    public function testMgmtOpenapiYamlWithServersPatchesUrl(): void
    {
        $yaml = mgmt_openapi_yaml_with_servers('http://api.example.com');
        $this->assertStringContainsString('http://api.example.com', $yaml);
        $this->assertStringNotContainsString('http://localhost/dbapi/src', $yaml);
    }

    public function testWithMgmtOpenapiSingleModePathsRewritesUrls(): void
    {
        putenv('DEPLOYMENT_MODE=single');
        $spec = [
            'openapi' => '3.1.0',
            'paths' => [
                '/mgmt/v1/apis' => ['get' => ['operationId' => 'listApis']],
                '/mgmt/v1/apis/{apiId}' => [
                    'parameters' => [['$ref' => '#/components/parameters/ApiId']],
                    'get' => ['operationId' => 'getApi'],
                ],
                '/mgmt/v1/apis/{apiId}/connection' => [
                    'parameters' => [['$ref' => '#/components/parameters/ApiId']],
                    'get' => ['operationId' => 'getConnection'],
                ],
                '/mgmt/v1/apis/{apiId}:activate' => [
                    'parameters' => [['$ref' => '#/components/parameters/ApiId']],
                    'post' => ['operationId' => 'activateApi'],
                ],
            ],
        ];
        $out = with_mgmt_openapi_single_mode_paths($spec);
        $this->assertArrayHasKey('/mgmt/v1/apis', $out['paths']);
        $this->assertArrayHasKey('/mgmt/v1', $out['paths']);
        $this->assertArrayHasKey('/mgmt/v1/connection', $out['paths']);
        $this->assertArrayHasKey('/mgmt/v1:activate', $out['paths']);
        $this->assertArrayNotHasKey('/mgmt/v1/apis/{apiId}', $out['paths']);
        $this->assertArrayNotHasKey('parameters', $out['paths']['/mgmt/v1']);
        putenv('DEPLOYMENT_MODE');
    }

    public function testApiPublicBaseUrlPrefersBaseUrlEnv(): void
    {
        require_once APPPATH . 'helpers/deployment_helper.php';
        putenv('BASE_URL=http://api.example.com');
        $this->assertSame('http://api.example.com', api_public_base_url());
        putenv('BASE_URL');
    }

    public function testGenerateSwaggerProducesValidSpec(): void
    {
        $structure = self::sampleStructure();
        $dm = \dbAPI\API\Datamodel::init($structure);
        $spec = generate_swagger(
            api_openapi_data_url('http://localhost', 'demo'),
            $dm->get_dataModel(),
            'demo Spec',
            'demo spec',
            'demo',
            'test@example.com'
        );

        $validation = validate_data_api_openapi_spec($spec);
        $this->assertTrue($validation['valid'], implode('; ', $validation['errors']));

        $this->assertSame('http://localhost/v1/apis/demo/data', $spec['servers'][0]['url']);

        $this->assertArrayHasKey('/customers', $spec['paths']);
        $this->assertArrayHasKey('/orders', $spec['paths']);
        $this->assertArrayHasKey('/customers/{customers_id}', $spec['paths']);
        $this->assertArrayHasKey('/customers/{customers_id}/orders', $spec['paths']);
        $this->assertArrayHasKey('/customers/{customers_id}/orders/{orders_id}', $spec['paths']);
        $this->assertArrayHasKey('/orders/{orders_id}', $spec['paths']);

        $operations = 0;
        foreach ($spec['paths'] as $pathItem) {
            foreach (['get', 'post', 'patch', 'delete'] as $method) {
                if (isset($pathItem[$method])) {
                    $operations++;
                }
            }
        }
        $this->assertSame(6, count($spec['paths']));
        $this->assertSame(15, $operations);
    }

    public function testApiStructureForOpenapiMergesPatch(): void
    {
        $dir = sys_get_temp_dir() . '/dbapi-swagger-test-' . bin2hex(random_bytes(4));
        $this->assertTrue(mkdir($dir));

        try {
            file_put_contents($dir . '/structure.php', '<?php return ' . var_export([
                'widgets' => [
                    'type' => 'table',
                    'keyFld' => 'id',
                    'fields' => [
                        'id' => ['type' => ['proto' => 'int'], 'required' => false, 'select' => true],
                        'label' => ['type' => ['proto' => 'varchar'], 'required' => true, 'select' => true],
                    ],
                    'relations' => [],
                ],
            ], true) . ';');

            file_put_contents($dir . '/patch.php', '<?php return ' . var_export([
                'widgets' => [
                    'fields' => [
                        'label' => ['select' => false],
                    ],
                ],
            ], true) . ';');

            $structure = api_structure_for_openapi($dir);
            $this->assertFalse($structure['widgets']['fields']['label']['select']);
        } finally {
            @unlink($dir . '/structure.php');
            @unlink($dir . '/patch.php');
            @rmdir($dir);
        }
    }

    public function testApiStructureForOpenapiMergesHiddenFields(): void
    {
        $dir = sys_get_temp_dir() . '/dbapi-swagger-test-' . bin2hex(random_bytes(4));
        $this->assertTrue(mkdir($dir));

        try {
            file_put_contents($dir . '/structure.php', '<?php return ' . var_export([
                'users' => [
                    'type' => 'table',
                    'keyFld' => 'id',
                    'fields' => [
                        'id' => ['type' => ['proto' => 'int'], 'required' => false, 'select' => true],
                        'password' => ['type' => ['proto' => 'varchar'], 'required' => true, 'select' => true],
                    ],
                    'relations' => [],
                ],
            ], true) . ';');

            file_put_contents($dir . '/patch.php', '<?php return ' . var_export([
                'hiddenFields' => [
                    'users' => ['password'],
                ],
            ], true) . ';');

            $structure = api_structure_for_openapi($dir);
            $this->assertFalse($structure['users']['fields']['password']['select']);
        } finally {
            @unlink($dir . '/structure.php');
            @unlink($dir . '/patch.php');
            @rmdir($dir);
        }
    }
}
