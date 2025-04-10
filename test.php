<?php

// Autoloader

use SlicedUpload\SlicedUpload;

require_once __DIR__ . '/php/vendor/autoload.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS for all
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, HEAD, OPTIONS, PATCH, DELETE");

// Handle OPTIONS requests for preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle request parsing
if (function_exists('request_parse_body')) {
    // Use request_parse_body if PHP 8.4+
    if (in_array($_SERVER['REQUEST_METHOD'], ['PATCH', 'PUT', 'DELETE'])) {
        [$_POST, $_FILES] = request_parse_body();
    }
} else {
    // Use _method to override request method
    if (isset($_POST['_method']) && in_array($_POST['_method'], ['PATCH', 'PUT', 'DELETE'])) {
        $_SERVER['REQUEST_METHOD'] = $_POST['_method'];
    }
}

// Server endpoint
SlicedUpload::process(function ($tempFile) {
    // Process chunk
});