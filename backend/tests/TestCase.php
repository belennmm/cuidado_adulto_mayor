<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
echo get_class($app) . PHP_EOL;