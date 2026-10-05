# Running the panel as a WordPress plugin (served at `/app`)

`release/nivcreative-panel-wp.zip` is the same panel wrapped in a WordPress plugin (`wp-plugin/nivcreative-panel/` is the thin loader).

## Install

1. WordPress admin → **Plugins → Add New → Upload Plugin** → choose `nivcreative-panel-wp.zip` → **Install** → **Activate**.
   Activation creates the tables (prefix `nivp_`) in the site's own database — no DB credentials to enter.
2. **NivCreative Panel** (new admin menu entry) → *Panel administrator* → name, e-mail, password → **Save**.
3. Open `https://your-site/app/` and sign in with that account. Panel accounts are independent of WordPress users.
4. Add clients, then install the **NivCreative Connector** (download button on the same admin page) on each client website.

## How it works

* `plugins_loaded` priority 0 inspects the URL; for `/app` and `/app/*` it stops WordPress, runs the panel (same code, same security tests)
  and exits, so no theme, Elementor or WooCommerce code runs for panel requests.
* Static files (`app/assets/*`) are served by the web server directly from the plugin folder; the app and API responses are `no-store`.
* Configuration is derived from WordPress: DB constants, site timezone, `admin_email` (sender), and a random `nivp_app_key` option.
* Sessions, logs and the cache live in `wp-content/nivcreative-panel-data/` (denied by `.htaccess`), outside the plugin folder, so plugin
  updates never touch them. Plugin updates re-run the idempotent schema + SQL migrations once (`nivp_version` option).
* Maintenance (expiring subscriptions, waiting leads, silent websites) runs from WP-Cron every 10 minutes (`nivp_maintenance`).
  For exact timing add a real cron job calling `wp-cron.php`.
* Password-reset e-mails are sent with `wp_mail()` (works with SMTP plugins).

## Notes & limits

* A WordPress page/post with the slug `app` becomes unreachable (the panel wins). Change `NIVP_PATH` in `wp-config.php`
  (`define('NIVP_PATH', 'panel');`) *before* activating to use another path.
* Deactivating or deleting the plugin keeps all data. To wipe it deliberately add `define('NIVP_DELETE_DATA', true);` to `wp-config.php`
  before deleting the plugin — this drops the `nivp_*` tables.
* The panel never reads or writes WordPress tables (only its own `nivp_*` tables) and WordPress logins grant no access to it.
* If a page-cache plugin is installed, no configuration is needed: responses carry `Cache-Control: no-store` and
  `X-LiteSpeed-Cache-Control: no-cache`. If you ever see stale panel data, add `/app` to the cache plugin's "never cache URIs".
* Requires PHP 8.1+ with `pdo_mysql` and either `openssl` or `sodium` (encryption falls back from libsodium to OpenSSL AES-256-GCM automatically).
  `mbstring`, `ctype` and `curl` are optional — the panel ships polyfills/fallbacks for hosts that leave them disabled. Activation checks this and explains what is missing.
