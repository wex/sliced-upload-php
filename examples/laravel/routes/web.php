<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Route all methods to same callback
Route::any('/sliced-upload', function() {

    // Initialize redis datastore
    $ds = new \SlicedUpload\Datastore\Redis(new \Redis());

    // Initialize sliced upload
    $t = new \SlicedUpload\SlicedUpload($ds);
    
    // Receive upload
    $t->receive(function ($tempFile) {
    
        // Delete existing output file
        Storage::disk('public')->delete('output.mp4');
    
        // Rename temp file to output file
        Storage::disk('public')->move($tempFile, 'output.mp4');
        
    });

// Disable CSRF token verification for this route - we are using built-in nonces.
})->withoutMiddleware([
    VerifyCsrfToken::class,
]);