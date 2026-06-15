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
        $activation = $this->tryActivateApi($apiId);
        if (!$activation['activated']) {
            $this->mgmtError(409, $this->errorsCatalog['config']['not_ready_for_activate'], [
                'validation' => $activation['validation'],
            ]);
        }
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
