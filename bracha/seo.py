#!/usr/bin/env python3
"""Set server-side SEO fields (SureRank) for the Bracha page only. Auth is injected by the proxy."""
import json, os, subprocess, tempfile
HERE = os.path.dirname(os.path.abspath(__file__))
pid = int(open(os.path.join(HERE, '.page_id')).read().strip())
TITLE = 'ברכה ברנס | מנטורית לגיל הרך והכנה לכיתה א׳'
DESC = 'ברכה ברנס, אשת חינוך ומנטורית לגיל הרך, בעלת מעל 20 שנות ניסיון. הכנה מיטבית לכיתה א׳, פיתוח ביטחון עצמי, מסוגלות ורכישת קריאה בשיטה ייחודית.'
IMG_ID, IMG_URL = 3669, 'https://nivcreative.com/wp-content/uploads/2026/11/01_bracha_portrait_from_original.webp'
meta = {"page_title": TITLE, "page_description": DESC, "auto_generate_description": False,
        "facebook_title": TITLE, "facebook_description": DESC, "facebook_image_url": IMG_URL, "facebook_image_id": IMG_ID,
        "twitter_title": TITLE, "twitter_description": DESC}
tmp = os.path.join(tempfile.mkdtemp(), 'seo.json'); json.dump({"post_id": pid, "metaData": meta}, open(tmp, 'w'), ensure_ascii=False)
r = subprocess.run(['curl', '-sS', '-m', '60', '-X', 'POST', 'https://nivcreative.com/wp-json/surerank/v1/post/settings', '-H', 'Content-Type: application/json', '--data-binary', '@' + tmp], capture_output=True, text=True)
print(r.stdout[:300])
