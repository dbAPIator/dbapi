<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$autoload = defined('BASEPATH') ? BASEPATH . '/../vendor/autoload.php' : __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once APPPATH . 'libraries/HttpResp.php';
require_once APPPATH . 'libraries/Utilities.php';
require_once APPPATH . 'libraries/OpenApiBodyValidator.php';
require_once APPPATH . 'libraries/MgmtConfigStore.php';
require_once APPPATH . 'libraries/MgmtLifecycle.php';
require_once APPPATH . 'libraries/RequestContext.php';
require_once APPPATH . 'third_party/dbAPI/Autoloader.php';
\dbAPI\Autoloader::register();

use dbAPI\Config\DBWalk;

/**
 * Base controller for Management API (/mgmt/v1).
 */
class MY_MgmtController extends CI_Controller
{
    protected $errorsCatalog;
    protected $configFiles;
    protected $configDir;
    protected $utilities;
    /** @var MgmtConfigStore */
    protected $store;
    /** @var MgmtLifecycle */
    protected $lifecycle;
    protected $headers;
    protected $openApiPath;

    public function __construct()
    {
        parent::__construct();
        RequestContext::init();
        header('Content-Type: application/json');
        $this->config->load('dbapiator');
        $this->utilities = new Utilities();
        $this->load->helper('string');
        $this->load->helper('config_util');
        $this->load->config('errorscatalog');
        $this->errorsCatalog = $this->config->item('errors_catalog');
        $this->configFiles = $this->config->item('files');
        $this->configDir = $this->config->item('configs_dir');
        $this->headers = function_exists('getallheaders') ? getallheaders() : [];
        $this->store = new MgmtConfigStore($this->configDir, $this->configFiles);
        $this->lifecycle = new MgmtLifecycle($this->store, $this->configFiles);
        $this->openApiPath = APPPATH . '../public/management-openapi.json';
    }

    protected function headerValue(string ...$names): ?string
    {
        foreach ($names as $name) {
            foreach ($this->headers as $k => $v) {
                if (strcasecmp($k, $name) === 0) {
                    return $v;
                }
            }
            $q = $this->input->get($name);
            if ($q !== null && $q !== '') {
                return $q;
            }
        }
        return null;
    }

    protected function requireManagementKey(): void
    {
        $secret = $this->headerValue(
            'X-Management-Key',
            'x-management-key',
            'X-Admin-API-Key',
            'x-api-key',
            'X-Api-Key'
        );
        if (!$secret || $secret !== $this->config->item('config_api_secret')) {
            $this->mgmtError(401, $this->errorsCatalog['access']['secret_not_authorized']);
        }
        $ipsAcl = $this->config->item('config_api_ips_acls');
        if (!$this->utilities->IP_is_allowed($ipsAcl)) {
            $this->mgmtError(401, $this->errorsCatalog['access']['ip_not_authorized']);
        }
    }

