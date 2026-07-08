<?php

namespace dbAPI\API;

/**
 * Parses compact filter expressions: comparisons, comma (AND), || (OR), parentheses.
 */
class FilterParser
{
    public const VALID_OPS = [
        '!~=~', '!=~', '!~=', '!><', '<=', '>=', '<>', '><', '~=~',
        '!=', '~=', '=~', '!~', '=', '<', '>',
    ];

    private static int $maxExpressionLength = 4096;
    private static int $maxAstDepth = 20;
    private static int $maxAstNodes = 100;

    /**
     * @param array{maxExpressionLength?:int,maxAstDepth?:int,maxAstNodes?:int} $limits
     */
    public static function setGuardLimits(array $limits): void
    {
        if (isset($limits['maxExpressionLength'])) {
            self::$maxExpressionLength = max(128, (int) $limits['maxExpressionLength']);
        }
        if (isset($limits['maxAstDepth'])) {
            self::$maxAstDepth = max(3, (int) $limits['maxAstDepth']);
        }
        if (isset($limits['maxAstNodes'])) {
            self::$maxAstNodes = max(5, (int) $limits['maxAstNodes']);
        }
    }

    /**
     * @throws Exception
     */
    public static function guardExpression(string $expression): void
    {
        if (strlen($expression) > self::$maxExpressionLength) {
            throw new Exception(
                'Filter expression exceeds maximum length of ' . self::$maxExpressionLength . ' characters',
                400
            );
        }
    }

    /**
     * @throws Exception
     */
    public static function guardAst(?array $ast): void
    {
        if ($ast === null) {
            return;
        }
        self::guardAstNode($ast, 1, 0);
    }

