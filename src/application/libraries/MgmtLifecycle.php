<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/OpenApiSpecValidator.php';

/**
 * Validate / activate readiness checks for Management API.
 */
class MgmtLifecycle
{
    private $store;
    private $configFiles;

    public function __construct(MgmtConfigStore $store, array $configFiles)
    {
        $this->store = $store;
        $this->configFiles = $configFiles;
    }

    public function validate(string $apiId): array
    {
        $checks = [];
        $dir = $this->store->getApiDir($apiId);
        $meta = $this->store->loadMeta($apiId);

        $conn = @include "{$dir}/connection.php";
        $connOk = is_array($conn) && !empty($conn['database']) && !empty($conn['hostname']);
        $checks[] = $this->check('connection.configured', $connOk ? 'ok' : 'fail',
            $connOk ? 'Connection configured' : 'Connection not configured');

        $lastTest = $meta['connection']['lastTest']['status'] ?? null;
        $testOk = $lastTest === 'ok';
        $checks[] = $this->check('connection.tested', $testOk ? 'ok' : 'fail',
            $testOk ? 'Connection test succeeded' : 'Run connection:test successfully');

        $structPath = "{$dir}/{$this->configFiles['structure']}";
        $struct = is_file($structPath) ? @include $structPath : null;
        $structOk = is_array($struct) && count($struct) > 0;
        $checks[] = $this->check('schema.structure', $structOk ? 'ok' : 'fail',
            $structOk ? 'structure.php present' : 'Rebuild schema after introspect');

        $admin = $this->store->loadPhp("{$dir}/{$this->configFiles['admin_config']}");
        $adminOk = !empty($admin['acls']);
        $checks[] = $this->check('policies.configNetwork', $adminOk ? 'ok' : 'fail',
            $adminOk ? 'Config network policy set' : 'Set policies/config-network');

        $dataAcls = $this->store->loadPhp("{$dir}/{$this->configFiles['data_api_acls']}");
        $dataOk = !empty($dataAcls['IP']);
        $checks[] = $this->check('policies.dataNetwork', $dataOk ? 'ok' : 'fail',
            $dataOk ? 'Data network policy set' : 'Set policies/data-network');

        $authPath = "{$dir}/{$this->configFiles['auth']}";
        $authOk = is_file($authPath);
        $checks[] = $this->check('policies.auth', $authOk ? 'ok' : 'fail',
            $authOk ? 'Auth policy present' : 'Set policies/auth');

        $hooksWarn = $this->hooksRedisWarn($apiId, $struct);
        $checks[] = $hooksWarn;

        $nameOk = ($meta['name'] ?? $apiId) === $apiId || !empty($meta['name']);
        $checks[] = $this->check('meta.name', $nameOk ? 'ok' : 'fail', 'API metadata name set');

        $checks[] = $this->checkOpenApiSpec($dir, $structOk, $structPath, $meta);

        $ready = true;
        foreach ($checks as $c) {
            if (in_array($c['id'], $this->requiredCheckIds(), true) && $c['status'] === 'fail') {
                $ready = false;
            }
        }

        return ['ready' => $ready, 'checks' => $checks];
    }

    private function requiredCheckIds(): array
    {
        return [
            'connection.configured',
            'connection.tested',
            'schema.structure',
            'policies.configNetwork',
            'policies.dataNetwork',
            'policies.auth',
            'meta.name',
            'schema.openapi',
        ];
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function checkOpenApiSpec(string $dir, bool $structOk, string $structPath, array $meta): array
    {
        if (!$structOk) {
            return $this->check('schema.openapi', 'fail', 'Generate structure before OpenAPI spec');
        }

        $openapiFile = $this->configFiles['openapi'] ?? 'openapi.json';
        $openapiPath = rtrim($dir, '/') . '/' . $openapiFile;
        $openapiError = $meta['schema']['openapiError'] ?? null;

        if (!empty($openapiError)) {
            return $this->check(
                'schema.openapi',
                'fail',
                'OpenAPI generation failed: ' . $openapiError
            );
        }

        if (!is_file($openapiPath) || filesize($openapiPath) < 3) {
            return $this->check(
                'schema.openapi',
                'fail',
                'openapi.json missing; run schema:rebuild to generate the cached spec'
            );
        }

        $validation = OpenApiSpecValidator::validateFile($openapiPath);
        if ($validation === null || !$validation['valid']) {
            $msg = !empty($validation['errors'])
                ? implode('; ', $validation['errors'])
                : 'openapi.json is invalid or empty';
            return $this->check('schema.openapi', 'fail', $msg);
        }

        if (!empty($validation['warnings'])) {
            return $this->check(
                'schema.openapi',
                'warn',
                'OpenAPI valid with warnings: ' . implode('; ', $validation['warnings'])
            );
        }

        if (is_file($structPath)) {
            $structMtime = filemtime($structPath) ?: 0;
            $specMtime = filemtime($openapiPath) ?: 0;
            if ($structMtime > $specMtime) {
                return $this->check(
                    'schema.openapi',
                    'warn',
                    'OpenAPI spec is older than structure.php; run schema:rebuild'
                );
            }
        }

        $summary = $validation['summary'];
        return $this->check(
            'schema.openapi',
            'ok',
            sprintf(
                'OpenAPI spec valid (%d paths, %d schemas)',
                (int) ($summary['pathCount'] ?? 0),
                (int) ($summary['schemaCount'] ?? 0)
            )
        );
    }

    private function hooksRedisWarn(string $apiId, $structure): array
    {
        $hasHooks = false;
        if (is_array($structure)) {
            foreach ($structure as $res) {
                if (!empty($res['hooks'])) {
                    $hasHooks = true;
                    break;
                }
            }
        }
        if (!$hasHooks) {
            return $this->check('hooks.redis', 'ok', 'No hooks configured');
        }
        $redisOk = !empty(getenv('REDIS_HOST')) || !empty($_ENV['REDIS_HOST'] ?? null);
        return $this->check('hooks.redis', $redisOk ? 'ok' : 'warn',
            $redisOk ? 'Redis configured' : 'Hooks present but REDIS_HOST not set');
    }

    private function check(string $id, string $status, string $message): array
    {
        return ['id' => $id, 'status' => $status, 'message' => $message];
    }
}