    protected function requireApiAccess(string $apiId): void
    {
        if (!$this->store->apiExists($apiId)) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found'], ['apiId' => $apiId]);
        }
        if ($this->isGlobalManagementKey()) {
            return;
        }
        $admin = $this->store->loadPhp("{$this->store->getApiDir($apiId)}/{$this->configFiles['admin_config']}");
        if (empty($admin['acls']) || !$this->utilities->IP_is_allowed($admin['acls'])) {
            $this->mgmtError(401, $this->errorsCatalog['access']['ip_not_authorized']);
        }
        $secret = $this->headerValue('X-Api-Config-Key', 'x-api-config-key', 'x-api-key', 'X-Api-Key');
        if (!$secret || ($admin['secret'] ?? '') !== $secret) {
            $this->mgmtError(401, $this->errorsCatalog['access']['api_config_secret_not_authorized']);
        }
    }

    protected function isGlobalManagementKey(): bool
    {
        $secret = $this->headerValue(
            'X-Management-Key',
            'x-management-key',
            'X-Admin-API-Key',
            'x-api-key',
            'X-Api-Key'
        );
        return $secret && $secret === $this->config->item('config_api_secret');
    }

    protected function mgmtError(int $httpCode, array $catalogEntry, ?array $details = null): void
    {
        HttpResp::json_out($httpCode, [
            'error' => [
                'code' => (int) ($catalogEntry['code'] ?? $httpCode),
                'message' => $catalogEntry['message'] ?? 'Error',
                'details' => $details,
            ],
        ]);
    }

    /**
     * Path relative to the app root for OpenAPI matching (strips Apache subdir e.g. /dbapi/src).
     */
    protected function openApiRequestPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base && $base !== '/' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        return $path;
    }

    protected function validatePayload(): object
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->mgmtError(400, $this->errorsCatalog['input']['invalid_json']);
        }
        if (!is_file($this->openApiPath)) {
            return $payload;
        }
        $validator = new OpenApiBodyValidator($this->openApiPath);
        $path = $this->openApiRequestPath();
        try {
            $validator->validate($_SERVER['REQUEST_METHOD'], $path, $payload);
        } catch (InvalidArgumentException $e) {
            $this->mgmtError(400, $this->errorsCatalog['input']['invalid_input_data'], [
                'validation' => json_decode($e->getMessage(), true),
            ]);
        } catch (RuntimeException $e) {
            $this->mgmtError(500, ['code' => 5000, 'message' => $e->getMessage()]);
        }
        return $payload;
    }

    protected function wantsImmediateProvision(): bool
    {
        if ($this->input->get('provision') === 'immediate') {
            return true;
        }
        $prefer = $this->headerValue('Prefer') ?? '';
        return stripos($prefer, 'immediate-provision') !== false;
    }

    protected function baseUrl(): string
    {
        return rtrim($this->config->item('base_url') ?: '', '/');
    }

    protected function generateStructure(string $apiId, $structure = null): array
    {
        $dir = $this->store->getApiDir($apiId);
        $conn = @include "{$dir}/connection.php";
        if (!is_array($conn) || empty($conn)) {
            throw new RuntimeException('Connection not configured');
        }
        if ($structure === null) {
            $db = @$this->load->database($conn, true);
            $err = $db->error();
            if ($err['code'] !== 0) {
                throw new RuntimeException($err['message']);
            }
            $structure = DBWalk::parse($db, $conn['database'])['structure'];
        }
        $patchFile = "{$dir}/patch.php";
        if (is_file($patchFile)) {
            $patch = @include $patchFile;
            if (is_array($patch)) {
                $structure = smart_array_merge_recursive($structure, $patch);
            }
        }
        return $structure;
    }

    protected function saveStructure(string $apiId, array $structure): void
    {
        $this->store->savePhp(
            "{$this->store->getApiDir($apiId)}/{$this->configFiles['structure']}",
            $structure
        );
        $meta = $this->store->loadMeta($apiId);
        $meta['schema']['introspectedAt'] = gmdate('Y-m-d\TH:i:s\Z');
        $meta['schema']['effectiveVersion'] = (string) ((int) ($meta['schema']['effectiveVersion'] ?? 0) + 1);
        $this->store->saveMeta($apiId, $meta);
        $this->store->touchUpdated($apiId);
        $this->regenerateOpenApiSpec($apiId, $structure);
    }

    /**
     * Regenerate cached openapi.json (structure.php + patch.php unless structure provided).
     */
    protected function regenerateOpenApiSpec(string $apiId, ?array $structure = null): void
    {
        $dir = $this->store->getApiDir($apiId);
        if (!is_dir($dir)) {
            return;
        }
        $this->load->helper('swagger');
        try {
            write_api_openapi_spec($apiId, $dir, $this->baseUrl(), $structure);
            $meta = $this->store->loadMeta($apiId);
            $meta['schema']['openapiGeneratedAt'] = gmdate('Y-m-d\TH:i:s\Z');
            unset($meta['schema']['openapiError']);
            $this->store->saveMeta($apiId, $meta);
        } catch (Throwable $e) {
            $meta = $this->store->loadMeta($apiId);
            $meta['schema']['openapiError'] = $e->getMessage();
            unset($meta['schema']['openapiGeneratedAt']);
            $this->store->saveMeta($apiId, $meta);
            RequestContext::log('error', 'OpenAPI spec generation failed', [
                'apiId' => $apiId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
