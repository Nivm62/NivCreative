<?php
declare(strict_types=1);

// NivCreative Panel – front controller. Works from /panel (sub-folder) or from a sub-domain root.
define('NIVC_PANEL_VERSION', '1.0.0');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1 or newer is required.');
}
require __DIR__ . '/src/autoload.php';
Nivc\Core\App::run();
