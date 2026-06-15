<?php

use PHPUnit\Framework\TestCase;

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('ENVIRONMENT', 'testing');

require_once APPPATH . 'helpers/config_util_helper.php';

class TestSchemaPatchOverrides extends TestCase
{
    public function testHiddenFieldsSetsSelectFalse(): void
    {
        $merged = schema_patch_apply_overrides([
            'hiddenFields' => [
                'app_users' => ['password', 'pin'],
            ],
        ]);

        $this->assertArrayNotHasKey('hiddenFields', $merged);
        $this->assertFalse($merged['app_users']['fields']['password']['select']);
        $this->assertFalse($merged['app_users']['fields']['pin']['select']);
    }

    public function testHiddenEntitiesSetsReadFalse(): void
    {
        $merged = schema_patch_apply_overrides([
            'hiddenEntities' => ['internal_audit'],
        ]);

        $this->assertArrayNotHasKey('hiddenEntities', $merged);
        $this->assertFalse($merged['internal_audit']['read']);
    }

    public function testPreservesEntityOverridesAlongsideHiddenFields(): void
    {
        $merged = schema_patch_apply_overrides([
            'hiddenFields' => ['app_users' => ['password']],
            'products' => ['access' => 'public'],
        ]);

        $this->assertSame('public', $merged['products']['access']);
        $this->assertFalse($merged['app_users']['fields']['password']['select']);
    }

    public function testExplicitSelectFalseOverridesHiddenFieldsMerge(): void
    {
        $merged = schema_patch_apply_overrides([
            'hiddenFields' => ['app_users' => ['password']],
            'app_users' => [
                'fields' => [
                    'password' => ['select' => true, 'insert' => false],
                ],
            ],
        ]);

        $this->assertTrue($merged['app_users']['fields']['password']['select']);
        $this->assertFalse($merged['app_users']['fields']['password']['insert']);
    }

    public function testPassthroughWhenNoHiddenKeys(): void
    {
        $patch = ['customers' => ['access' => 'scoped']];
        $this->assertSame($patch, schema_patch_apply_overrides($patch));
    }
}
