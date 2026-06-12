<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/HttpResp.php';
require_once APPPATH . 'libraries/RequestContext.php';

class Errors extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        RequestContext::init();
    }

    public function home()
    {
        $this->load->config('dbapiator');
        if (($this->config->item('deployment_mode') ?? 'multi') === 'single') {
            $apiId = $this->config->item('default_api_id') ?: 'default';
            HttpResp::json_out(200, [
                'service' => 'dbAPI',
                'deploymentMode' => 'single',
                'management' => '/mgmt/v1/apis/' . $apiId,
                'managementOpenApi' => '/management-openapi.yaml',
                'data' => '/v1/data',
                'auth' => '/v1/auth',
                'openApi' => '/v1/swagger',
            ]);
            return;
        }

        HttpResp::json_out(200, [
            'service' => 'dbAPI',
            'deploymentMode' => 'multi',
            'management' => '/mgmt/v1/apis',
            'managementOpenApi' => '/management-openapi.yaml',
            'data' => '/v1/apis/{apiId}/data',
            'deprecated' => [
                'adminApi' => 'Removed. Use /mgmt/v1/apis instead of /admin/apis.',
            ],
        ]);
    }

    /**
     * Legacy Admin API — removed; all configuration uses Management API.
     */
    public function deprecated_admin()
    {
        HttpResp::json_out(410, [
            'error' => [
                'code' => 'admin_api_removed',
                'message' => 'The Admin API (/admin/apis) has been removed. Use the Management API at /mgmt/v1/apis.',
                'documentation' => '/docs/management_api.md',
                'openApi' => '/management-openapi.yaml',
                'migration' => [
                    'listApis' => 'GET /mgmt/v1/apis',
                    'createApi' => 'POST /mgmt/v1/apis',
                    'authHeader' => 'X-Management-Key (instance) or X-Api-Config-Key (per API)',
                ],
            ],
        ]);
    }

    public function error_404()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 404 Not Found");
        echo json_encode([
            "error" => "404 Not Found",
            "message" => "The page you are looking for does not exist."
        ]);
        exit;
    }

    public function error_500()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode([
            "error" => "500 Internal Server Error",
            "message" => "An internal server error occurred."
        ]);
        exit;
    }

    public function error_503()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 503 Service Unavailable");
        echo json_encode([
            "error" => "503 Service Unavailable",
            "message" => "The service is currently unavailable."
        ]);
        exit;
    }

    public function error_504()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 504 Gateway Timeout");
        echo json_encode([
            "error" => "504 Gateway Timeout",
            "message" => "The request timed out."
        ]);
        exit;
    }

    public function error_509()
    {
        header("Content-Type: application/json");
        header("HTTP/1.1 509 Bandwidth Limit Exceeded");
        echo json_encode([
            "error" => "509 Bandwidth Limit Exceeded",
            "message" => "The bandwidth limit has been exceeded."
        ]);
        exit;
    }
}
