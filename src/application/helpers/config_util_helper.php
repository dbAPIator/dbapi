<?php
/**
 * @param $data
 * @return string
 */
function to_php_code($data,$addBegining=false)
{
//    $json = json_encode($data,JSON_PRETTY_PRINT);
//    print_r($json);
//    $str =  preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],$json).";";
//    $str = str_replace('"',"'",$str);

    return ($addBegining ? "<?php\nreturn " : "").var_export($data,true).";";
}

/**
 * @param $db_struct
 * @param $target_struct
 * @return array
 */
function compute_struct_diff($db_struct, $target_struct) {
    if(is_array($db_struct) xor is_array($target_struct)) {
        return $target_struct;
    }
    if(!is_array($target_struct)) {
        return $target_struct;
    }

    $diff = [];
    foreach ($target_struct as $key=>$val) {
        if(!isset($db_struct[$key])) {
            $diff[$key] = $val;
            continue;
        }
        if($val==$db_struct[$key]) {
            continue;
        }
        $diff[$key] = compute_struct_diff($db_struct[$key],$val);
    }
    return $diff;
}


/**
 * @param $arr1
 * @param $arr2
 * @return bool
 */
/**
 * Local FK column on the parent resource for an outbound relation (relation array key).
 */
function dbapi_outbound_local_column(string $relName, array $relSpec): string
{
    if (($relSpec['type'] ?? '') !== 'outbound') {
        return $relName;
    }
    return $relSpec['fkfield'] ?? $relName;
}

/**
 * Stable identity for a relation edge (used to preserve public names on regen).
 */
function structure_relation_edge_id(string $parentEntity, array $relSpec, ?string $relName = null): string
{
    if (($relSpec['type'] ?? '') === 'inbound') {
        return 'inbound:' . $parentEntity . ':' . $relSpec['table'] . '.' . $relSpec['field'];
    }
    $local = dbapi_outbound_local_column($relName ?? '', $relSpec);
    return 'outbound:' . $parentEntity . ':' . $local . '->' . $relSpec['table'] . '.' . $relSpec['field'];
}

/**
 * Default public name for a new inbound relation (deterministic).
 */
function structure_default_inbound_relation_name(string $childTable, string $childField): string
{
    return $childTable . '_' . $childField;
}

/**
 * Propose inbound relation name; short child table name only when unambiguous.
 */
function structure_propose_inbound_relation_name(
    string $childTable,
    string $childField,
    array $usedNames,
    array $inboundCountByChild
): string {
    $short = $childTable;
    $qualified = structure_default_inbound_relation_name($childTable, $childField);

    if (($inboundCountByChild[$childTable] ?? 0) <= 1 && !isset($usedNames[$short])) {
        return $short;
    }
    if (!isset($usedNames[$qualified])) {
        return $qualified;
    }
    $n = 2;
    while (isset($usedNames[$qualified . '_' . $n])) {
        $n++;
    }
    return $qualified . '_' . $n;
}

/**
 * Resolve FK target for a column from outbound relation (canonical) or legacy field.foreignKey.
 *
 * @return array{table:string, field:string}|null
 */
function structure_resolve_field_foreign_key(array $entity, string $fieldName, ?string $relName = null): ?array
{
    $relName = $relName ?? $fieldName;
    $relations = $entity['relations'] ?? [];
    if (isset($relations[$relName]) && is_array($relations[$relName])
        && ($relations[$relName]['type'] ?? '') === 'outbound') {
        return [
            'table' => $relations[$relName]['table'],
            'field' => $relations[$relName]['field'],
        ];
    }
    $fields = $entity['fields'] ?? [];
    if (isset($fields[$fieldName]['foreignKey']) && is_array($fields[$fieldName]['foreignKey'])) {
        return $fields[$fieldName]['foreignKey'];
    }
    return null;
}

/**
 * @return array<int, array{parent:string, child:string, childField:string, parentField:string}>
 */
function structure_collect_inbound_edges(array $structure): array
{
    $edges = [];
    $seen = [];
    foreach ($structure as $childTable => $table) {
        if (!is_array($table)) {
            continue;
        }
        if (isset($table['relations']) && is_array($table['relations'])) {
            foreach ($table['relations'] as $relName => $rel) {
                if (!is_array($rel) || ($rel['type'] ?? '') !== 'outbound') {
                    continue;
                }
                $childField = dbapi_outbound_local_column($relName, $rel);
                $parent = $rel['table'];
                $key = "{$childTable}.{$childField}->{$parent}.{$rel['field']}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $edges[] = [
                    'parent' => $parent,
                    'child' => $childTable,
                    'childField' => $childField,
                    'parentField' => $rel['field'],
                ];
            }
        }
        if (!isset($table['fields']) || !is_array($table['fields'])) {
            continue;
        }
        foreach ($table['fields'] as $fldName => $fldSpec) {
            if (!is_array($fldSpec) || !isset($fldSpec['foreignKey'])) {
                continue;
            }
            $key = "{$childTable}.{$fldName}->{$fldSpec['foreignKey']['table']}.{$fldSpec['foreignKey']['field']}";
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $edges[] = [
                'parent' => $fldSpec['foreignKey']['table'],
                'child' => $childTable,
                'childField' => $fldName,
                'parentField' => $fldSpec['foreignKey']['field'],
            ];
        }
    }
    return $edges;
}

