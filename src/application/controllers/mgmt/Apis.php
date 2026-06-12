<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Apis extends MY_MgmtController
{
    public function list_apis()
    {
        $this->requireManagementKey();
        $limit = min(200, max(1, (int) ($this->input->get('limit') ?: 50)));
        $offset = max(0, (int) ($this->input->get('offset') ?: 0));
        $ids = $this->store->listApiIds();
        $total = count($ids);
        $slice = array_slice($ids, $offset, $limit);
        $items = [];
        foreach ($slice as $apiId) {
            $items[] = $this->store->buildApiResource($apiId);
        }
        HttpResp::json_out(200, [
            'items' => $items,
            'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => $total],
        ]);
    }

    public function create()
    {
        $this->requireManagementKey();
        if (($this->config->item('deployment_mode') ?? 'multi') === 'single') {
            $this->mgmtError(409, $this->errorsCatalog['config']['single_mode_no_create']);
        }
        $payload = $this->readCreatePayload();
        $apiId = $payload->name ?? null;
        if (!$apiId || !preg_match('/^[a-zA-Z0-9_\-]+$/', $apiId)) {
            $this->mgmtError(400, $this->errorsCatalog['input']['invalid_input_data'], ['field' => 'name']);
        }
        if ($this->store->apiExists($apiId)) {
            $this->mgmtError(409, $this->errorsCatalog['config']['api_exists']);
        }

        if ($this->wantsImmediateProvision()) {
            $this->provisionImmediate($apiId, $payload);
            return;
        }

        $id = $this->utilities->short_uuid();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $meta = [
            'id' => $id,
            'name' => $apiId,
            'description' => $payload->description ?? null,
            'contact' => json_decode(json_encode($payload->contact ?? null), true),
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
        $this->store->scaffoldDraft($apiId, $meta);
        $admin = $this->store->loadPhp("{$this->store->getApiDir($apiId)}/{$this->configFiles['admin_config']}");
        $api = $this->store->buildApiResource($apiId);
        $location = $this->baseUrl() . '/mgmt/v1/apis/' . rawurlencode($apiId);
        HttpResp::json_out(201, [
            'api' => $api,
            'managementCredential' => [
                'secret' => $admin['secret'] ?? '',
                'header' => 'X-Api-Config-Key',
                'note' => 'Shown only once. Store securely.',
            ],
        ], ['Location' => $location]);
    }

    public function get($apiId)
    {
        $this->requireApiAccess($apiId);
        HttpResp::json_out(200, $this->store->buildApiResource($apiId));
    }

    public function patch($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = $this->validatePayload();
        $meta = $this->store->loadMeta($apiId);
        $merged = smart_array_merge_recursive($meta, json_decode(json_encode($payload), true));
        if (isset($payload->name) && $payload->name !== $apiId) {
            $newId = $payload->name;
            if ($this->store->apiExists($newId)) {
                $this->mgmtError(409, $this->errorsCatalog['config']['api_exists']);
            }
            $this->store->renameApi($apiId, $newId);
            $apiId = $newId;
        }
        $merged['updatedAt'] = gmdate('Y-m-d\TH:i:s\Z');
        $this->store->saveMeta($apiId, $merged);
        HttpResp::json_out(200, $this->store->buildApiResource($apiId));
    }

    public function delete($apiId)
    {
        $this->requireManagementKey();
        if (!$this->store->apiExists($apiId)) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found']);
        }
        $status = $this->store->getStatus($apiId);
        $force = $this->input->get('force') === 'true' || $this->input->get('force') === '1';
        if ($status === 'active' && !$force) {
            $this->mgmtError(409, $this->errorsCatalog['config']['not_ready_for_activate'], [
                'hint' => 'Deactivate first or pass force=true',
            ]);
        }
        $this->store->deleteApi($apiId);
        HttpResp::no_content(204);
    }

    protected function readCreatePayload(): object
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->mgmtError(400, $this->errorsCatalog['input']['invalid_json']);
        }
        if (isset($payload->connection)) {
            $c = $payload->connection;
            if (isset($c->hostname) && !isset($c->host)) {
                $payload->connection = (object) [
                    'driver' => 'mysql',
                    'host' => $c->hostname,
                    'port' => $c->port ?? 3306,
                    'database' => $c->database ?? '',
                    'username' => $c->username ?? '',
                    'password' => $c->password ?? '',
                ];
            }
        }
        if (!$this->wantsImmediateProvision() && is_file($this->openApiPath)) {
            $validator = new OpenApiBodyValidator($this->openApiPath);
            $path = $this->openApiRequestPath();
            try {
                $validator->validate($_SERVER['REQUEST_METHOD'], $path, $payload);
            } catch (InvalidArgumentException $e) {
                $this->mgmtError(400, $this->errorsCatalog['input']['invalid_input_data'], [
                    'validation' => json_decode($e->getMessage(), true),
                ]);
            } catch (RuntimeException $e) {
                // Path not in spec (e.g. legacy /apis) — skip
            }
        }
        return $payload;
    }

    protected function provisionImmediate(string $apiId, object $payload): void
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $meta = [
            'id' => $this->utilities->short_uuid(),
            'name' => $apiId,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
        try {
            $this->store->scaffoldDraft($apiId, $meta);
            if (isset($payload->connection)) {
                $this->store->saveConnection($apiId, $payload->connection);
                $this->runConnectionTest($apiId);
            }
            if (!empty($payload->connection)) {
                $structure = $this->generateStructure($apiId);
                $this->saveStructure($apiId, $structure);
            }
            $result = $this->lifecycle->validate($apiId);
            if (!$result['ready']) {
                HttpResp::json_out(422, [
                    'api' => $this->store->buildApiResource($apiId),
                    'validation' => $result,
                ]);
                return;
            }
            $this->store->setStatus($apiId, 'active');
            $admin = $this->store->loadPhp("{$this->store->getApiDir($apiId)}/{$this->configFiles['admin_config']}");
            HttpResp::json_out(201, [
                'api' => $this->store->buildApiResource($apiId),
                'managementCredential' => [
                    'secret' => $admin['secret'] ?? '',
                    'header' => 'X-Api-Config-Key',
                    'note' => 'Shown only once. Store securely.',
                ],
            ], ['Location' => $this->baseUrl() . '/mgmt/v1/apis/' . rawurlencode($apiId)]);
        } catch (Exception $e) {
            if ($this->store->apiExists($apiId)) {
                $this->store->deleteApi($apiId);
            }
            $this->mgmtError(400, ['code' => 2003, 'message' => $e->getMessage()]);
        }
    }

    private function runConnectionTest(string $apiId): void
    {
        $conn = @include "{$this->store->getApiDir($apiId)}/connection.php";
        $db = @$this->load->database($conn, true);
        $err = $db->error();
        if ($err['code'] !== 0) {
            throw new RuntimeException($err['message']);
        }
        $this->store->recordConnectionTest($apiId, true);
    }
}
