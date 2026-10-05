#!/usr/bin/env bash
# Builds build/nivcreative-panel.zip: minified assets, no tests/dev data/secrets.
# Needs: php, python3, and (optionally) Node for esbuild minification — without Node the unminified assets are shipped.
set -euo pipefail
cd "$(dirname "$0")/.."
python3 tools/build_lang.py
php tests/unit.php
OUT=build/panel
rm -rf build && mkdir -p "$OUT"
rsync -a --exclude 'build' --exclude 'tests' --exclude 'tools' --exclude '.git' --exclude 'config/config.php' --exclude 'storage/installed.lock' \
  --exclude 'storage/logs/*.log' --exclude 'storage/sessions/sess_*' --exclude 'bin/seed-demo.php' --exclude 'node_modules' --exclude '*.zip' ./ "$OUT/"
mkdir -p "$OUT/storage/logs" "$OUT/storage/sessions" "$OUT/storage/cache"
if command -v npx >/dev/null 2>&1 && npx --no-install esbuild --version >/dev/null 2>&1 || command -v esbuild >/dev/null 2>&1; then
  ES="$(command -v esbuild || echo 'npx --no-install esbuild')"
  for f in $(find "$OUT/assets/js" -name '*.js'); do $ES "$f" --minify --target=es2017 --outfile="$f" --allow-overwrite --log-level=error; done
  $ES "$OUT/assets/css/app.css" --minify --outfile="$OUT/assets/css/app.css" --allow-overwrite --log-level=error
  echo "assets minified"
else
  echo "esbuild not found – shipping unminified assets (install with: npm i -g esbuild)"
fi
(cd build/panel && zip -qr ../nivcreative-panel.zip .)
ls -la build/nivcreative-panel.zip
# WordPress connector plugin (upload via Plugins → Add New → Upload)
(cd connector && zip -qr ../build/nivcreative-connector.zip nivcreative-connector)
ls -la build/nivcreative-connector.zip
mkdir -p release && cp build/nivcreative-panel.zip build/nivcreative-connector.zip release/
