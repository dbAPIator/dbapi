<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Persistence helpers for Management API (configs_dir/{apiId}/).
 */
class MgmtConfigStore
{
    private $configDir;
    private $configFiles;

    public function __construct(string $configDir, array $configFiles)
    {
        $this->configDir = rtrim($configDir, '/');
        $this->configFiles = $configFiles;
    }

    public function getApiDir(string $apiId): string
    {
        return "{$this->configDir}/{$apiId}";
    }

    public function apiExists(string $apiId): bool
    {
        return is_dir($this->getApiDir($apiId));
    }

    /** @return list<string> */
    public function listApiIds(): array
    {
        if (!is_dir($this->configDir)) {
            return [];
        }
        $ids = [];
        foreach (scandir($this->configDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($this->getApiDir($entry))) {
                $ids[] = $entry;
            }
        }
        sort($ids);
        return $ids;
    }

    public function scaffoldDraft(string $apiId, array $meta): void
    {
        $dir = $this->getApiDir($apiId);
        if (is_dir($dir)) {
            throw new RuntimeException('API already exists', 409);
        }
        mkdir($dir, 0777, true);
        $this->setStatus($apiId, 'draft');
        $this->saveMeta($apiId, $meta);
        $this->savePhp("{$dir}/connection.php", []);
        $this->savePhp("{$dir}/patch.php", []);
        $this->savePhp("{$dir}/{$this->configFiles['auth']}", ['mode' => 'none', 'allowGuest' => true]);
        $this->saveDefaultDataAcls($apiId);
        $this->savePhp("{$dir}/{$this->configFiles['admin_config']}", $this->defaultAdminConfig());
    }

