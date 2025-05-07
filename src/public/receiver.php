<?php

$data = json_decode(file_get_contents('php://input'), true);

error_log(json_encode(["data"=>$data,"request"=>$_REQUEST])); 

echo json_encode($data);

?>