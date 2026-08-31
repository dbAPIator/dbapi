<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (! defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../application/helpers/csv_import_helper.php';

/**
 * Lightweight stand-in for Datamodel field checks used by CSV import.
 */
final class FakeCsvDatamodel
{
    /** @param array<string,array{insert?:bool}> $fields */
    public function __construct(private array $fields)
    {
    }

    public function get_fields(string $resourceName): array
    {
        return $this->fields;
    }

    public function field_is_insertable(string $resName, string $fldName): bool
    {
        return (bool) ($this->fields[$fldName]['insert'] ?? true);
    }
}

final class TestCsvImportHelper extends TestCase
{
    private FakeCsvDatamodel $dm;

    protected function setUp(): void
    {
        $this->dm = new FakeCsvDatamodel([
            'sku' => ['insert' => true],
            'name' => ['insert' => true],
            'price' => ['insert' => true],
            'id' => ['insert' => false],
        ]);
    }

    public function testParseBasicCsv(): void
    {
        $doc = dbapi_parse_csv_import(
            "sku,name,price\nA,Alpha,1.5\nB,Beta,2\n",
            'products',
            $this->dm
        );
        $this->assertCount(2, $doc->data);
        $this->assertEquals('products', $doc->data[0]->type);
        $this->assertEquals('A', $doc->data[0]->attributes->sku);
        $this->assertEquals('Alpha', $doc->data[0]->attributes->name);
        $this->assertEquals('1.5', $doc->data[0]->attributes->price);
    }

    public function testParseSemicolonDelimiterAndBom(): void
    {
        $doc = dbapi_parse_csv_import(
            "\xEF\xBB\xBFsku;name\nC;Gamma\n",
            'products',
            $this->dm
        );
        $this->assertCount(1, $doc->data);
        $this->assertEquals('C', $doc->data[0]->attributes->sku);
        $this->assertEquals('Gamma', $doc->data[0]->attributes->name);
    }

    public function testEmptyCellsAreOmitted(): void
    {
        $doc = dbapi_parse_csv_import(
            "sku,name,price\nD,Delta,\n",
            'products',
            $this->dm
        );
        $this->assertFalse(property_exists($doc->data[0]->attributes, 'price'));
        $this->assertEquals('Delta', $doc->data[0]->attributes->name);
    }

    public function testUnknownColumnThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown CSV column');
        dbapi_parse_csv_import("sku,bogus\nA,x\n", 'products', $this->dm);
    }

    public function testNonInsertableColumnThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not insertable');
        dbapi_parse_csv_import("sku,id\nA,1\n", 'products', $this->dm);
    }

    public function testDottedColumnThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('nested');
        dbapi_parse_csv_import("sku,rel.name\nA,x\n", 'products', $this->dm);
    }

    public function testContentTypeDetection(): void
    {
        $this->assertTrue(dbapi_is_csv_content_type('text/csv; charset=utf-8'));
        $this->assertTrue(dbapi_is_csv_content_type('multipart/form-data; boundary=xyz'));
        $this->assertTrue(dbapi_is_csv_content_type('text/plain'));
        $this->assertFalse(dbapi_is_csv_content_type('application/json'));
        $this->assertFalse(dbapi_is_csv_content_type(null));
    }
}
