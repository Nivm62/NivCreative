<?php
declare(strict_types=1);

// CLI installer: php bin/install.php --db-host=localhost --db-name=x --db-user=y --db-pass=z --admin-email=a@b.c --admin-pass='Secret123' [--admin-name=Niv] [--base-url=https://nivcreative.com/panel]
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('NIVC_PANEL_VERSION', '1.0.0');
require dirname(__DIR__) . '/src/autoload.php';
use Nivc\Core\{Auth, Config, Db, I18n};
use Nivc\Controllers\Web\InstallController;

I18n::setLocale('he');
$o = getopt('', ['db-host::', 'db-port::', 'db-name:', 'db-user:', 'db-pass::', 'admin-email:', 'admin-pass:', 'admin-name::', 'base-url::', 'env::']);
foreach (['db-name', 'db-user', 'admin-email', 'admin-pass'] as $req) {
    if (empty($o[$req])) {
        fwrite(STDERR, "Missing --$req\n");
        exit(1);
    }
}
if (Config::installed()) {
    fwrite(STDERR, "Already installed.\n");
    exit(1);
}
if (!Auth::validPasswordRule((string) $o['admin-pass'])) {
    fwrite(STDERR, "Admin password must be 8+ chars with a letter and a number.\n");
    exit(1);
}
$db = ['host' => $o['db-host'] ?? 'localhost', 'port' => (int) ($o['db-port'] ?? 3306), 'name' => $o['db-name'], 'user' => $o['db-user'], 'pass' => $o['db-pass'] ?? '', 'charset' => 'utf8mb4'];
$pdo = Db::connect($db);
InstallController::runSchema($pdo);
$key = base64_encode(random_bytes(32));
Config::set(['db' => $db, 'app_key' => $key]);
$now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jerusalem')))->format('Y-m-d H:i:s');
if (!Db::val("SELECT id FROM users WHERE role = 'admin' LIMIT 1")) {
    Db::insert('users', ['client_id' => null, 'role' => 'admin', 'email' => strtolower($o['admin-email']), 'password_hash' => Auth::hashPassword($o['admin-pass']),
        'name' => $o['admin-name'] ?? 'Admin', 'locale' => 'he', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
}
$cfg = ['env' => $o['env'] ?? 'production', 'timezone' => 'Asia/Jerusalem', 'default_locale' => 'he', 'app_key' => $key, 'base_url' => rtrim((string) ($o['base-url'] ?? ''), '/'), 'db' => $db];
file_put_contents(Config::root() . '/config/config.php', "<?php\nreturn " . var_export($cfg, true) . ";\n");
chmod(Config::root() . '/config/config.php', 0640);
file_put_contents(Config::root() . '/storage/installed.lock', date('c'));
echo "Installed.\n";
