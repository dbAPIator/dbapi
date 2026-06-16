<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'helpers/deployment_helper.php';
require_once APPPATH . 'third_party/dbAPI/Autoloader.php';
\dbAPI\Autoloader::register();

use dbAPI\Config\DBWalk;

/**
 * Auto-provisions the default API for single-deployment (Docker) mode.
 */
class SingleModeProvisioner
{
    /** @var CI_Controller */
    private $ci;
    /** @var MgmtConfigStore */
    private $store;
    /** @var MgmtLifecycle */
    private $lifecycle;
    /** @var Utilities */
    private $utilities;
    /** @var array */
    private $configFiles;

    public function __construct(
        CI_Controller $ci,
        MgmtConfigStore $store,
        MgmtLifecycle $lifecycle,
        Utilities $utilities,
        array $configFiles
    ) {
        $this->ci = $ci;
        $this->store = $store;
        $this->lifecycle = $lifecycle;
        $this->utilities = $utilities;
        $this->configFiles = $configFiles;
    }

    /**
     * @return array{provisioned:bool,message:string,apiId?:string}
     */
    public function provisionIfNeeded(): array
    {
        if (!is_single_deployment_mode()) {
            return ['provisioned' => false, 'message' => 'Not in single deployment mode'];
        }

        $apiId = default_api_id();
        if ($this->isReady($apiId)) {
            return ['provisioned' => false, 'message' => 'Default API already active', 'apiId' => $apiId];
        }

        $provisioned = false;
        if (!$this->store->apiExists($apiId)) {
            $this->store->scaffoldDraft($apiId, $this->buildInitialMeta($apiId));
            $provisioned = true;
        }

        $connection = single_mode_connection_from_env();
        if ($connection !== null) {
            $this->store->saveConnection($apiId, $connection);
            $provisioned = true;
        }

        if ($connection === null) {
            return [
                'provisioned' => $provisioned,
                'message' => 'Default API provisioned (draft): set DB_HOST and DB_NAME or PUT /mgmt/v1/connection',
                'apiId' => $apiId,
            ];
        }

        try {
            $this->runConnectionTest($apiId);
        } catch (Throwable $e) {
            return [
                'provisioned' => $provisioned,
                'message' => 'Default API provisioned (draft): database not reachable (' . $e->getMessage() . ')',
                'apiId' => $apiId,
            ];
        }

        try {
            $structure = $this->generateStructure($apiId);
            $this->saveStructure($apiId, $structure);
        } catch (Throwable $e) {
            return [
                'provisioned' => $provisioned,
                'message' => 'Default API provisioned (draft): schema build failed (' . $e->getMessage() . ')',
                'apiId' => $apiId,
            ];
        }

        if (count($structure) === 0) {
            $this->store->setStatus($apiId, 'draft');
            return [
                'provisioned' => $provisioned,
                'message' => 'Default API provisioned (draft): no tables or views found; POST /mgmt/v1/schema:rebuild when ready',
                'apiId' => $apiId,
            ];
        }

        $result = $this->lifecycle->validate($apiId);
        if (!$result['ready']) {
            $this->store->setStatus($apiId, 'draft');
            return [
                'provisioned' => $provisioned,
                'message' => 'Default API provisioned (draft): validation incomplete; complete setup via Management API',
                'apiId' => $apiId,
            ];
        }

        $this->store->setStatus($apiId, 'active');
        return ['provisioned' => true, 'message' => 'Default API provisioned and activated', 'apiId' => $apiId];
    }

    private function buildInitialMeta(string $apiId): array
    {
        $meta = single_mode_meta_from_env($apiId);
        $meta['id'] = $this->utilities->short_uuid();
        if (!isset($meta['description'])) {
            $meta['description'] = 'Auto-provisioned default API (single deployment mode)';
        }
        return $meta;
    }

    private function isReady(string $apiId): bool
    {
        if (!$this->store->apiExists($apiId)) {
            return false;
        }
        if ($this->store->getStatus($apiId) !== 'active') {
            return false;
        }
        $structurePath = "{$this->store->getApiDir($apiId)}/{$this->configFiles['structure']}";
        if (!is_file($structurePath)) {
            return false;
        }
        $structure = @include $structurePath;
        return is_array($structure) && count($structure) > 0;
    }

    private function runConnectionTest(string $apiId): void
    {
        $conn = @include "{$this->store->getApiDir($apiId)}/connection.php";
        $db = @$this->ci->load->database($conn, true);
        $err = $db->error();
        if ($err['code'] !== 0) {
            throw new RuntimeException($err['message'] ?? 'Database connection failed');
        }
        $this->store->recordConnectionTest($apiId, true);
    }

    private function generateStructure(string $apiId): array
    {
        $dir = $this->store->getApiDir($apiId);
        $conn = @include "{$dir}/connection.php";
        if (!is_array($conn) || empty($conn)) {
            throw new RuntimeException('Connection not configured');
        }
        $db = @$this->ci->load->database($conn, true);
        $err = $db->error();
        if ($err['code'] !== 0) {
            throw new RuntimeException($err['message'] ?? 'Database connection failed');
        }
        $structure = DBWalk::parse($db, $conn['database'])['structure'];

        $patchFile = "{$dir}/patch.php";
        if (is_file($patchFile)) {
            $patch = @include $patchFile;
            if (is_array($patch)) {
                if (!function_exists('smart_array_merge_recursive')) {
                    $this->ci->load->helper('config_util');
                }
                $structure = smart_array_merge_recursive($structure, schema_patch_apply_overrides($patch));
            }
        }

        return $structure;
    }

    private function saveStructure(string $apiId, array $structure): void
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
        if (count($structure) > 0) {
            $this->regenerateOpenApiSpec($apiId, $structure);
        }
    }

    private function regenerateOpenApiSpec(string $apiId, ?array $structure = null): void
    {
        $dir = $this->store->getApiDir($apiId);
        $this->ci->load->helper('swagger');
        require_once APPPATH . 'helpers/deployment_helper.php';
        write_api_openapi_spec($apiId, $dir, api_public_base_url($this->ci->config), $structure);
        $meta = $this->store->loadMeta($apiId);
        $meta['schema']['openapiGeneratedAt'] = gmdate('Y-m-d\TH:i:s\Z');
        unset($meta['schema']['openapiError']);
        $this->store->saveMeta($apiId, $meta);
    }
}
