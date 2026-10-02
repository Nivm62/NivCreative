#!/usr/bin/env bash
# Run once on your own machine: downloads the Stitch placeholder images into ./images
# and rewrites index.html to use the local copies (so they never expire).
set -e
cd "$(dirname "$0")"
mkdir -p images
i=0
for u in $(grep -o 'https://lh3[^"]*' index.html | sort -u); do
  i=$((i+1))
  curl -sSL "$u" -o "images/img$i.jpg"
  python3 - "$u" "images/img$i.jpg" <<'PY'
import sys
u,l=sys.argv[1],sys.argv[2]
s=open('index.html',encoding='utf-8').read().replace(u,l)
open('index.html','w',encoding='utf-8').write(s)
PY
  echo "saved images/img$i.jpg"
done
