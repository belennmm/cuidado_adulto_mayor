<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

try {
    $app = require 'bootstrap/app.php';
    
    // Simular request
    $request = Illuminate\Http\Request::capture();
    $app->instance('request', $request);
    
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo 'Bootstrap OK' . PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo 'En: ' . $e->getFile() . ' línea ' . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}