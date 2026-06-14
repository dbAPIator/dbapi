<?php

namespace dbAPI\API;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Data-plane access: path rules (with when), table access, mandatoryFilter / mandatoryAssign.
 */
class AccessControl
{
    public const ACCESS_PUBLIC = 'public';
    public const ACCESS_PRIVATE = 'private';
    public const ACCESS_SCOPED = 'scoped';

    public static function resolveDefaultAccessRule(array $auth): string
    {
        if (isset($auth['default_access_rule'])) {
            $rule = $auth['default_access_rule'];
            if ($rule === self::ACCESS_PUBLIC || $rule === self::ACCESS_PRIVATE) {
                return $rule;
            }
        }
        $allowGuest = $auth['allowGuest'] ?? (($auth['mode'] ?? null) === 'none');
        return $allowGuest ? self::ACCESS_PUBLIC : self::ACCESS_PRIVATE;
    }

    public static function authorizationHeader(array $headers, ?array $server = null): string
    {
        foreach (['Authorization', 'X-Authorization'] as $wanted) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, $wanted) === 0) {
                    $header = trim((string) $value);
                    if ($header !== '') {
                        return $header;
                    }
                }
            }
            if ($server !== null) {
                $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $wanted));
                if (!empty($server[$serverKey])) {
                    return trim((string) $server[$serverKey]);
                }
            }
        }
        if ($server !== null) {
            foreach (['REDIRECT_HTTP_AUTHORIZATION', 'REDIRECT_HTTP_X_AUTHORIZATION'] as $key) {
                if (!empty($server[$key])) {
                    return trim((string) $server[$key]);
                }
            }
        }
        return '';
    }

    /**
     * @return array{payload: \stdClass, valid: bool, anonymous: bool}
     */
    public static function decodeJwt(array $auth, array $headers, ?array $server = null): array
    {
        $authMode = $auth['mode'] ?? null;
        $requiresJwt = !empty($auth['jwt_key']) && $authMode !== 'none';
        $payload = new \stdClass();
        $valid = false;
        $anonymous = true;

        if (!$requiresJwt) {
            return ['payload' => $payload, 'valid' => false, 'anonymous' => true];
        }

        $authHeader = self::authorizationHeader($headers, $server);
        $jwt = null;
        if (preg_match('/Bearer (.*)$/i', $authHeader, $matches) === 1) {
            $jwt = trim($matches[1]);
        }

        if ($jwt === null || $jwt === '') {
            return ['payload' => $payload, 'valid' => false, 'anonymous' => true];
        }

        try {
            $payload = JWT::decode($jwt, new Key($auth['jwt_key'], 'HS256'));
            $valid = true;
            $anonymous = false;
            if (isset($payload->exp) && (int) $payload->exp < time()) {
                $valid = false;
            }
        } catch (\Throwable $e) {
            $payload = new \stdClass();
        }

        return ['payload' => $payload, 'valid' => $valid, 'anonymous' => !$valid];
    }

    /**
     * @return array<string, scalar>
     */
    public static function claimSubstitutions(\stdClass $payload): array
    {
        $userData = [];
        foreach (get_object_vars($payload) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $userData['{{' . $key . '}}'] = $value;
            }
        }
        return $userData;
    }

    public static function whenMatches(array $when, \stdClass $payload): bool
    {
        foreach ($when as $claim => $expected) {
            if (!property_exists($payload, $claim)) {
                return false;
            }
            if ((string) $payload->$claim !== (string) $expected) {
                return false;
            }
        }
        return true;
    }

    /**
     * First matching path rule. Returns null if no rule matched.
     *
     * @return bool|null allow/deny, or null when no pattern matched
     */
    public static function evaluatePathRules(
        array $rules,
        string $reqPath,
        string $method,
        array $userData,
        \stdClass $payload
    ): ?bool {
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $when = $rule['when'] ?? null;
            if (is_array($when) && !self::whenMatches($when, $payload)) {
                continue;
            }

            $pattern = $rule['pattern'] ?? '';
            $urlPattern = '/^' . strtr(
                str_replace(['/', '*'], ['\/', '.*'], $pattern),
                $userData
            ) . '$/i';
            if (!preg_match($urlPattern, $reqPath)) {
                continue;
            }

            $ruleMethod = $rule['method'] ?? $rule['methods'] ?? null;
            if ($ruleMethod) {
                $methodPattern = '/^' . strtoupper(str_replace('*', '.*', $ruleMethod)) . '$/i';
                if (!preg_match($methodPattern, $method)) {
                    continue;
                }
            }

            return (bool) ($rule['allow'] ?? false);
        }

        return null;
    }

    public static function resourceFromDataPath(string $reqPath): ?string
    {
        $reqPath = '/' . trim($reqPath, '/');
        if ($reqPath === '/') {
            return null;
        }
        $segments = explode('/', trim($reqPath, '/'));
        $name = $segments[0] ?? '';
        return $name !== '' ? $name : null;
    }

    /**
     * @param array<string, mixed> $resourceConfig
     */
    public static function effectiveAccess(array $auth, ?array $resourceConfig): string
    {
        $access = $resourceConfig['access'] ?? null;
        if (in_array($access, [self::ACCESS_PUBLIC, self::ACCESS_PRIVATE, self::ACCESS_SCOPED], true)) {
            return $access;
        }
        return self::resolveDefaultAccessRule($auth);
    }

    /**
     * Table-level access when no path rule matched.
     *
     * @param array<string, mixed> $structure
     */
    public static function evaluateTableAccess(
        array $auth,
        array $structure,
        string $reqPath,
        string $method,
        \stdClass $payload,
        bool $jwtValid,
        bool $anonymous
    ): bool {
        $method = strtoupper($method);
        if ($method === 'OPTIONS') {
            return true;
        }

        $resourceName = self::resourceFromDataPath($reqPath);
        if ($resourceName === null) {
            return false;
        }

        $resourceConfig = $structure[$resourceName] ?? [];
        $access = self::effectiveAccess($auth, is_array($resourceConfig) ? $resourceConfig : []);

        if ($anonymous || !$jwtValid) {
            if ($access === self::ACCESS_PUBLIC && in_array($method, ['GET', 'HEAD'], true)) {
                return true;
            }
            return false;
        }

        if ($access === self::ACCESS_SCOPED) {
            $scopePattern = $resourceConfig['scopePattern'] ?? null;
            if (!is_string($scopePattern) || $scopePattern === '') {
                return false;
            }
            $userData = self::claimSubstitutions($payload);
            $urlPattern = '/^' . strtr(
                str_replace(['/', '*'], ['\/', '.*'], $scopePattern),
                $userData
            ) . '(\/.*)?$/i';
            return (bool) preg_match($urlPattern, $reqPath);
        }

        return true;
    }

    public static function shouldBypassMandatoryFilter(array $auth, \stdClass $payload): bool
    {
        $roles = $auth['filterBypassRoles'] ?? [];
        if (!is_array($roles) || !property_exists($payload, 'role')) {
            return false;
        }
        return in_array((string) $payload->role, array_map('strval', $roles), true);
    }

    public static function substituteClaims(string $template, \stdClass $payload): string
    {
        $map = [];
        foreach (self::claimSubstitutions($payload) as $placeholder => $value) {
            $map[$placeholder] = (string) $value;
        }
        return strtr($template, $map);
    }

    public static function applyMandatoryFilter(
        DBAPIRequest $request,
        Datamodel $dm,
        array $auth,
        \stdClass $payload
    ): void {
        if (self::shouldBypassMandatoryFilter($auth, $payload)) {
            return;
        }

        $config = $dm->get_config($request->resourceName);
        $mf = $config['mandatoryFilter'] ?? null;
        if (!is_string($mf) || trim($mf) === '') {
            return;
        }

        $expr = self::substituteClaims(trim($mf), $payload);
        if (strpos($expr, '{{') !== false) {
            $request->filter = FilterParser::andNodes(
                FilterParser::normalize($request->filter),
                FilterParser::parse('id=-1')
            );
            return;
        }

        try {
            $mandatoryAst = FilterParser::parse($expr);
        } catch (\Throwable $e) {
            return;
        }

        foreach (FilterParser::collectCompareFields($mandatoryAst) as $field) {
            $request->filter = FilterParser::removeCompareOnField($request->filter, $field);
        }

        $request->filter = FilterParser::andNodes($mandatoryAst, FilterParser::normalize($request->filter)) ?? $mandatoryAst;
    }

    /**
     * @param array<string, mixed>|null $clientFilterAst
     */
    public static function compileMandatoryWhere(
        string $table,
        Datamodel $dm,
        array $auth,
        \stdClass $payload,
        ?array $clientFilterAst = null,
        ?string $recId = null
    ): string {
        if (self::shouldBypassMandatoryFilter($auth, $payload)) {
            if ($recId !== null && $recId !== '') {
                $pk = $dm->get_primary_key($table);
                if ($pk) {
                    return FilterParser::compile(
                        FilterParser::addCompare(null, $pk, '=', $recId),
                        $table
                    );
                }
            }
            return FilterParser::compile($clientFilterAst, $table);
        }

        $request = new DBAPIRequest($table, 20);
        if ($recId !== null && $recId !== '') {
            $pk = $dm->get_primary_key($table);
            if ($pk) {
                $request->add_filter_condition($pk, '=', $recId);
            }
        }
        if ($clientFilterAst !== null) {
            $request->filter = $clientFilterAst;
        }
        self::applyMandatoryFilter($request, $dm, $auth, $payload);
        $compiled = FilterParser::compile(FilterParser::normalize($request->filter), $table);
        return $compiled !== '' ? $compiled : '1';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function applyMandatoryAssign(
        array &$attributes,
        string $resourceName,
        Datamodel $dm,
        array $auth,
        \stdClass $payload
    ): void {
        if (self::shouldBypassMandatoryFilter($auth, $payload)) {
            return;
        }

        $config = $dm->get_config($resourceName);
        $assign = $config['mandatoryAssign'] ?? null;
        if (!is_array($assign)) {
            return;
        }

        foreach ($assign as $column => $template) {
            if (!is_string($template)) {
                continue;
            }
            $value = self::substituteClaims($template, $payload);
            if (strpos($value, '{{') !== false) {
                continue;
            }
            $attributes[$column] = $value;
        }
    }
}
