#!/usr/bin/env python3
"""usage: push_draft.py <json> [status] [password]  -> updates page in .page_id (auth via proxy)"""
import json, os, subprocess, sys, tempfile
H = os.path.dirname(os.path.abspath(__file__)); pid = open(os.path.join(H, '.page_id')).read().strip()
doc = json.load(open(sys.argv[2 - 1])); status = sys.argv[2] if len(sys.argv) > 2 else 'draft'; pw = sys.argv[3] if len(sys.argv) > 3 else ''
payload = {"status": status, "password": pw, "meta": {"_elementor_data": json.dumps(doc['content'], ensure_ascii=False)}}
tmp = os.path.join(tempfile.mkdtemp(), 'p.json'); json.dump(payload, open(tmp, 'w'), ensure_ascii=False)
r = subprocess.run(['curl', '-sS', '-m', '90', '-X', 'POST', 'https://nivcreative.com/wp-json/wp/v2/pages/' + pid, '-H', 'Content-Type: application/json', '--data-binary', '@' + tmp], capture_output=True, text=True)
j = json.loads(r.stdout); print(j.get('id'), j.get('slug'), j.get('status'), j.get('code'), j.get('message'))
subprocess.run(['curl', '-sS', '-m', '30', '-X', 'DELETE', 'https://nivcreative.com/wp-json/elementor/v1/cache', '-o', '/dev/null'])
