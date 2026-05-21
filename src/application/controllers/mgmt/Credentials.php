<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Credentials extends MY_MgmtController
{
    public function rotate($apiId)
    {
        if (!$this->store->apiExists($apiId)) {
            $this->mgmtError(404, $this->errorsCatalog['config']['api_not_found']);
        }
        if (!$this->isGlobalManagementKey()) {
            $this->requireApiAccess($apiId);
        } else {
            $this->requireManagementKey();
        }
        $path = "{$this->store->getApiDir($apiId)}/{$this->configFiles['admin_config']}";
        $admin = $this->store->loadPhp($path);
        $admin['secret'] = bin2hex(random_bytes(32));
        $this->store->savePhp($path, $admin);
        HttpResp::json_out(200, [
            'managementCredential' => [
                'secret' => $admin['secret'],
                'header' => 'X-Api-Config-Key',
                'note' => 'Shown only once. Store securely.',
            ],
        ]);
    }
}
