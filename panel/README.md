# NivCreative Panel

Central client & lead management platform: administrator panel + private client dashboards, Hebrew (RTL) by default with full
English (LTR) support. PHP 8.1+, MySQL, no framework, installable in `/public_html/panel`.

* **Install:** [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) · **Architecture:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) ·
  **Connect websites:** [docs/INTEGRATION.md](docs/INTEGRATION.md) · **Security:** [docs/SECURITY.md](docs/SECURITY.md)

## Develop

```bash
php bin/install.php --db-host=127.0.0.1 --db-name=panel --db-user=u --db-pass=p --admin-email=me@x.co --admin-pass='Secret123' --env=local
php bin/seed-demo.php                                  # fake data (env must be 'local')
php -S 127.0.0.1:8082 -t . tests/router.php           # dev server
php tests/unit.php && php tests/e2e.php http://127.0.0.1:8082
python3 tools/build_lang.py                            # regenerate lang/*.php from tools/i18n_table.py + verify keys
tools/build.sh                                         # minified production zip → build/nivcreative-panel.zip
```