    /**
     * @throws Exception
     */
    private static function guardAstNode(array $node, int $depth, int $nodeCount): int
    {
        $nodeCount++;
        if ($nodeCount > self::$maxAstNodes) {
            throw new Exception(
                'Filter expression exceeds maximum complexity (' . self::$maxAstNodes . ' nodes)',
                400
            );
        }
        if ($depth > self::$maxAstDepth) {
            throw new Exception(
                'Filter expression exceeds maximum nesting depth of ' . self::$maxAstDepth,
                400
            );
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $nodeCount = self::guardAstNode($child, $depth + 1, $nodeCount);
            }
        }
        return $nodeCount;
    }

    private string $s;
    private int $i = 0;
    private int $len = 0;

    /**
     * @throws Exception
     */
    public static function parse(string $expression): array
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new Exception('Empty filter expression', 400);
        }
        self::guardExpression($expression);
        $parser = new self();
        $ast = $parser->parseExpression($expression);
        self::guardAst($ast);
        return $ast;
    }

    /**
     * Parse a single comparison (no combinators).
     *
     * @throws Exception
     */
    public static function parseComparison(string $expression): array
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new Exception('Empty filter comparison', 400);
        }
        self::guardExpression($expression);
        $parser = new self();
        $parser->s = $expression;
        $parser->i = 0;
        $parser->len = strlen($expression);
        $node = $parser->parseComparisonNode();
        $parser->skipWs();
        if ($parser->i < $parser->len) {
            throw new Exception('Unexpected characters in filter comparison: ' .
                substr($expression, $parser->i, 32), 400);
        }
        self::guardAst($node);
        return $node;
    }

    /**
     * @param array|string|null $filter
     */
    public static function normalize($filter): ?array
    {
        if ($filter === null || $filter === [] || $filter === '') {
            return null;
        }
        if (is_string($filter)) {
            return self::parse($filter);
        }
        if (is_array($filter) && isset($filter['type'])) {
            self::guardAst($filter);
            return $filter;
        }
        if (is_array($filter)) {
            $children = [];
            foreach ($filter as $item) {
                if (is_array($item) && isset($item['type'])) {
                    $children[] = $item;
                } elseif (is_array($item) && isset($item['left'], $item['op'])) {
                    $left = $item['left'];
                    $children[] = [
                        'type' => 'compare',
                        'left' => is_object($left) ? $left->field : (string) $left,
                        'op' => $item['op'],
                        'right' => (string) ($item['right'] ?? ''),
                    ];
                }
            }
            if (count($children) === 0) {
                return null;
            }
            if (count($children) === 1) {
                $ast = $children[0];
                self::guardAst($ast);
                return $ast;
            }
            $ast = ['type' => 'and', 'children' => $children];
            self::guardAst($ast);
            return $ast;
        }
        return null;
    }

    public static function andNodes(?array $left, ?array $right): ?array
    {
        if ($left === null) {
            return $right;
        }
        if ($right === null) {
            return $left;
        }
        return ['type' => 'and', 'children' => [$left, $right]];
    }

    /**
     * @param array<string, array{alias: string, field: string}> $fieldAliases
     * @throws Exception
     */
    public static function compile(?array $ast, string $alias, array $fieldAliases = []): string
    {
        $ast = self::normalize($ast);
        if ($ast === null) {
            return '1';
        }
        return self::compileNode($ast, $alias, $fieldAliases);
    }

    /**
     * @return array{rels: list<string>, field: string}|null
     */
    public static function parseRelationalPath(string $left): ?array
    {
        if (strpos($left, '.') === false) {
            return null;
        }
        $parts = explode('.', $left);
        $field = array_pop($parts);
        if ($field === '' || $parts === []) {
            return null;
        }
        return ['rels' => $parts, 'field' => $field];
    }

    /**
     * Build LEFT JOINs for dotted filter paths (outbound relations only).
     *
     * @return array{joins: list<string>, fieldAliases: array<string, array{alias: string, field: string}>}
     * @throws Exception
     */
    public static function buildRelationalJoins(Datamodel $dm, string $resourceName, ?array $filterAst): array
    {
        $joins = [];
        $fieldAliases = [];
        $joinAliases = [];

        foreach (self::collectCompareFields($filterAst) as $left) {
            $path = self::parseRelationalPath($left);
            if ($path === null) {
                continue;
            }

            $parentAlias = $resourceName;
            $currentTable = $resourceName;
            $aliasPrefix = $resourceName;

            foreach ($path['rels'] as $relName) {
                $relSpec = $dm->get_outbound_relation($currentTable, $relName);
                if ($relSpec === null) {
                    throw new Exception(
                        "Invalid relational filter '$left': outbound relationship '$relName' not found on '$currentTable'",
                        400
                    );
                }

                $joinAlias = $aliasPrefix . '_' . $relName;
                if (!isset($joinAliases[$joinAlias])) {
                    $localColumn = function_exists('dbapi_outbound_local_column')
                        ? dbapi_outbound_local_column($relName, $relSpec)
                        : ($relSpec['fkfield'] ?? $relName);
                    $joins[] = sprintf(
                        'LEFT JOIN `%s` AS `%s` ON `%s`.`%s`=`%s`.`%s`',
                        $relSpec['table'],
                        $joinAlias,
                        $joinAlias,
                        $relSpec['field'],
                        $parentAlias,
                        $localColumn
                    );
                    $joinAliases[$joinAlias] = true;
                }

                $parentAlias = $joinAlias;
                $currentTable = $relSpec['table'];
                $aliasPrefix = $joinAlias;
            }

            if (!$dm->is_valid_field($currentTable, $path['field'])) {
                throw new Exception(
                    "Invalid relational filter '$left': field '{$path['field']}' not found on '$currentTable'",
                    400
                );
            }
            if (!$dm->field_is_selectable($currentTable, $path['field'])) {
                throw new Exception(
                    "Invalid relational filter '$left': field '{$path['field']}' is not selectable on '$currentTable'",
                    400
                );
            }

            $fieldAliases[$left] = [
                'alias' => $parentAlias,
                'field' => $path['field'],
            ];
        }

        return ['joins' => $joins, 'fieldAliases' => $fieldAliases];
    }

    /**
     * Remove comparison nodes on a given field (used for relationship parent injection).
     */
    public static function removeCompareOnField($filter, string $field): ?array
    {
        $ast = self::normalize($filter);
        if ($ast === null) {
            return null;
        }
        $pruned = self::pruneField($ast, $field);
        if ($pruned === null) {
            return null;
        }
        return self::collapseSingleChild($pruned);
    }

    /**
     * @return list<string>
     */
    public static function collectCompareFields(?array $filter): array
    {
        $ast = self::normalize($filter);
        if ($ast === null) {
            return [];
        }
        $fields = [];
        self::walkCompareFields($ast, $fields);
        return array_values(array_unique($fields));
    }

    /**
     * @param list<string> $fields
     */
    private static function walkCompareFields(array $node, array &$fields): void
    {
        if (($node['type'] ?? '') === 'compare') {
            $fields[] = (string) ($node['left'] ?? '');
            return;
        }
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                self::walkCompareFields($child, $fields);
            }
        }
    }

    public static function addCompare($filter, string $left, string $op, string $right): array
    {
        $node = ['type' => 'compare', 'left' => $left, 'op' => $op, 'right' => $right];
        return self::andNodes(self::normalize($filter), $node) ?? $node;
    }

    /**
     * @throws Exception
     */
    private function parseExpression(string $expression): array
    {
        $this->s = $expression;
        $this->i = 0;
        $this->len = strlen($expression);
        $node = $this->parseOr();
        $this->skipWs();
        if ($this->i < $this->len) {
            throw new Exception('Unexpected characters in filter expression near: ' .
                substr($expression, $this->i, 32), 400);
        }
        return $node;
    }

    /**
     * @throws Exception
     */
    private function parseOr(): array
    {
        $children = [$this->parseAnd()];
        $this->skipWs();
        while ($this->match('||')) {
            $children[] = $this->parseAnd();
            $this->skipWs();
        }
        if (count($children) === 1) {
            return $children[0];
        }
        return ['type' => 'or', 'children' => $children];
    }

    /**
     * @throws Exception
     */
    private function parseAnd(): array
    {
        $children = [$this->parsePrimary()];
        $this->skipWs();
        while ($this->peekComma()) {
            $this->i++;
            $this->skipWs();
            $children[] = $this->parsePrimary();
            $this->skipWs();
        }
        if (count($children) === 1) {
            return $children[0];
        }
        return ['type' => 'and', 'children' => $children];
    }

    /**
     * @throws Exception
     */
    private function parsePrimary(): array
    {
        $this->skipWs();
        if ($this->peek() === '(') {
            $this->i++;
            $node = $this->parseOr();
            $this->skipWs();
            if ($this->peek() !== ')') {
                throw new Exception('Expected ")" in filter expression', 400);
            }
            $this->i++;
            return $node;
        }
        return $this->parseComparisonNode();
    }

    /**
     * @throws Exception
     */
    private function parseComparisonNode(): array
    {
        $this->skipWs();
        if (!preg_match('/^(\w+(?:\.\w+)*)/', substr($this->s, $this->i), $m)) {
            throw new Exception('Invalid filter comparison near: ' .
                substr($this->s, $this->i, 32), 400);
        }
        $left = $m[1];
        $this->i += strlen($left);

        $op = $this->readOperator();
        if ($op === null) {
            throw new Exception("Invalid comparison operator in filter near field '$left'", 400);
        }

        $right = $this->readValue();

        return ['type' => 'compare', 'left' => $left, 'op' => $op, 'right' => $right];
    }

    private function readOperator(): ?string
    {
        $rest = substr($this->s, $this->i);
        foreach (self::VALID_OPS as $op) {
            if (strpos($rest, $op) === 0) {
                $this->i += strlen($op);
                return $op;
            }
        }
        return null;
    }

    private function readValue(): string
    {
        $value = '';
        while ($this->i < $this->len) {
            if ($this->isValueDelimiterAt()) {
                break;
            }
            if ($this->s[$this->i] === '\\' && $this->i + 1 < $this->len) {
                $value .= $this->s[$this->i + 1];
                $this->i += 2;
                continue;
            }
            $value .= $this->s[$this->i];
            $this->i++;
        }
        return $value;
    }

    /** Delimiters between filter values; must not skip whitespace (spaces are valid in values). */
    private function isValueDelimiterAt(): bool
    {
        if ($this->i >= $this->len) {
            return true;
        }
        $ch = $this->s[$this->i];
        if ($ch === ',' || $ch === ')') {
            return true;
        }
        return substr($this->s, $this->i, 2) === '||';
    }

    private function peekComma(): bool
    {
        $this->skipWs();
        return $this->i < $this->len && $this->s[$this->i] === ',';
    }

    private function peek(): string
    {
        $this->skipWs();
        if ($this->i >= $this->len) {
            return '';
        }
        return $this->s[$this->i];
    }

    private function match(string $token, bool $consume = true): bool
    {
        $this->skipWs();
        if (substr($this->s, $this->i, strlen($token)) === $token) {
            if ($consume) {
                $this->i += strlen($token);
            }
            return true;
        }
        return false;
    }

    private function skipWs(): void
    {
        while ($this->i < $this->len && ctype_space($this->s[$this->i])) {
            $this->i++;
        }
    }

    /**
     * @param array<string, array{alias: string, field: string}> $fieldAliases
     */
    public static function compileCompare(string $left, string $op, string $right, string $alias, array $fieldAliases = []): string
    {
        return self::compileCompareObject((object) [
            'left' => $left,
            'op' => $op,
            'right' => $right,
        ], $alias, $fieldAliases);
    }

    /**
     * @param array<string, array{alias: string, field: string}> $fieldAliases
     */
    public static function compileCompareObject(object $where, string $alias, array $fieldAliases = []): string
    {
        if (!property_exists($where, 'left') || !property_exists($where, 'op')) {
            return 'FALSE';
        }

        $target = $fieldAliases[$where->left] ?? ['alias' => $alias, 'field' => $where->left];
        $colAlias = $target['alias'];
        $colField = $target['field'];

        $validOps = ['!=', '=', '<', '<=', '>', '>=', '><', '~=', '!~=', '=~', '!=~', '<>', '!><', '~=~', '!~=~'];
        $where->right = ($where->right ?? '') === 'NULL' ? null : ($where->right ?? '');

        switch ($where->op) {
            case '><':
                return sprintf(
                    '`%s`.`%s` IN (\'%s\')',
                    $colAlias,
                    $colField,
                    str_replace(';', "','", $where->right)
                );
            case '!><':
                return sprintf(
                    '`%s`.`%s` NOT IN (\'%s\')',
                    $colAlias,
                    $colField,
                    str_replace(';', "','", $where->right)
                );
            case '~=':
                return sprintf('`%s`.`%s` LIKE (\'%%%s\')', $colAlias, $colField, $where->right);
            case '!~=':
                return sprintf('`%s`.`%s` NOT LIKE (\'%%%s\')', $colAlias, $colField, $where->right);
            case '=~':
                return sprintf('`%s`.`%s` LIKE (\'%s%%\')', $colAlias, $colField, $where->right);
            case '!=~':
                return sprintf('`%s`.`%s` NOT LIKE (\'%s%%\')', $colAlias, $colField, $where->right);
            case '~=~':
                return sprintf('`%s`.`%s` LIKE (\'%%%s%%\')', $colAlias, $colField, $where->right);
            case '!~=~':
                return sprintf('`%s`.`%s` NOT LIKE (\'%%%s%%\')', $colAlias, $colField, $where->right);
            case '=':
                if ($where->right === '__NULL__') {
                    return sprintf('`%s`.`%s` IS NULL', $colAlias, $colField);
                }
                return sprintf(
                    '`%s`.`%s` %s %s',
                    $colAlias,
                    $colField,
                    $where->op,
                    ($where->right !== '' ? "'".$where->right."'" : 'NULL')
                );
            case '!=':
                if ($where->right === '__NULL__') {
                    return sprintf('`%s`.`%s` IS NOT NULL', $colAlias, $colField);
                }
                return sprintf(
                    '`%s`.`%s` %s %s',
                    $colAlias,
                    $colField,
                    $where->op,
                    ($where->right !== '' ? "'".$where->right."'" : 'NULL')
                );
            default:
                if (in_array($where->op, $validOps, true)) {
                    return sprintf(
                        '`%s`.`%s` %s %s',
                        $colAlias,
                        $colField,
                        $where->op,
                        ($where->right !== '' ? "'".$where->right."'" : 'NULL')
                    );
                }
                return 'TRUE';
        }
    }

    /**
     * @param array<string, array{alias: string, field: string}> $fieldAliases
     */
    private static function compileNode(array $node, string $alias, array $fieldAliases = []): string
    {
        switch ($node['type']) {
            case 'compare':
                return self::compileCompare($node['left'], $node['op'], $node['right'], $alias, $fieldAliases);
            case 'and':
                $parts = array_map(function ($child) use ($alias, $fieldAliases) {
                    return self::compileNode($child, $alias, $fieldAliases);
                }, $node['children']);
                return '(' . implode(' AND ', $parts) . ')';
            case 'or':
                $parts = array_map(function ($child) use ($alias, $fieldAliases) {
                    return self::compileNode($child, $alias, $fieldAliases);
                }, $node['children']);
                return '(' . implode(' OR ', $parts) . ')';
            default:
                return '1';
        }
    }

    /**
     * @param list<string> $joins
     * @return list<string>
     */
    public static function dedupeJoins(array $joins): array
    {
        $seen = [];
        $result = [];
        foreach ($joins as $join) {
            if (preg_match('/AS `([^`]+)`/', $join, $m)) {
                if (isset($seen[$m[1]])) {
                    continue;
                }
                $seen[$m[1]] = true;
            }
            $result[] = $join;
        }
        return $result;
    }

    private static function pruneField(array $node, string $field): ?array
    {
        if ($node['type'] === 'compare') {
            return ($node['left'] === $field) ? null : $node;
        }
        $children = [];
        foreach ($node['children'] as $child) {
            $kept = self::pruneField($child, $field);
            if ($kept !== null) {
                $children[] = $kept;
            }
        }
        if (count($children) === 0) {
            return null;
        }
        if (count($children) === 1) {
            return $children[0];
        }
        return ['type' => $node['type'], 'children' => $children];
    }

    private static function collapseSingleChild(array $node): array
    {
        if (in_array($node['type'], ['and', 'or'], true) && count($node['children']) === 1) {
            return $node['children'][0];
        }
        return $node;
    }
}
