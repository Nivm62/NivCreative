# Deployment (Hostinger shared hosting)

Requirements: PHP 8.1+ with `pdo_mysql` and `openssl` or `sodium`; `mbstring`, `ctype` and `curl` are optional (polyfills/fallbacks included). MySQL/MariaDB.

1. hPanel → **Databases → MySQL Databases**: create a database + user, note the host (usually `localhost`).
2. hPanel → **File Manager** → `public_html/panel` (it already exists as an empty folder): upload `nivcreative-panel.zip`
   and **Extract** *into that folder* so `public_html/panel/index.php` exists. Delete the zip.
3. Open `https://nivcreative.com/panel/install` (or `https://panel.nivcreative.com/install`): database details + the first
   administrator. The installer creates the schema, writes `config/config.php` (chmod 640) and locks itself
   (`storage/installed.lock`). From then on `/install` is a 404.
   *Do this immediately after uploading — until it runs, anyone who finds the URL could install.*
4. hPanel → **Advanced → Cron Jobs** → every 10 minutes:
   `php /home/<user>/domains/nivcreative.com/public_html/panel/bin/cron.php`
   (without it, maintenance still runs opportunistically when someone opens a dashboard).
5. Add the client sites: **Clients → Add client** (optionally with website + landing page) or **Websites → Add website**,
   then install the Connector plugin / tracker tag (see `INTEGRATION.md`).
6. Optional: support contacts under **Settings**.

## Updates

Upload the new files over the old ones (keep `config/config.php` and `storage/`), then run `php bin/migrate.php`
(or open nothing — new releases document any required migration). Data is never touched by file uploads.

## Caching

The panel sends `Cache-Control: no-store`, `X-LiteSpeed-Cache-Control: no-cache` and `.htaccess` contains `CacheDisable public /`.
If you use the LiteSpeed Cache plugin on the main WordPress site also add `/panel` to *Cache → Excludes → Do Not Cache URIs*.
Static assets under `/panel/assets` are cache-busted (`?v=<mtime>`) and cached for a year.

## Sub-domain vs sub-folder

The same files work at `/public_html/panel` served as `https://nivcreative.com/panel` **and** as the document root of
`panel.nivcreative.com`. Choose one as canonical (set `base_url` in `config/config.php`, e.g. `https://nivcreative.com/panel`, so
e-mailed links and the tracker snippets use it) and redirect the other.

## Behind a CDN / proxy

If the real visitor IP is not in `REMOTE_ADDR`, set `'ip_header' => 'HTTP_CF_CONNECTING_IP'` (or the header your proxy sets) in
`config/config.php` — otherwise rate limits and login throttling see the proxy's IP.

## Backups

Everything lives in the database plus `config/config.php`. Use hPanel backups or `mysqldump` nightly.
`storage/logs/` holds application logs (rotated monthly by file name).

## Troubleshooting

| Symptom | Check |
|---|---|
| 500 on first load | PHP version ≥ 8.1; `storage/` and `config/` writable; `storage/logs/app-YYYY-MM.log` |
| Login loops back | Cookies blocked; `base_url` host differs from the URL you use |
| Cron mails nothing | Panel only creates in-app notifications today |
| Reset e-mails missing | `mail()` is used; set a real `mail_from` on your domain (SPF/DKIM) |
