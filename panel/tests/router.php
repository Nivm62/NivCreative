<?php
// Router for `php -S` during development/tests only (production uses .htaccess).
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__);
if (preg_match('#^/(src|config|database|lang|views|storage|bin|tests|tools|docs|connector)(/|$)#', $path) || preg_match('#\.(sql|md|lock|log|py|sh)$#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
$file = $root . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
