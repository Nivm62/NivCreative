<?php
// Test helper: makes CLI scripts (seed / e2e) talk to a panel that is embedded in WordPress (prefixed tables, injected config).
// Usage: NIVC_EMBED_TEST=1 NIVC_DB_NAME=wp NIVC_DB_USER=wp NIVC_DB_PASS=wp NIVC_DB_HOST=127.0.0.1 NIVC_APP_KEY=base64 php tests/e2e.php http://127.0.0.1:8081/app
use Nivc\Core\Config;

Config::embedded([
    'env' => 'local', 'timezone' => 'Asia/Jerusalem', 'default_locale' => 'he', 'app_key' => (string) getenv('NIVC_APP_KEY'),
    'base_url' => (string) getenv('NIVC_BASE_URL'),
    'db' => ['host' => getenv('NIVC_DB_HOST') ?: '127.0.0.1', 'port' => 3306, 'name' => getenv('NIVC_DB_NAME'), 'user' => getenv('NIVC_DB_USER'), 'pass' => getenv('NIVC_DB_PASS'), 'charset' => 'utf8mb4', 'prefix' => 'nivp_'],
], (string) getenv('NIVC_STORAGE'));
