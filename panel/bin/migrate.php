<?php
declare(strict_types=1);

// Applies pending SQL migrations from database/migrations/*.sql (see README there).  php bin/migrate.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('NIVC_PANEL_VERSION', '1.0.0');
require dirname(__DIR__) . '/src/autoload.php';
use Nivc\Core\{Config, Db};
use Nivc\Services\Settings;
use Nivc\Controllers\Web\InstallController;

if (!Config::installed()) {
    fwrite(STDERR, "Panel is not installed.\n");
    exit(1);
}
// Make sure every base table exists (safe: IF NOT EXISTS).
InstallController::runSchema(Db::connect());
$applied = json_decode((string) Settings::get('migrations_applied', '[]'), true) ?: [];
$files = glob(Config::root() . '/database/migrations/*.sql') ?: [];
sort($files);
$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', (string) file_get_contents($file)) ?: [])) as $stmt) {
        Db::connect()->exec($stmt);
    }
    $applied[] = $name;
    Settings::set('migrations_applied', json_encode($applied));
    echo "applied $name\n";
    $count++;
}
echo $count ? "Done.\n" : "Nothing to migrate.\n";
