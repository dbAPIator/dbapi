<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Utilities.php';
require_once APPPATH . 'libraries/MgmtConfigStore.php';
require_once APPPATH . 'libraries/MgmtLifecycle.php';
require_once APPPATH . 'libraries/SingleModeProvisioner.php';
require_once APPPATH . 'helpers/deployment_helper.php';

/**
 * CLI tasks (Docker entrypoint bootstrap).
 */
class Cli extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) {
            show_error('CLI only', 403);
        }
        $this->config->load('dbapiator');
        $this->load->helper('string');
        $this->load->helper('config_util');
    }

    /**
     * Auto-provision default API when DEPLOYMENT_MODE=single.
     */
    public function provision()
    {
        $method = $this->uri->segment(3) ?: 'run';
        if ($method !== 'run') {
            fwrite(STDERR, "Unknown provision action: {$method}\n");
            exit(1);
        }

        if (!is_single_deployment_mode()) {
            echo "DEPLOYMENT_MODE is not single; skipping auto-provision.\n";
            exit(0);
        }

        $store = new MgmtConfigStore(
            $this->config->item('configs_dir'),
            $this->config->item('files')
        );
        $lifecycle = new MgmtLifecycle($store, $this->config->item('files'));
        $utilities = new Utilities();
        $provisioner = new SingleModeProvisioner(
            $this,
            $store,
            $lifecycle,
            $utilities,
            $this->config->item('files')
        );

        try {
            $result = $provisioner->provisionIfNeeded();
            echo $result['message'] . "\n";
            exit($result['provisioned'] ? 0 : 0);
        } catch (Throwable $e) {
            fwrite(STDERR, 'Auto-provision failed: ' . $e->getMessage() . "\n");
            exit(1);
        }
    }
}