    public function defaultAdminConfig(): array
    {
        return [
            'acls' => [
                ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'allow' => true],
                ['ip' => '0.0.0.0/0', 'allow' => false],
            ],
            'secret' => bin2hex(random_bytes(32)),
        ];
    }

    public function saveDefaultDataAcls(string $apiId): void
    {
        $this->savePhp("{$this->getApiDir($apiId)}/{$this->configFiles['data_api_acls']}", [
            'IP' => [
                ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'allow' => true],
                ['ip' => '0.0.0.0/0', 'allow' => false],
            ],
            'path' => [
                ['pattern' => '/*', 'methods' => 'GET', 'allow' => true],
                ['pattern' => '/*', 'methods' => 'OPTIONS', 'allow' => true],
                ['pattern' => '/*', 'methods' => '*', 'allow' => false],
            ],
        ]);
    }

    public function loadMeta(string $apiId): array
    {
        $path = "{$this->getApiDir($apiId)}/meta.php";
        if (!is_file($path)) {
            return [];
        }
        $data = @include $path;
        return is_array($data) ? $data : [];
    }

    public function saveMeta(string $apiId, array $meta): void
    {
        $this->savePhp("{$this->getApiDir($apiId)}/meta.php", $meta);
    }

    public function getStatus(string $apiId): string
    {
        $path = "{$this->getApiDir($apiId)}/status.php";
        if (!is_file($path)) {
            return 'draft';
        }
        $status = @include $path;
        return is_string($status) ? $status : 'draft';
    }

    public function setStatus(string $apiId, string $status): void
    {
        file_put_contents(
            "{$this->getApiDir($apiId)}/status.php",
            "<?php\nreturn " . var_export($status, true) . ";\n"
        );
    }

    public function buildApiResource(string $apiId): array
    {
        $meta = $this->loadMeta($apiId);
        $dir = $this->getApiDir($apiId);
        $conn = @include "{$dir}/connection.php";
        $configured = is_array($conn) && !empty($conn['database'] ?? null);

        return array_merge($meta, [
            'id' => $meta['id'] ?? $apiId,
            'name' => $meta['name'] ?? $apiId,
            'status' => $this->getStatus($apiId),
            'connection' => [
                'configured' => $configured,
                'lastTest' => $meta['connection']['lastTest'] ?? null,
            ],
            'policies' => [
                'configNetworkConfigured' => is_file("{$dir}/{$this->configFiles['admin_config']}"),
                'dataNetworkConfigured' => is_file("{$dir}/{$this->configFiles['data_api_acls']}"),
            ],
            'schema' => [
                'introspectedAt' => $meta['schema']['introspectedAt'] ?? null,
                'effectiveVersion' => is_file("{$dir}/{$this->configFiles['structure']}")
                    ? ($meta['schema']['effectiveVersion'] ?? '1')
                    : null,
            ],
        ]);
    }

    public function touchUpdated(string $apiId): void
    {
        $meta = $this->loadMeta($apiId);
        $meta['updatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
        $this->saveMeta($apiId, $meta);
    }

    public function recordConnectionTest(string $apiId, bool $ok, ?string $message = null): void
    {
        $meta = $this->loadMeta($apiId);
        $meta['connection'] = $meta['connection'] ?? [];
        $meta['connection']['lastTest'] = [
            'status' => $ok ? 'ok' : 'fail',
            'at' => gmdate('Y-m-d\TH:i:s\Z'),
            'message' => $message,
        ];
        $this->saveMeta($apiId, $meta);
    }

    public function connectionToDisk($payload): array
    {
        $p = json_decode(json_encode($payload), true);
        $driver = $p['driver'] ?? 'mysql';
        if ($driver !== 'mysql') {
            throw new InvalidArgumentException('Only driver "mysql" is supported');
        }
        $port = $p['port'] ?? 3306;
        $host = $p['host'] ?? 'localhost';
        return [
            'dbdriver' => 'mysqli',
            'hostname' => strpos($host, ':') !== false ? $host : "{$host}:{$port}",
            'username' => $p['username'] ?? '',
            'password' => $p['password'] ?? '',
            'database' => $p['database'] ?? '',
        ];
    }

    public function connectionFromDisk(string $apiId, bool $maskPassword = true): ?array
    {
        $path = "{$this->getApiDir($apiId)}/connection.php";
        if (!is_file($path)) {
            return null;
        }
        $conn = @include $path;
        if (!is_array($conn) || empty($conn)) {
            return null;
        }
        $host = $conn['hostname'] ?? 'localhost';
        $port = 3306;
        if (preg_match('/^(.+):(\d+)$/', $host, $m)) {
            $host = $m[1];
            $port = (int) $m[2];
        }
        $out = [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $conn['database'] ?? '',
            'username' => $conn['username'] ?? '',
        ];
        $out['password'] = $maskPassword ? '***********' : ($conn['password'] ?? '');
        return $out;
    }

    public function saveConnection(string $apiId, $payload): void
    {
        $this->savePhp("{$this->getApiDir($apiId)}/connection.php", $this->connectionToDisk($payload));
        $this->touchUpdated($apiId);
    }

    public function loadPhp(string $path)
    {
        if (!is_file($path)) {
            return [];
        }
        $data = @include $path;
        return is_array($data) ? $data : [];
    }

    public function savePhp(string $path, $data): void
    {
        $res = file_put_contents($path, to_php_code($data, true));
        if ($res === false) {
            throw new RuntimeException("Could not write {$path}");
        }
        @opcache_invalidate($path, true);
        @chmod($path, 0666);
    }

    public function deleteApi(string $apiId): void
    {
        remove_dir_recursive($this->getApiDir($apiId));
    }

    public function renameApi(string $apiId, string $newApiId): void
    {
        rename($this->getApiDir($apiId), $this->getApiDir($newApiId));
    }

    public function networkPolicyToAdminConfig(array $policy): array
    {
        $admin = $this->defaultAdminConfig();
        $admin['defaultAction'] = $policy['defaultAction'] ?? 'allow';
        if (!empty($policy['rules'])) {
            $admin['acls'] = [];
            foreach ($policy['rules'] as $rule) {
                $admin['acls'][] = [
                    'ip' => $rule['cidr'] ?? $rule['ip'] ?? '0.0.0.0/0',
                    'allow' => ($rule['action'] ?? 'allow') === 'allow',
                ];
            }
        }
        return $admin;
    }

    public function adminConfigToNetworkPolicy(string $apiId): array
    {
        $admin = $this->loadPhp("{$this->getApiDir($apiId)}/{$this->configFiles['admin_config']}");
        $rules = [];
        foreach ($admin['acls'] ?? [] as $acl) {
            $rules[] = [
                'cidr' => $acl['ip'] ?? '0.0.0.0/0',
                'action' => !empty($acl['allow']) ? 'allow' : 'deny',
            ];
        }
        return [
            'defaultAction' => $admin['defaultAction'] ?? 'allow',
            'rules' => $rules,
        ];
    }

    public function networkPolicyToDataAcls(array $policy, ?array $existing = null): array
    {
        $acls = $existing ?? [
            'path' => [['pattern' => '/*', 'methods' => '*', 'allow' => false]],
        ];
        $acls['IP'] = [];
        foreach ($policy['rules'] ?? [] as $rule) {
            $acls['IP'][] = [
                'ip' => $rule['cidr'] ?? $rule['ip'] ?? '0.0.0.0/0',
                'allow' => ($rule['action'] ?? 'allow') === 'allow',
            ];
        }
        if (empty($acls['IP'])) {
            $acls['IP'] = [
                ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'allow' => true],
                ['ip' => '0.0.0.0/0', 'allow' => ($policy['defaultAction'] ?? 'deny') === 'allow'],
            ];
        }
        if (!empty($policy['path']) && is_array($policy['path'])) {
            $acls['path'] = [];
            foreach ($policy['path'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $acls['path'][] = [
                    'pattern' => $rule['pattern'] ?? '/*',
                    'methods' => $rule['methods'] ?? '*',
                    'allow' => !empty($rule['allow']),
                ];
            }
        }
        return $acls;
    }

    public function dataAclsToNetworkPolicy(string $apiId): array
    {
        $acls = $this->loadPhp("{$this->getApiDir($apiId)}/{$this->configFiles['data_api_acls']}");
        $rules = [];
        foreach ($acls['IP'] ?? [] as $acl) {
            $rules[] = [
                'cidr' => $acl['ip'] ?? '0.0.0.0/0',
                'action' => !empty($acl['allow']) ? 'allow' : 'deny',
            ];
        }
        return ['defaultAction' => 'deny', 'rules' => $rules];
    }
}
