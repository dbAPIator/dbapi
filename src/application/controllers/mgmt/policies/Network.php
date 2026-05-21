<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Network extends MY_MgmtController
{
    public function get($apiId)
    {
        $this->requireApiAccess($apiId);
        HttpResp::json_out(200, $this->store->adminConfigToNetworkPolicy($apiId));
    }

    public function update($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = $this->validatePayload();
        $policy = json_decode(json_encode($payload), true);
        $admin = $this->store->networkPolicyToAdminConfig($policy);
        $path = "{$this->store->getApiDir($apiId)}/{$this->configFiles['admin_config']}";
        $existing = $this->store->loadPhp($path);
        $admin['secret'] = $existing['secret'] ?? bin2hex(random_bytes(32));
        $this->store->savePhp($path, $admin);
        $this->store->touchUpdated($apiId);
        HttpResp::json_out(200, $policy);
    }
}
