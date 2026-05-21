<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Hooks extends MY_MgmtController
{
    private function loadStructure(string $apiId): array
    {
        $path = "{$this->store->getApiDir($apiId)}/{$this->configFiles['structure']}";
        if (!is_file($path)) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found'], ['reason' => 'structure missing']);
        }
        return @include $path;
    }

    private function saveStructure(string $apiId, array $structure): void
    {
        $this->store->savePhp(
            "{$this->store->getApiDir($apiId)}/{$this->configFiles['structure']}",
            $structure
        );
        $this->regenerateOpenApiSpec($apiId, $structure);
        $this->store->touchUpdated($apiId);
    }

    private function extractAllHooks(array $structure): array
    {
        $out = [];
        foreach ($structure as $entity => $cfg) {
            if (!empty($cfg['hooks'])) {
                $out[$entity] = $cfg['hooks'];
            }
        }
        return $out;
    }

    public function list_all($apiId)
    {
        $this->requireApiAccess($apiId);
        HttpResp::json_out(200, $this->extractAllHooks($this->loadStructure($apiId)));
    }

    public function replace_all($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->mgmtError(400, $this->errorsCatalog['input']['invalid_json']);
        }
        $structure = $this->loadStructure($apiId);
        foreach ($structure as $entity => &$cfg) {
            unset($cfg['hooks']);
        }
        unset($cfg);
        foreach ($payload as $entity => $hooks) {
            if (!isset($structure[$entity])) {
                $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found'], ['entity' => $entity]);
            }
            $structure[$entity]['hooks'] = $this->validateHooks($hooks);
        }
        $this->saveStructure($apiId, $structure);
        HttpResp::json_out(200, $this->extractAllHooks($structure));
    }

    public function upsert_entity($apiId, $entityName)
    {
        $this->requireApiAccess($apiId);
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->mgmtError(400, $this->errorsCatalog['input']['invalid_json']);
        }
        $structure = $this->loadStructure($apiId);
        if (!isset($structure[$entityName])) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found'], ['entity' => $entityName]);
        }
        $structure[$entityName]['hooks'] = $this->validateHooks($payload);
        $this->saveStructure($apiId, $structure);
        HttpResp::json_out(200, $structure[$entityName]['hooks']);
    }

    public function delete_entity($apiId, $entityName)
    {
        $this->requireApiAccess($apiId);
        $structure = $this->loadStructure($apiId);
        if (!isset($structure[$entityName])) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found'], ['entity' => $entityName]);
        }
        unset($structure[$entityName]['hooks']);
        $this->saveStructure($apiId, $structure);
        HttpResp::no_content(204);
    }

    private function validateHooks(array $hooks): array
    {
        $allowed = ['create', 'update', 'delete'];
        $out = [];
        foreach ($hooks as $event => $list) {
            if (!in_array($event, $allowed, true)) {
                $this->mgmtError(400, $this->errorsCatalog['input']['invalid_input_data'], ['event' => $event]);
            }
            if (!is_array($list)) {
                $this->mgmtError(400, $this->errorsCatalog['input']['invalid_input_data']);
            }
            $out[$event] = [];
            foreach ($list as $hook) {
                if (empty($hook['url']) || !filter_var($hook['url'], FILTER_VALIDATE_URL)) {
                    $this->mgmtError(400, $this->errorsCatalog['input']['invalid_input_data'], ['url' => $hook['url'] ?? null]);
                }
                $method = strtoupper($hook['method'] ?? 'POST');
                if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], true)) {
                    $this->mgmtError(400, $this->errorsCatalog['input']['invalid_input_data'], ['method' => $method]);
                }
                $out[$event][] = [
                    'url' => $hook['url'],
                    'method' => $method,
                    'headers' => is_array($hook['headers'] ?? null) ? $hook['headers'] : [],
                    'body' => $hook['body'] ?? null,
                ];
            }
        }
        return $out;
    }
}
