<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 10/18/18
 * Time: 12:05 PM
 */
require_once(APPPATH."libraries/HttpResp.php");
require_once(APPPATH."libraries/RequestContext.php");
require_once(APPPATH."libraries/ApiSafety.php");
require_once(APPPATH."third_party/dbAPI/Autoloader.php");
require_once (BASEPATH."/../vendor/autoload.php");

use dbAPI\Autoloader;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JSONApi\Document;
use dbAPI\API\RateLimiter;
use dbAPI\API\DBAPIRequest;
use dbAPI\API\Datamodel;

Autoloader::register();

/**
 * create_multiple_records
 * update_multiple_records
 * delete_multiple_records
 *
 * get_multiple_records
 * get_single_record
 *
 * create_single_record
 * update_single_record
 * delete_single_record
 * get_relationship
 * create_relationship
 * update_relationship
 * delete_relationship
 */

/**
 * Class Dbapi controller: JSON:API data plane (MySQL/MariaDB)
 * @property CI_Config config
 * @property CI_Loader load
 * @property CI_Input input
 * @property  Utilities utilities
 */
require_once APPPATH . 'core/traits/dbapi/DbapiInitTrait.php';
require_once APPPATH . 'core/traits/dbapi/DbapiDiscoveryTrait.php';
require_once APPPATH . 'core/traits/dbapi/DbapiInputTrait.php';
require_once APPPATH . 'core/traits/dbapi/DbapiQueryTrait.php';
require_once APPPATH . 'core/traits/dbapi/DbapiReadTrait.php';
require_once APPPATH . 'core/traits/dbapi/DbapiWriteTrait.php';
require_once APPPATH . 'core/traits/dbapi/DbapiRelationsTrait.php';
require_once APPPATH . 'core/traits/dbapi/DbapiMiscTrait.php';

/**
 * REST data API controller. Logic is split across traits under core/traits/dbapi/.
 *
 * @property CI_Config config
 * @property CI_Loader load
 * @property CI_Input input
 * @property Utilities utilities
 */
class Dbapi extends CI_Controller
{
    use DbapiInitTrait;
    use DbapiDiscoveryTrait;
    use DbapiInputTrait;
    use DbapiQueryTrait;
    use DbapiReadTrait;
    use DbapiWriteTrait;
    use DbapiRelationsTrait;
    use DbapiMiscTrait;

    /**
     * @var CI_DB_pdo_driver
     */
    private $apiDb;

    /**
     * @var Datamodel
     */
    private $apiDm;

    /**
     * @var array
     */
    private $apiSettings;

    /**
     * @var \dbAPI\API\Records
     */
    private $recs;
    /**
     * @var string|null
     */
    private $deployment_type;
    /**
     * @var mixed
     */
    private $inputData;
    private $baseUrl;
    private $basePath;
    private $noLinksInOutput = false;
    /**
     * @var array
     */
    private $JsonApiDocOptions = [
        /**
         * @var bool when true do not include links in output
         */
        "nolinks"=>true,
        /**
         * @var bool
         */
        "minify"=>false
    ];

    /**
     * @var int set recursion level for creating new records
     * set to 0 to disable it
     */
    protected $insertMaxRecursionLevel=5;
    /**
     * @var bool
     */
    private $debug=false;
    /**
     * @var string
     */
    private $apiConfigDir;
    /**
     * @var array
     */
    private $errors;

    private $rateLimiter;
    private $configFiles;
    private $configDir;

    function get_max_insert_recursions()
    {
        return $this->insertMaxRecursionLevel;
    }

  
    function __construct ()
    {
        parent::__construct();
        $this->config->load("dbapiator");
        RequestContext::init();
        ApiSafety::configure($this->config);
        $this->configFiles = $this->config->item("files");
        $this->configDir = $this->config->item("configs_dir");

        $this->deployment_type = $this->config->item("deployment_type");

        $this->load->helper("my_utils");
        $this->load->helper("config_util");
        $this->load->helper("dbapi_request");

        $this->load->config("errorscatalog");
        $this->errors = $this->config->item("errors");

        // TODO: implement CORS control
        //header("Access-Control-Allow-Origin: *");


        // $this->_init();
        // $this->rateLimiter = new RateLimiter();
        
        // Optional: Configure different limits
        // $this->rateLimiter->setLimit(100, 60); // 100 requests per minute
    }
}
