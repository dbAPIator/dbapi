<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Data extends MY_MgmtController
{
    public function get($apiId)
    {
        $this->requireApiAccess($apiId);
        HttpResp::json_out(200, $this->store->dataAclsToNetworkPolicy($apiId));
    }

    public function update($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = $this->validatePayload();
        $policy = json_decode(json_encode($payload), true);
        $path = "{$this->store->getApiDir($apiId)}/{$this->configFiles['data_api_acls']}";
        $existing = $this->store->loadPhp($path);
        $merged = $this->store->networkPolicyToDataAcls($policy, $existing);
        $this->store->savePhp($path, $merged);
        $this->store->touchUpdated($apiId);
        HttpResp::json_out(200, $policy);
    }
}
