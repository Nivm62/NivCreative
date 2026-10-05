#!/usr/bin/env bash
# Builds release/nivcreative-panel-wp.zip – the WordPress plugin (panel served at /app).
set -euo pipefail
cd "$(dirname "$0")/.."
python3 tools/build_lang.py
php tests/unit.php
OUT=build/plugin/nivcreative-panel
rm -rf build/plugin && mkdir -p "$OUT/app"
cp wp-plugin/nivcreative-panel/nivcreative-panel.php wp-plugin/nivcreative-panel/uninstall.php "$OUT/"
for d in src views lang assets database; do cp -r "$d" "$OUT/app/$d"; done
rm -rf "$OUT/app/database/migrations/README.md.bak"
mkdir -p "$OUT/connector" && cp -r connector/nivcreative-connector "$OUT/connector/"
mkdir -p "$OUT/docs" && cp docs/*.md "$OUT/docs/"
printf '<?php // Silence is golden.\n' > "$OUT/index.php"
for d in src views lang database; do printf '<?php // Silence is golden.\n' > "$OUT/app/$d/index.php"; done
if command -v npx >/dev/null 2>&1 && npx --no-install esbuild --version >/dev/null 2>&1 || command -v esbuild >/dev/null 2>&1; then
  ES="$(command -v esbuild || echo 'npx --no-install esbuild')"
  for f in $(find "$OUT/app/assets/js" -name '*.js'); do $ES "$f" --minify --target=es2017 --outfile="$f" --allow-overwrite --log-level=error; done
  $ES "$OUT/app/assets/css/app.css" --minify --outfile="$OUT/app/assets/css/app.css" --allow-overwrite --log-level=error
  echo "assets minified"
fi
(cd build/plugin && zip -qr ../nivcreative-panel-wp.zip nivcreative-panel)
mkdir -p release && cp build/nivcreative-panel-wp.zip release/
ls -la release/nivcreative-panel-wp.zip
