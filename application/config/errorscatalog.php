<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 9/23/19
 * Time: 9:39 AM
 */

$errors = [];
$errors["1"] = [
    "code"=>"1",
    "title"=>"Invalid JSON input data not allowed",
    "description"=>"A POST request has been submitted by the client with an invalid JSONAPI payload",
    "solution"=>"Use a valid JSONAPI document as payload for creating a new record."
];
$errors["2"] = [
    "code"=>"2",
    "title"=>"No API ID detected",
    "description"=>"A request has been made on an undefind",
    "solution"=>"Use a valid JSONAPI document as payload for creating a new record."
];

$config["errors"] = $errors;