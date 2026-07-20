<?php

/**
 * Ensures local and Docker schema entry points load the shared body file.
 */
class TestDataplaneSchemaSync extends PHPUnit\Framework\TestCase
{
    private static function repoRoot(): string
    {
        $srcRoot = dirname(__DIR__);
        if (is_file($srcRoot . '/tests/dataplane-schema-body.sql')) {
            return $srcRoot;
        }

        $repoRoot = dirname($srcRoot);
        if (is_file($repoRoot . '/src/tests/dataplane-schema-body.sql')) {
            return $repoRoot;
        }

        return $repoRoot;
    }

    public function testSharedSchemaBodyExists(): void
    {
        $body = self::repoRoot() . '/tests/dataplane-schema-body.sql';
        if (!is_file($body)) {
            $body = self::repoRoot() . '/src/tests/dataplane-schema-body.sql';
        }
        $this->assertFileExists($body);
        $contents = file_get_contents($body);
        $this->assertStringContainsString('CREATE TABLE `filter_cases`', $contents);
        $this->assertStringContainsString("(20, 'negated'", $contents);
    }

    public function testLocalSqlSourcesSharedBody(): void
    {
        $local = self::repoRoot() . '/tests/dbapi_dataplane.sql';
        if (!is_file($local)) {
            $local = self::repoRoot() . '/src/tests/dbapi_dataplane.sql';
        }
        $this->assertFileExists($local);
        $this->assertStringContainsString(
            'dataplane-schema-body.sql',
            file_get_contents($local)
        );
    }

    public function testDockerInitLoadsSharedBody(): void
    {
        $init = self::repoRoot() . '/docker/mysql-init/001-load-demo-schema.sh';
        if (!is_file($init)) {
            $this->markTestSkipped('docker/mysql-init not available in this layout');
        }
        $this->assertStringContainsString(
            'dataplane-schema-body.sql',
            file_get_contents($init)
        );
        $this->assertFileDoesNotExist(
            self::repoRoot() . '/docker/mysql-init/001-demo-schema.sql',
            'Stale duplicate schema file must be removed'
        );
    }
}