/**
 * Phase 2: relations-only — ensure outbound relations exist, drop redundant field.foreignKey.
 *
 * @return array{structure: array, changed: bool}
 */
function structure_phase2_normalize(array $structure): array
{
    $changed = false;
    foreach ($structure as $entityName => &$entity) {
        if (!is_array($entity) || !isset($entity['fields']) || !is_array($entity['fields'])) {
            continue;
        }
        if (!isset($entity['relations']) || !is_array($entity['relations'])) {
            $entity['relations'] = [];
        }
        foreach ($entity['fields'] as $fieldName => &$field) {
            if (!is_array($field) || !isset($field['foreignKey']) || !is_array($field['foreignKey'])) {
                continue;
            }
            $fk = $field['foreignKey'];
            $localCol = $fieldName;
            if (!isset($entity['relations'][$localCol])
                || ($entity['relations'][$localCol]['type'] ?? '') !== 'outbound') {
                $entity['relations'][$localCol] = [
                    'table' => $fk['table'],
                    'field' => $fk['field'],
                    'type' => 'outbound',
                ];
                $changed = true;
            }
            unset($field['foreignKey']);
            $changed = true;
        }
        unset($field);
    }
    unset($entity);
    return ['structure' => $structure, 'changed' => $changed];
}

/**
 * Assign inbound relations with stable, deterministic public names.
 *
 * @return list<array{code:string, message:string, entity?:string, relation?:string}>
 */
function structure_apply_inbound_relations(array &$structure, array $edges, ?array &$permissions = null): array
{
    $warnings = [];
    $defaultPerms = [
        'insert' => true,
        'update' => true,
        'select' => true,
        'searchable' => true,
    ];
    $byParent = [];
    foreach ($edges as $edge) {
        $byParent[$edge['parent']][] = $edge;
    }

    foreach ($byParent as $parent => $parentEdges) {
        usort($parentEdges, function ($a, $b) {
            return [$a['child'], $a['childField']] <=> [$b['child'], $b['childField']];
        });

        if (!isset($structure[$parent]['relations']) || !is_array($structure[$parent]['relations'])) {
            $structure[$parent]['relations'] = [];
        }

        $usedNames = array_fill_keys(array_keys($structure[$parent]['relations']), true);
        $inboundCountByChild = [];
        foreach ($parentEdges as $edge) {
            $inboundCountByChild[$edge['child']] = ($inboundCountByChild[$edge['child']] ?? 0) + 1;
        }

        foreach ($parentEdges as $edge) {
            $name = structure_propose_inbound_relation_name(
                $edge['child'],
                $edge['childField'],
                $usedNames,
                $inboundCountByChild
            );

            if ($name !== $edge['child']) {
                $warnings[] = [
                    'code' => 'QUALIFIED_RELATION_NAME',
                    'message' => "Inbound relation on '{$parent}' published as '{$name}' (child {$edge['child']}.{$edge['childField']})",
                    'entity' => $parent,
                    'relation' => $name,
                ];
            }

            $structure[$parent]['relations'][$name] = [
                'table' => $edge['child'],
                'field' => $edge['childField'],
                'type' => 'inbound',
            ];
            if ($permissions !== null) {
                if (!isset($permissions[$parent]['relations']) || !is_array($permissions[$parent]['relations'])) {
                    $permissions[$parent]['relations'] = [];
                }
                $permissions[$parent]['relations'][$name] = $defaultPerms;
            }
            $usedNames[$name] = true;
        }
    }

    return $warnings;
}

/**
 * Preserve public relation names from the previous effective schema on regen.
 *
 * @param array<string, array<string, bool>> $protectedRelationsByEntity
 * @return array{structure: array, warnings: list<array{code:string, message:string, entity?:string, relation?:string}>}
 */
