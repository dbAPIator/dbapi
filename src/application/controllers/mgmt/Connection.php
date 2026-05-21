<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Connection extends MY_MgmtController
{
    public function get($apiId)
    {
        $this->requireApiAccess($apiId);
        $conn = $this->store->connectionFromDisk($apiId, true);
        if ($conn === null) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found'], ['reason' => 'connection not set']);
        }
        HttpResp::json_out(200, $conn);
    }

    public function update($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = $this->validatePayload();
        $this->store->saveConnection($apiId, $payload);
        HttpResp::json_out(200, $this->store->connectionFromDisk($apiId, true));
    }

    public function test($apiId)
    {
        $this->requireApiAccess($apiId);
        $conn = @include "{$this->store->getApiDir($apiId)}/connection.php";
        if (!is_array($conn) || empty($conn)) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found']);
        }
        $start = microtime(true);
        try {
            $db = @$this->load->database($conn, true);
            $err = $db->error();
            if ($err['code'] !== 0) {
                $this->store->recordConnectionTest($apiId, false, $err['message']);
                HttpResp::json_out(200, [
                    'status' => 'fail',
                    'at' => gmdate('Y-m-d\TH:i:s\Z'),
                    'latencyMs' => (microtime(true) - $start) * 1000,
                    'message' => $err['message'],
                ]);
                return;
            }
            $this->store->recordConnectionTest($apiId, true);
            HttpResp::json_out(200, [
                'status' => 'ok',
                'at' => gmdate('Y-m-d\TH:i:s\Z'),
                'latencyMs' => round((microtime(true) - $start) * 1000, 2),
                'message' => null,
            ]);
        } catch (Exception $e) {
            $this->store->recordConnectionTest($apiId, false, $e->getMessage());
            HttpResp::json_out(200, [
                'status' => 'fail',
                'at' => gmdate('Y-m-d\TH:i:s\Z'),
                'latencyMs' => round((microtime(true) - $start) * 1000, 2),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
