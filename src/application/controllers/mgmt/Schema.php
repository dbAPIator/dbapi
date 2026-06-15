<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Schema extends MY_MgmtController
{
    public function introspect($apiId)
    {
        $this->requireApiAccess($apiId);
        try {
            $structure = $this->generateStructure($apiId);
            $snapPath = "{$this->store->getApiDir($apiId)}/schema_introspected.json";
            file_put_contents($snapPath, json_encode($structure, JSON_PRETTY_PRINT));
            $meta = $this->store->loadMeta($apiId);
            $meta['schema']['introspectedAt'] = gmdate('Y-m-d\TH:i:s\Z');
            $this->store->saveMeta($apiId, $meta);
            HttpResp::json_out(200, [
                'introspectedAt' => $meta['schema']['introspectedAt'],
                'entityCount' => count($structure),
            ]);
        } catch (Exception $e) {
            $this->mgmtError(400, ['code' => 2003, 'message' => $e->getMessage()]);
        }
    }

    public function get_introspected($apiId)
    {
        $this->requireApiAccess($apiId);
        $snapPath = "{$this->store->getApiDir($apiId)}/schema_introspected.json";
        if (!is_file($snapPath)) {
            try {
                $structure = $this->generateStructure($apiId);
                HttpResp::json_out(200, ['entities' => $structure]);
                return;
            } catch (Exception $e) {
                $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found']);
            }
        }
        HttpResp::json_out(200, json_decode(file_get_contents($snapPath), true));
    }

    public function get_overrides($apiId)
    {
        $this->requireApiAccess($apiId);
        $patch = $this->store->loadPhp("{$this->store->getApiDir($apiId)}/patch.php");
        HttpResp::json_out(200, $patch ?: new stdClass());
    }

    public function put_overrides($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = $this->validatePayload();
        $data = json_decode(json_encode($payload), true);
        $this->store->savePhp("{$this->store->getApiDir($apiId)}/patch.php", $data);
        $this->store->touchUpdated($apiId);
        HttpResp::json_out(200, $data);
    }

    public function patch_overrides($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = $this->validatePayload();
        $existing = $this->store->loadPhp("{$this->store->getApiDir($apiId)}/patch.php");
        $merged = smart_array_merge_recursive($existing, json_decode(json_encode($payload), true));
        $this->store->savePhp("{$this->store->getApiDir($apiId)}/patch.php", $merged);
        $this->store->touchUpdated($apiId);
        HttpResp::json_out(200, $merged);
    }

    public function get_effective($apiId)
    {
        $this->requireApiAccess($apiId);
        try {
            $structure = $this->generateStructure($apiId);
            HttpResp::json_out(200, ['entities' => $structure]);
        } catch (Exception $e) {
            $this->mgmtError(400, ['code' => 2003, 'message' => $e->getMessage()]);
        }
    }

    public function rebuild($apiId)
    {
        $this->requireApiAccess($apiId);
        try {
            $built = $this->rebuildStructureFromDatabase($apiId);
            $this->saveStructure($apiId, $built['structure']);
            $meta = $this->store->loadMeta($apiId);
            $meta['schema']['lastWarnings'] = $built['warnings'] ?? [];
            $this->store->saveMeta($apiId, $meta);

            HttpResp::json_out(200, [
                'rebuiltAt' => gmdate('Y-m-d\TH:i:s\Z'),
                'entityCount' => count($built['structure']),
                'warnings' => $built['warnings'] ?? [],
            ]);
        } catch (Exception $e) {
            $this->mgmtError(400, ['code' => 2003, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Introspect DB, write snapshot, rebuild structure.php (preserves relationship names).
     * Optional ?activate=true runs validation and activates in the same request.
     */
    public function sync($apiId)
    {
        $this->requireApiAccess($apiId);
        try {
            $structure = $this->generateStructure($apiId);
            $snapPath = "{$this->store->getApiDir($apiId)}/schema_introspected.json";
            file_put_contents($snapPath, json_encode($structure, JSON_PRETTY_PRINT));

            $built = $this->rebuildStructureFromDatabase($apiId);
            $this->saveStructure($apiId, $built['structure']);
            $meta = $this->store->loadMeta($apiId);
            $meta['schema']['lastWarnings'] = $built['warnings'] ?? [];
            $this->store->saveMeta($apiId, $meta);

            $result = [
                'syncedAt' => gmdate('Y-m-d\TH:i:s\Z'),
                'introspectedAt' => $meta['schema']['introspectedAt'] ?? null,
                'entityCount' => count($built['structure']),
                'warnings' => $built['warnings'] ?? [],
            ];

            if ($this->wantsActivate()) {
                $activation = $this->tryActivateApi($apiId);
                $result['activation'] = $activation;
                if (!$activation['activated']) {
                    $this->mgmtError(409, $this->errorsCatalog['config']['not_ready_for_activate'], [
                        'validation' => $activation['validation'],
                        'sync' => $result,
                    ]);
                }
            }

            HttpResp::json_out(200, $result);
        } catch (Exception $e) {
            $this->mgmtError(400, ['code' => 2003, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Regenerate cached openapi.json from structure.php (+ patch) without full DB rebuild.
     */
    public function regenerate_openapi($apiId)
    {
        $this->requireApiAccess($apiId);
        $dir = $this->store->getApiDir($apiId);
        $structPath = "{$dir}/{$this->configFiles['structure']}";
        if (!is_file($structPath)) {
            $this->mgmtError(400, ['code' => 2003, 'message' => 'structure.php missing; run schema:rebuild first']);
        }

        try {
            $this->regenerateOpenApiSpec($apiId);
        } catch (Throwable $e) {
            $this->mgmtError(400, ['code' => 2003, 'message' => $e->getMessage()]);
        }

        require_once APPPATH . 'libraries/OpenApiSpecValidator.php';
        $this->load->helper('swagger');
        $validation = OpenApiSpecValidator::validateFile(openapi_spec_path($dir));
        $meta = $this->store->loadMeta($apiId);

        HttpResp::json_out(200, [
            'regeneratedAt' => $meta['schema']['openapiGeneratedAt'] ?? gmdate('Y-m-d\TH:i:s\Z'),
            'path' => openapi_spec_filename(),
            'validation' => $validation,
        ]);
    }

    /**
     * OpenAPI cache status (does not return full spec; use data API GET .../swagger for that).
     */
    public function get_openapi($apiId)
    {
        $this->requireApiAccess($apiId);
        $dir = $this->store->getApiDir($apiId);
        $this->load->helper('swagger');
        $path = openapi_spec_path($dir);
        $meta = $this->store->loadMeta($apiId);

        require_once APPPATH . 'libraries/OpenApiSpecValidator.php';
        $validation = is_file($path) ? OpenApiSpecValidator::validateFile($path) : [
            'valid' => false,
            'errors' => ['openapi.json not found'],
            'warnings' => [],
            'summary' => [],
        ];

        $structPath = "{$dir}/{$this->configFiles['structure']}";
        $stale = false;
        if (is_file($path) && is_file($structPath)) {
            $stale = (filemtime($structPath) ?: 0) > (filemtime($path) ?: 0);
        }

        HttpResp::json_out(200, [
            'exists' => is_file($path),
            'file' => openapi_spec_filename(),
            'sizeBytes' => is_file($path) ? filesize($path) : 0,
            'generatedAt' => $meta['schema']['openapiGeneratedAt'] ?? null,
            'error' => $meta['schema']['openapiError'] ?? null,
            'stale' => $stale,
            'validation' => $validation,
            'swaggerUrl' => $this->baseUrl() . '/apis/' . rawurlencode($apiId) . '/swagger',
        ]);
    }
}