function structure_merge_preserved_relations(
    array $oldStructure,
    array $newStructure,
    array $protectedRelationsByEntity = []
): array
{
    $warnings = [];

    foreach (array_keys($newStructure) as $parent) {
        if (!is_array($newStructure[$parent])) {
            continue;
        }

        $oldRels = (is_array($oldStructure[$parent] ?? null) && isset($oldStructure[$parent]['relations']))
            ? $oldStructure[$parent]['relations'] : [];
        $newRels = $newStructure[$parent]['relations'] ?? [];

        if (!is_array($oldRels)) {
            $oldRels = [];
        }
        if (!is_array($newRels)) {
            $newRels = [];
        }

        $oldByEdge = [];
        foreach ($oldRels as $relName => $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $oldByEdge[structure_relation_edge_id($parent, $spec, $relName)] = [
                'name' => $relName,
                'spec' => $spec,
            ];
        }

        $merged = [];
        $usedNames = [];

        foreach ($newRels as $relName => $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $edgeId = structure_relation_edge_id($parent, $spec, $relName);
            $publicName = $relName;

            if (isset($oldByEdge[$edgeId])) {
                $publicName = $oldByEdge[$edgeId]['name'];
                unset($oldByEdge[$edgeId]);
            }

            if (isset($usedNames[$publicName])) {
                $warnings[] = [
                    'code' => 'CONFLICT_RELATION_NAME',
                    'message' => "Cannot assign relation '{$publicName}' on '{$parent}': name already used",
                    'entity' => $parent,
                    'relation' => $publicName,
                ];
                continue;
            }

            $merged[$publicName] = $spec;
            $usedNames[$publicName] = true;
        }

        foreach ($oldByEdge as $edgeId => $info) {
            $relationName = $info['name'];
            $isProtected = isset($protectedRelationsByEntity[$parent][$relationName]);
            if (!$isProtected) {
                $warnings[] = [
                    'code' => 'ORPHAN_RELATION',
                    'message' => "Relation '{$relationName}' on '{$parent}' no longer exists in the database; kept for API compatibility",
                    'entity' => $parent,
                    'relation' => $relationName,
                ];
            }
            if (!isset($usedNames[$relationName])) {
                $merged[$relationName] = $info['spec'];
                $usedNames[$relationName] = true;
            }
        }

        if (count($merged) || isset($newStructure[$parent]['relations'])) {
            $newStructure[$parent]['relations'] = $merged;
        }
    }

    return ['structure' => $newStructure, 'warnings' => $warnings];
}

/**
 * Expand SchemaOverrides keys (hiddenFields, hiddenEntities) into entity patch entries for merge.
 * Top-level hiddenFields/hiddenEntities are not merged into structure as-is.
 */
function schema_patch_apply_overrides(array $patch): array
{
    $hiddenFields = $patch['hiddenFields'] ?? null;
    $hiddenEntities = $patch['hiddenEntities'] ?? null;

    if (!is_array($hiddenFields) && !is_array($hiddenEntities)) {
        return $patch;
    }

    $merge = $patch;
    unset($merge['hiddenFields'], $merge['hiddenEntities']);

    $hidden = [];

    if (is_array($hiddenEntities)) {
        foreach ($hiddenEntities as $entity) {
            if (!is_string($entity) || $entity === '') {
                continue;
            }
            if (!isset($hidden[$entity]) || !is_array($hidden[$entity])) {
                $hidden[$entity] = [];
            }
            $hidden[$entity]['read'] = false;
        }
    }

    if (is_array($hiddenFields)) {
        foreach ($hiddenFields as $entity => $fields) {
            if (!is_string($entity) || $entity === '' || !is_array($fields)) {
                continue;
            }
            if (!isset($hidden[$entity]) || !is_array($hidden[$entity])) {
                $hidden[$entity] = [];
            }
            if (!isset($hidden[$entity]['fields']) || !is_array($hidden[$entity]['fields'])) {
                $hidden[$entity]['fields'] = [];
            }
            foreach ($fields as $field) {
                if (!is_string($field) || $field === '') {
                    continue;
                }
                if (!isset($hidden[$entity]['fields'][$field]) || !is_array($hidden[$entity]['fields'][$field])) {
                    $hidden[$entity]['fields'][$field] = [];
                }
                $hidden[$entity]['fields'][$field]['select'] = false;
            }
        }
    }

    return smart_array_merge_recursive($hidden, $merge);
}

/**
 * Introspect DB + preserve stable relation names + optional patch merge.
 *
 * @return array{structure: array, warnings: list<array>}
 */
