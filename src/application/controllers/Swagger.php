<?php
require_once(APPPATH."libraries/HttpResp.php");
require_once(APPPATH."libraries/RequestContext.php");

/**
 * Serves OpenAPI specs: per-API from disk; management API from YAML (mode-aware).
 *
 * @property CI_Config config
 */
class Swagger extends CI_Controller {

    public function __construct() {
        parent::__construct();
        RequestContext::init();
        $this->load->config("dbapiator");
        $this->load->helper("swagger");
    }

    public function index($configName = null) {

        if ($configName === null || $configName === '') {
            HttpResp::exception_out(new Exception(
                'Invalid usage. Use GET /v1/swagger',
                400
            ));
        }

        $configDir = $this->config->item("configs_dir");
        $apiConfigDir = "$configDir/$configName";

        if(!is_dir($apiConfigDir)) {
            HttpResp::exception_out(new Exception("Invalid API config dir $apiConfigDir",500));
        }

        $spec = read_api_openapi_spec($apiConfigDir);
        if ($spec === null) {
            HttpResp::exception_out(new Exception(
                "OpenAPI spec not found for API '$configName'. Rebuild the schema (Management API) to generate openapi.json.",
                404
            ));
        }

        HttpResp::json_out(200, with_api_openapi_servers_url($spec, $configName));
    }

    public function management_json($variant = null)
    {
        try {
            $spec = prepare_mgmt_openapi_spec(null, $this->normalizeMgmtVariant($variant));
        } catch (RuntimeException $e) {
            HttpResp::exception_out(new Exception($e->getMessage(), 404));
        }
        HttpResp::json_out(200, $spec);
    }

    public function management_yaml($variant = null)
    {
        try {
            $yaml = mgmt_openapi_yaml_with_servers(null, $this->normalizeMgmtVariant($variant));
        } catch (RuntimeException $e) {
            HttpResp::exception_out(new Exception($e->getMessage(), 404));
        }
        HttpResp::quick(200, 'application/yaml', $yaml);
    }

    /**
     * @return 'multi'|'single'|null
     */
    private function normalizeMgmtVariant($variant): ?string
    {
        if ($variant === null || $variant === '') {
            return null;
        }
        if ($variant === 'multi' || $variant === 'single') {
            return $variant;
        }
        HttpResp::exception_out(new Exception('Unknown management OpenAPI variant', 404));
        return null;
    }
}
