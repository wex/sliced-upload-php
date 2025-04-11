<?php

// Autoloader
require_once __DIR__ . '/vendor/autoload.php';

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

// Initialize datastore
//$ds = new \SlicedUpload\Datastore\Mysql(new PDO('mysql:host=localhost;dbname=t', 'root', ''));
$ds = new \SlicedUpload\Datastore\Redis(new \Redis());

// Initialize sliced upload
$t = new \SlicedUpload\SlicedUpload($ds);

// Receive upload
$t->receive(function ($tempFile) {

    // Delete existing output file
    @unlink('output.mp4');

    // Rename temp file to output file
    rename($tempFile, 'output.mp4');
    
});
