<?php

/**
 * Ensures local and Docker schema entry points load the shared body file.
 */
class TestDataplaneSchemaSync extends PHPUnit\Framework\TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testSharedSchemaBodyExists(): void
    {
        $body = self::repoRoot() . '/src/tests/dataplane-schema-body.sql';
        $this->assertFileExists($body);
        $contents = file_get_contents($body);
        $this->assertStringContainsString('CREATE TABLE `filter_cases`', $contents);
        $this->assertStringContainsString("(20, 'negated'", $contents);
    }

    public function testLocalSqlSourcesSharedBody(): void
    {
        $local = self::repoRoot() . '/src/tests/dbapi_dataplane.sql';
        $this->assertFileExists($local);
        $this->assertStringContainsString(
            'dataplane-schema-body.sql',
            file_get_contents($local)
        );
    }

    public function testDockerInitLoadsSharedBody(): void
    {
        $init = self::repoRoot() . '/docker/mysql-init/001-load-demo-schema.sh';
        $this->assertFileExists($init);
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
