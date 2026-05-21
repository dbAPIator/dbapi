<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/HttpResp.php';

class Errors extends CI_Controller {

    public function home()
    {
        HttpResp::json_out(200, [
            'service' => 'dbAPI',
            'management' => '/mgmt/v1/apis',
            'data' => '/v1/apis/{apiId}/data',
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