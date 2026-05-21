<?php

use PHPUnit\Framework\TestCase;
use dbAPI\API\FilterParser;

require_once __DIR__ . '/../application/third_party/dbAPI/Autoloader.php';
dbAPI\Autoloader::register();

class TestFilterParser extends TestCase
{
    protected function tearDown(): void
    {
        FilterParser::setGuardLimits([
            'maxExpressionLength' => 4096,
            'maxAstDepth' => 20,
            'maxAstNodes' => 100,
        ]);
        parent::tearDown();
    }

    public function testSimpleAnd(): void
    {
        $ast = FilterParser::parse('fname=~John,bdate<2010');
        $this->assertSame('and', $ast['type']);
        $this->assertCount(2, $ast['children']);
    }

    public function testOrAndPrecedence(): void
    {
        $ast = FilterParser::parse('a=1,b=2||c=3');
        $this->assertSame('or', $ast['type']);
        $this->assertSame('and', $ast['children'][0]['type']);
        $this->assertSame('compare', $ast['children'][1]['type']);
    }

    public function testGrouping(): void
    {
        $sql = FilterParser::compile(
            FilterParser::parse('(city=Washington||city=London),active=1'),
            'customers'
        );
        $this->assertStringContainsString(' OR ', $sql);
        $this->assertStringContainsString(' AND ', $sql);
    }

    public function testLegacyFlatList(): void
    {
        $sql = FilterParser::compile([
            ['left' => 'qty', 'op' => '>', 'right' => '0'],
            ['left' => 'status', 'op' => '=', 'right' => 'open'],
        ], 'orders');
        $this->assertStringContainsString('`orders`.`qty`', $sql);
        $this->assertStringContainsString(' AND ', $sql);
    }

    public function testInOperatorValueWithSemicolons(): void
    {
        $ast = FilterParser::parse('city><Washington;London');
        $this->assertSame('Washington;London', $ast['right']);
    }

    public function testEscapedCommaInValue(): void
    {
        $ast = FilterParser::parse('note~=~part\\,two');
        $this->assertSame('part,two', $ast['right']);
    }

    public function testAddCompareMergesWithExisting(): void
    {
        $ast = FilterParser::addCompare(
            FilterParser::parse('a=1'),
            'b',
            '=',
            '2'
        );
        $this->assertSame('and', $ast['type']);
        $this->assertCount(2, $ast['children']);
    }

    public function testRemoveFieldFromAst(): void
    {
        $ast = FilterParser::removeCompareOnField(
            FilterParser::parse('fk=1,status=open'),
            'fk'
        );
        $this->assertSame('compare', $ast['type']);
        $this->assertSame('status', $ast['left']);
    }

    public function testExpressionLengthGuard(): void
    {
        FilterParser::setGuardLimits(['maxExpressionLength' => 128]);
        $this->expectException(\dbAPI\API\Exception::class);
        FilterParser::parse(str_repeat('x', 127) . '=1');
    }

    public function testAstDepthGuard(): void
    {
        FilterParser::setGuardLimits(['maxAstDepth' => 3, 'maxExpressionLength' => 4096]);
        $this->expectException(\dbAPI\API\Exception::class);
        FilterParser::parse('(((a=1,b=2),(c=3,d=4)),((e=1,f=2),(g=3,h=4)))');
    }

    public function testAstNodeGuard(): void
    {
        FilterParser::setGuardLimits(['maxAstNodes' => 5, 'maxExpressionLength' => 4096]);
        $this->expectException(\dbAPI\API\Exception::class);
        FilterParser::parse('a=1,b=2,c=3,d=4,e=5,f=6');
    }
}
