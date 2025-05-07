<?php

// --- Global Error Handlers ---

set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_PARSE])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fatal error: ' . $error['message']]);
    }
});

// --- Actual code (simulate DB error) ---

$mysqli = new mysqli('mysql', 'wronguser', 'wrongpass', 'nonexistent');

if ($mysqli->connect_error) {
    throw new Exception('Database connection failed: ' . $mysqli->connect_error);
}

// Dummy response (should not get here)
echo json_encode(['status' => 'ok']);