function structure_build_from_database($db, string $dbName, array $oldStructure = [], ?array $patch = null): array
{
    require_once APPPATH . 'third_party/dbAPI/Config/DBWalk.php';

    $parsed = \dbAPI\Config\DBWalk::parse($db, $dbName);
    $structure = $parsed['structure'];
    $warnings = $parsed['warnings'] ?? [];

    $patchExpanded = null;
    if (is_array($patch) && count($patch)) {
        // Used to suppress misleading ORPHAN_RELATION warnings for relations
        // explicitly defined by overrides (e.g. via views).
        $patchExpanded = schema_patch_apply_overrides($patch);
    }

    if (count($oldStructure)) {
        $protectedRelationsByEntity = [];
        if (is_array($patchExpanded)) {
            foreach ($patchExpanded as $entity => $patchEntity) {
                if (!is_string($entity) || $entity === '' || !is_array($patchEntity)) {
                    continue;
                }
                $rels = $patchEntity['relations'] ?? null;
                if (!is_array($rels)) {
                    continue;
                }
                foreach ($rels as $relName => $relSpec) {
                    if (!is_string($relName) || $relName === '' || !is_array($relSpec)) {
                        continue;
                    }
                    $protectedRelationsByEntity[$entity][$relName] = true;
                }
            }
        }

        $merge = structure_merge_preserved_relations($oldStructure, $structure, $protectedRelationsByEntity);
        $structure = $merge['structure'];
        $warnings = array_merge($warnings, $merge['warnings']);
    }

    if (is_array($patchExpanded)) {
        $structure = smart_array_merge_recursive($structure, $patchExpanded);
    }

    return ['structure' => $structure, 'warnings' => $warnings];
}

/**
 * Copy per-resource hooks from a previous effective schema.
 */
function structure_copy_hooks_from_old(array $oldStructure, array &$newStructure): void
{
    foreach (array_keys($newStructure) as $resourceName) {
        if (isset($oldStructure[$resourceName]['hooks'])) {
            $newStructure[$resourceName]['hooks'] = $oldStructure[$resourceName]['hooks'];
        }
    }
}

/**
 * Phase 1 structure slimming: remove referencedBy, fkfield, redundant name keys.
 *
 * @return array{structure: array, changed: bool}
 */
function structure_phase1_slim(array $structure): array
{
    $changed = false;
    foreach ($structure as $entityName => &$entity) {
        if (!is_array($entity)) {
            continue;
        }
        if (array_key_exists('name', $entity) && (!isset($entity['name']) || $entity['name'] === $entityName)) {
            unset($entity['name']);
            $changed = true;
        }
        if (!isset($entity['fields']) || !is_array($entity['fields'])) {
            continue;
        }
        foreach ($entity['fields'] as $fieldName => &$field) {
            if (!is_array($field)) {
                continue;
            }
            if (array_key_exists('name', $field) && (!isset($field['name']) || $field['name'] === $fieldName)) {
                unset($field['name']);
                $changed = true;
            }
            if (array_key_exists('referencedBy', $field)) {
                unset($field['referencedBy']);
                $changed = true;
            }
        }
        unset($field);
        if (!isset($entity['relations']) || !is_array($entity['relations'])) {
            continue;
        }
        foreach ($entity['relations'] as $relName => &$rel) {
            if (!is_array($rel)) {
                continue;
            }
            if (($rel['type'] ?? '') === 'outbound' && array_key_exists('fkfield', $rel)) {
                unset($rel['fkfield']);
                $changed = true;
            }
        }
        unset($rel);
    }
    unset($entity);

    return ['structure' => $structure, 'changed' => $changed];
}

function smart_array_merge_recursive($arr1,$arr2) {
    if(!is_array($arr1) || !is_array($arr2) )
        return $arr1;

    foreach ($arr2 as $key=>$val) {
        if(is_null($val)) {
            unset($arr1[$key]);
            continue;
        }
        if(!array_key_exists($key,$arr1)) {
            $arr1[$key] = $val;
            continue;
        }
        if(is_array($val) && is_array($arr1[$key])) {
            $arr1[$key] = smart_array_merge_recursive($arr1[$key],$val);
            continue;
        }
        $arr1[$key] = $val;

    }
    return  $arr1;
}

function remove_dir_recursive($fsEntry) {
    if(!is_dir($fsEntry)) {
        if(!@unlink($fsEntry)) throw new Exception("Could not remove $fsEntry");
        return true;
    }

    $dir = opendir($fsEntry);
    while ($entry = readdir($dir)) {
        if(in_array($entry,[".",".."])) continue;
        remove_dir_recursive($fsEntry."/".$entry);
    }
    closedir($dir);
    if(!rmdir($fsEntry)) throw new Exception("Could not remove $fsEntry");
    return true;
}

/**
 * Recursively sanitizes array values to prevent code injection
 * @param mixed $data Input array or value to sanitize
 * @return mixed Sanitized array or value
 */
function sanitize_data($data) {
    if (is_array($data)) {
        $clean = [];
        foreach ($data as $key => $value) {
            // Sanitize the key
            $clean_key = strip_tags($key);
            // Recursively sanitize the value
            $clean[$clean_key] = sanitize_data($value);
        }
        return $clean;
    } else {
        // For non-array values, apply sanitization
        if (is_string($data)) {
            // Remove any potential PHP tags
            $data = preg_replace('/<\?(php)?|\?>/', '', $data);
            // Convert special characters to HTML entities
            return strip_tags($data);
        }
        // Return non-string values unchanged
        return $data;
    }
}


function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
