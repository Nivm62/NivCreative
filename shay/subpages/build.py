"""Build the Elementor data for every Shay inner page from the live home page's shared parts.

header/global CSS  -> home widget 1 up to </header>
content            -> template.render(page)
contact heading    -> home widget 1 from <section class="syx-contact">
form container     -> home form container (deep copy), page-specific hidden fields only
footer             -> home widget 2, accessibility link pointed at /shaymentor/accessibility-declaration/
"""
import copy, json, random, sys
sys.path.insert(0, __file__.rsplit('/', 1)[0])
from template import render
from pages import PAGES

A11Y_OLD, A11Y_NEW = 'href="/accessibility-declaration/"', 'href="/shaymentor/accessibility-declaration/"'
rnd = random.Random(2026)


def new_ids(el):
    el['id'] = '%07x' % rnd.getrandbits(28)
    for c in el.get('elements', []):
        new_ids(c)


def shared(home):
    data = json.loads(home['meta']['_elementor_data'])
    w1 = data[0]['elements'][0]['settings']['html']
    head = w1[:w1.index('</header>') + len('</header>')]
    tail = w1[w1.index('<section class="syx-contact"'):]
    form_cont = data[0]['elements'][1]
    footer = data[0]['elements'][2]['settings']['html']
    assert footer.count(A11Y_OLD) == 1
    return data, head, tail, form_cont, footer.replace(A11Y_OLD, A11Y_NEW)


def build(key, page, home, drafts):
    data, head, tail, form_cont, footer = shared(home)
    root = copy.deepcopy(data[0])
    root['elements'][0]['settings']['html'] = head + render(page) + tail
    fc = copy.deepcopy(form_cont)
    form = fc['elements'][0]['elements'][0]
    fs = form['settings']
    path = '/shaymentor/' + ('partners/thank-you/' if key == 'partners-thank-you' else ('' if key == 'home' else page['slug'] + '/'))
    old = drafts.get(page['id'])
    service = 'פנייה כללית'
    if old:  # keep the internal lead labels the page already had (form name, subject, hidden service)
        def find(e):
            if e.get('widgetType') == 'form': return e
            for c in e.get('elements', []):
                r = find(c)
                if r: return r
        of = find(json.loads(old['meta']['_elementor_data'])[0])
        if of:
            for k in ('form_name', 'email_subject', 'redirect_to'):
                if of['settings'].get(k): fs[k] = of['settings'][k]
            for f in of['settings'].get('form_fields', []):
                if f.get('custom_id') == 'service': service = f.get('field_value') or service
    if key == 'accessibility-declaration':
        service, fs['form_name'] = 'פנייה בנושא נגישות או כללית', 'ShayMentor – הצהרת נגישות'
    for f in fs['form_fields']:
        if f.get('custom_id') == 'service': f['field_value'] = service
        if f.get('custom_id') == 'source_page': f['field_value'] = path
    root['elements'][1] = fc
    root['elements'][2]['settings']['html'] = footer
    new_ids(root)
    return [root], path


if __name__ == '__main__':
    home = json.load(open(sys.argv[1]))
    drafts = {}
    for p in PAGES.values():
        if p['id']:
            drafts[p['id']] = json.load(open(f"{sys.argv[2]}/{p['id']}.json"))
    out = {}
    for k, p in PAGES.items():
        data, path = build(k, p, home, drafts)
        out[k] = {'id': p['id'], 'path': path, 'title': p['title'], 'slug': p['slug'], 'seo_title': p['seo_title'], 'seo_desc': p['seo_desc'],
                  'elementor_data': json.dumps(data, ensure_ascii=False)}
    json.dump(out, open(sys.argv[3], 'w'), ensure_ascii=False)
    print('built', len(out), 'pages')
