<?php
/**
 * Swagger / OpenAPI helper entry point.
 *
 * Loads Schemas, Paths, and SpecBuilder modules. CodeIgniter loads this file
 * via $this->load->helper('swagger').
 */
require_once __DIR__ . '/swagger/Schemas.php';
require_once __DIR__ . '/swagger/Paths.php';
require_once __DIR__ . '/swagger/SpecBuilder.php';
