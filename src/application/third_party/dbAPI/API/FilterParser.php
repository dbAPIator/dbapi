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
     * @throws Exception
     */
    public static function compile(?array $ast, string $alias): string
    {
        $ast = self::normalize($ast);
        if ($ast === null) {
            return '1';
        }
        return self::compileNode($ast, $alias);
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
        if (!preg_match('/^(\w+)/', substr($this->s, $this->i), $m)) {
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
            if ($this->isDelimiterAt(0)) {
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

    private function isDelimiterAt(int $parenDepth): bool
    {
        if ($parenDepth !== 0) {
            return false;
        }
        if ($this->peek() === ',' || $this->peek() === ')') {
            return true;
        }
        return $this->match('||', false);
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

    public static function compileCompare(string $left, string $op, string $right, string $alias): string
    {
        return self::compileCompareObject((object) [
            'left' => $left,
            'op' => $op,
            'right' => $right,
        ], $alias);
    }

    public static function compileCompareObject(object $where, string $alias): string
    {
        if (!property_exists($where, 'left') || !property_exists($where, 'op')) {
            return 'FALSE';
        }

        $validOps = ['!=', '=', '<', '<=', '>', '>=', '><', '~=', '!~=', '=~', '!=~', '<>', '!><', '~=~', '!~=~'];
        $where->right = ($where->right ?? '') === 'NULL' ? null : ($where->right ?? '');

        switch ($where->op) {
            case '><':
                return sprintf(
                    '`%s`.`%s` IN (\'%s\')',
                    $alias,
                    $where->left,
                    str_replace(';', "','", $where->right)
                );
            case '!><':
                return sprintf(
                    '`%s`.`%s` NOT IN (\'%s\')',
                    $alias,
                    $where->left,
                    str_replace(';', "','", $where->right)
                );
            case '~=':
                return sprintf('`%s`.`%s` LIKE (\'%%%s\')', $alias, $where->left, $where->right);
            case '!~=':
                return sprintf('`%s`.`%s` NOT LIKE (\'%%%s\')', $alias, $where->left, $where->right);
            case '=~':
                return sprintf('`%s`.`%s` LIKE (\'%s%%\')', $alias, $where->left, $where->right);
            case '!=~':
                return sprintf('`%s`.`%s` NOT LIKE (\'%s%%\')', $alias, $where->left, $where->right);
            case '~=~':
                return sprintf('`%s`.`%s` LIKE (\'%%%s%%\')', $alias, $where->left, $where->right);
            case '!~=~':
                return sprintf('`%s`.`%s` NOT LIKE (\'%%%s%%\')', $alias, $where->left, $where->right);
            case '=':
                if ($where->right === '__NULL__') {
                    return sprintf('`%s`.`%s` IS NULL', $alias, $where->left);
                }
                return sprintf(
                    '`%s`.`%s` %s %s',
                    $alias,
                    $where->left,
                    $where->op,
                    ($where->right !== '' ? "'".$where->right."'" : 'NULL')
                );
            case '!=':
                if ($where->right === '__NULL__') {
                    return sprintf('`%s`.`%s` IS NOT NULL', $alias, $where->left);
                }
                return sprintf(
                    '`%s`.`%s` %s %s',
                    $alias,
                    $where->left,
                    $where->op,
                    ($where->right !== '' ? "'".$where->right."'" : 'NULL')
                );
            default:
                if (in_array($where->op, $validOps, true)) {
                    return sprintf(
                        '`%s`.`%s` %s %s',
                        $alias,
                        $where->left,
                        $where->op,
                        ($where->right !== '' ? "'".$where->right."'" : 'NULL')
                    );
                }
                return 'TRUE';
        }
    }

    private static function compileNode(array $node, string $alias): string
    {
        switch ($node['type']) {
            case 'compare':
                return self::compileCompare($node['left'], $node['op'], $node['right'], $alias);
            case 'and':
                $parts = array_map(function ($child) use ($alias) {
                    return self::compileNode($child, $alias);
                }, $node['children']);
                return '(' . implode(' AND ', $parts) . ')';
            case 'or':
                $parts = array_map(function ($child) use ($alias) {
                    return self::compileNode($child, $alias);
                }, $node['children']);
                return '(' . implode(' OR ', $parts) . ')';
            default:
                return '1';
        }
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
