#!/usr/bin/env python3
"""Create/update the WordPress page via REST (auth is injected by the environment proxy; no secrets here).
usage: publish.py [draft|publish]"""
import json, os, subprocess, sys
HERE = os.path.dirname(os.path.abspath(__file__))
API = 'https://nivcreative.com/wp-json'
status = sys.argv[1] if len(sys.argv) > 1 else 'draft'
doc = json.load(open(os.path.join(HERE, 'bracha-elementor.json')))
state = os.path.join(HERE, '.page_id')
meta = {"_elementor_edit_mode": "builder", "_elementor_template_type": "wp-page", "_wp_page_template": "elementor_canvas",
        "_elementor_data": json.dumps(doc["content"], ensure_ascii=False)}
payload = {"title": "ברכה ברנס", "slug": "bracha", "status": status, "template": "elementor_canvas", "meta": meta}
pid = open(state).read().strip() if os.path.exists(state) else None
url = '%s/wp/v2/pages%s' % (API, '/' + pid if pid else '')
import tempfile
tmp = os.path.join(tempfile.mkdtemp(), 'payload.json'); json.dump(payload, open(tmp, 'w'), ensure_ascii=False)
r = subprocess.run(['curl', '-sS', '-m', '90', '-X', 'POST', url, '-H', 'Content-Type: application/json', '--data-binary', '@' + tmp], capture_output=True, text=True)
d = json.loads(r.stdout)
if 'id' not in d: sys.exit(r.stdout[:500])
open(state, 'w').write(str(d['id']))
subprocess.run(['curl', '-sS', '-m', '30', '-X', 'DELETE', API + '/elementor/v1/cache', '-o', '/dev/null'])
print(d['id'], d['slug'], d['status'], d['link'])
