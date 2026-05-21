<?php

/**
 * Structural validation for generated data-plane OpenAPI documents (openapi.json).
 */
class OpenApiSpecValidator
{
    /**
     * @return array{valid:bool,errors:array<int,string>,warnings:array<int,string>,summary:array<string,mixed>}
     */
    public static function validate(array $spec): array
    {
        $errors = [];
        $warnings = [];

        $version = $spec['openapi'] ?? null;
        if (!is_string($version) || $version === '') {
            $errors[] = 'Missing required field: openapi';
        } elseif (!preg_match('/^3\.\d+\.\d+$/', $version) && !preg_match('/^3\.\d+$/', $version)) {
            $errors[] = "Unsupported OpenAPI version: {$version} (expected 3.x)";
        }

        if (empty($spec['info']) || !is_array($spec['info'])) {
            $errors[] = 'Missing required object: info';
        } else {
            if (empty($spec['info']['title'])) {
                $errors[] = 'Missing required field: info.title';
            }
            if (empty($spec['info']['version'])) {
                $warnings[] = 'Missing info.version (recommended)';
            }
        }

        if (empty($spec['paths']) || !is_array($spec['paths'])) {
            $errors[] = 'Missing or invalid paths object';
        } else {
            $pathCount = count($spec['paths']);
            if ($pathCount === 0) {
                $errors[] = 'paths must contain at least one entry';
            }
            $hasOperation = false;
            foreach ($spec['paths'] as $path => $item) {
                if (!is_string($path) || $path === '' || $path[0] !== '/') {
                    $warnings[] = "Path key should start with /: {$path}";
                }
                if (!is_array($item)) {
                    continue;
                }
                foreach (['get', 'post', 'patch', 'put', 'delete'] as $method) {
                    if (isset($item[$method])) {
                        $hasOperation = true;
                        break 2;
                    }
                }
            }
            if ($pathCount > 0 && !$hasOperation) {
                $errors[] = 'paths defines no HTTP operations (get/post/patch/delete)';
            }
        }

        if (empty($spec['components']) || !is_array($spec['components'])) {
            $warnings[] = 'Missing components object (schemas may be incomplete)';
        } elseif (empty($spec['components']['schemas']) || !is_array($spec['components']['schemas'])) {
            $warnings[] = 'Missing components.schemas';
        }

        if (empty($spec['servers']) || !is_array($spec['servers'])) {
            $warnings[] = 'Missing servers array (clients may not resolve base URL)';
        } else {
            $first = $spec['servers'][0]['url'] ?? '';
            if (!is_string($first) || $first === '') {
                $warnings[] = 'servers[0].url is empty';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'openapi' => $version,
                'pathCount' => isset($spec['paths']) && is_array($spec['paths']) ? count($spec['paths']) : 0,
                'schemaCount' => isset($spec['components']['schemas']) && is_array($spec['components']['schemas'])
                    ? count($spec['components']['schemas']) : 0,
            ],
        ];
    }

    /**
     * @return array{valid:bool,errors:array<int,string>,warnings:array<int,string>,summary:array<string,mixed>}|null
     */
    public static function validateFile(string $path): ?array
    {
        if (!is_file($path) || filesize($path) < 3) {
            return [
                'valid' => false,
                'errors' => ['File missing or empty: ' . $path],
                'warnings' => [],
                'summary' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [
                'valid' => false,
                'errors' => ['Invalid JSON in ' . basename($path)],
                'warnings' => [],
                'summary' => [],
            ];
        }

        return self::validate($decoded);
    }
}
