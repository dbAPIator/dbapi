<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/mgmt/Apis.php';

/**
 * Legacy POST /apis → quick-create with deprecation headers.
 */
class Legacy extends Apis
{
    public function create_api()
    {
        header('Deprecation: true');
        header('Link: </mgmt/v1/apis>; rel="successor-version"');
        header('Sunset: Sat, 01 Jan 2027 00:00:00 GMT');
        $_GET['provision'] = 'immediate';
        $this->requireManagementKey();
        if (($this->config->item('deployment_mode') ?? 'multi') === 'single') {
            $this->mgmtError(409, $this->errorsCatalog['config']['single_mode_no_create']);
        }
        $payload = $this->readCreatePayload();
        $apiId = $payload->name ?? null;
        if (!$apiId) {
            $this->mgmtError(400, $this->errorsCatalog['config']['db_name_not_provided']);
        }
        if ($this->store->apiExists($apiId)) {
            $this->mgmtError(409, $this->errorsCatalog['config']['api_exists']);
        }
        try {
            $this->provisionImmediate($apiId, $payload);
        } catch (Exception $e) {
            $this->mgmtError(400, ['code' => 2003, 'message' => $e->getMessage()]);
        }
    }
}
