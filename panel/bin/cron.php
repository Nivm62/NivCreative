<?php
declare(strict_types=1);

// Periodic maintenance (notifications for expiring subscriptions, waiting leads, silent websites, lead spikes).
// Hostinger hPanel → Advanced → Cron Jobs → every 10 minutes:   php /home/USER/public_html/panel/bin/cron.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('NIVC_PANEL_VERSION', '1.0.0');
require dirname(__DIR__) . '/src/autoload.php';
use Nivc\Core\Config;
use Nivc\Core\I18n;
use Nivc\Services\MaintenanceService;

if (!Config::installed()) {
    fwrite(STDERR, "Panel is not installed.\n");
    exit(1);
}
date_default_timezone_set((string) Config::get('timezone', 'Asia/Jerusalem'));
I18n::setLocale('he');
echo json_encode(MaintenanceService::run()), "\n";
