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

require_once APPPATH . 'helpers/config_util_helper.php';

class TestStructureMergePreservedRelations extends TestCase
{
    public function testOrphanRelationWarningEmittedWhenNotProtected(): void
    {
        $oldStructure = [
            'locations_expanded' => [
                'relations' => [
                    'company_id' => [
                        'type' => 'outbound',
                        'table' => 'companies',
                        'field' => 'id',
                        'fkfield' => 'company_id',
                    ],
                ],
            ],
        ];

        $newStructure = [
            'locations_expanded' => [
                'relations' => [],
            ],
        ];

        $result = structure_merge_preserved_relations($oldStructure, $newStructure);

        $codes = array_map(static fn($w) => $w['code'] ?? null, $result['warnings']);
        $this->assertContains('ORPHAN_RELATION', $codes);
        $this->assertArrayHasKey(
            'company_id',
            $result['structure']['locations_expanded']['relations']
        );
    }

    public function testOrphanRelationWarningSuppressedWhenProtectedByPatch(): void
    {
        $protectedRelationsByEntity = [
            'locations_expanded' => [
                'company_id' => true,
            ],
        ];

        $oldStructure = [
            'locations_expanded' => [
                'relations' => [
                    'company_id' => [
                        'type' => 'outbound',
                        'table' => 'companies',
                        'field' => 'id',
                        'fkfield' => 'company_id',
                    ],
                ],
            ],
        ];

        $newStructure = [
            'locations_expanded' => [
                'relations' => [],
            ],
        ];

        $result = structure_merge_preserved_relations(
            $oldStructure,
            $newStructure,
            $protectedRelationsByEntity
        );

        $this->assertCount(0, $result['warnings']);
        $this->assertArrayHasKey(
            'company_id',
            $result['structure']['locations_expanded']['relations']
        );
    }
}

