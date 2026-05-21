<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Lifecycle extends MY_MgmtController
{
    public function validate($apiId)
    {
        $this->requireApiAccess($apiId);
        HttpResp::json_out(200, $this->lifecycle->validate($apiId));
    }

    public function activate($apiId)
    {
        $this->requireApiAccess($apiId);
        if ($this->store->getStatus($apiId) === 'active') {
            HttpResp::json_out(200, $this->store->buildApiResource($apiId));
            return;
        }
        $result = $this->lifecycle->validate($apiId);
        if (!$result['ready']) {
            $this->mgmtError(409, $this->errorsCatalog['config']['not_ready_for_activate'], ['validation' => $result]);
        }
        $this->store->saveDefaultDataAcls($apiId);
        $dir = $this->store->getApiDir($apiId);
        $acls = $this->store->loadPhp("{$dir}/{$this->configFiles['data_api_acls']}");
        if (empty($acls['path'])) {
            $acls['path'] = [
                ['pattern' => '/*', 'methods' => 'GET', 'allow' => true],
                ['pattern' => '/*', 'methods' => 'OPTIONS', 'allow' => true],
                ['pattern' => '/*', 'methods' => '*', 'allow' => false],
            ];
            $this->store->savePhp("{$dir}/{$this->configFiles['data_api_acls']}", $acls);
        }
        $this->store->setStatus($apiId, 'active');
        $this->store->touchUpdated($apiId);
        HttpResp::json_out(200, $this->store->buildApiResource($apiId));
    }

    public function deactivate($apiId)
    {
        $this->requireApiAccess($apiId);
        $this->store->setStatus($apiId, 'inactive');
        $this->store->touchUpdated($apiId);
        HttpResp::json_out(200, $this->store->buildApiResource($apiId));
    }
}
