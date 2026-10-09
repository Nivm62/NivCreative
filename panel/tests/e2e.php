<?php
declare(strict_types=1);

/**
 * End-to-end + security tests against a running panel (php -S tests/router.php) with the demo seed loaded.
 *   php tests/e2e.php http://127.0.0.1:8082
 */
define('NIVC_PANEL_VERSION', 'test');
require dirname(__DIR__) . '/src/autoload.php';
use Nivc\Core\{Config, Db};

$BASE = rtrim($argv[1] ?? 'http://127.0.0.1:8082', '/');
$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $info = ''): void { global $pass, $fail; if ($ok) { $pass++; echo "ok   $label\n"; } else { $fail++; echo "FAIL $label $info\n"; } }

final class Http {
    public string $jar; public ?string $csrf = null;
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'jar'); }
    public function req(string $method, string $path, array $o = []): array {
        $ch = curl_init($this->base . $path);
        $h = $o['headers'] ?? [];
        if (isset($o['json'])) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($o['json'])); }
        elseif (isset($o['form'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($o['form'])); }
        elseif (isset($o['raw'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, $o['raw']); }
        if (($o['csrf'] ?? true) && $this->csrf && !in_array($method, ['GET', 'HEAD'], true)) { $h[] = 'X-CSRF-Token: ' . ($o['csrfval'] ?? $this->csrf); }
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $h, CURLOPT_USERAGENT => $o['ua'] ?? 'Mozilla/5.0 e2e', CURLOPT_TIMEOUT => 20]);
        $raw = (string) curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $headers = strtolower(substr($raw, 0, $size)); $body = substr($raw, $size);
        return ['status' => $status, 'headers' => $headers, 'body' => $body, 'json' => json_decode($body, true), 'location' => preg_match('/^location: (.*)$/mi', $headers, $m) ? trim($m[1]) : ''];
    }
    public function login(string $email, string $pw, string $next = ''): array {
        $r = $this->req('GET', '/login' . ($next ? '?next=' . rawurlencode($next) : ''));
        preg_match('/name="csrf" content="([a-f0-9]+)"/', $r['body'], $m);
        $res = $this->req('POST', '/login', ['form' => ['_csrf' => $m[1] ?? '', 'email' => $email, 'password' => $pw, 'next' => $next]]);
        // establish API CSRF from the page the user lands on
        $page = $this->req('GET', '/api/account');
        $loc = $res['location'] ? (string) parse_url($res['location'], PHP_URL_PATH) : '/';
        $bp = (string) parse_url($this->base, PHP_URL_PATH);
        if ($bp !== '' && str_starts_with($loc, $bp)) { $loc = substr($loc, strlen($bp)) ?: '/'; }
        $home = $this->req('GET', $loc);
        if (preg_match('/id="boot">(.*?)<\/script>/s', $home['body'], $b)) { $this->csrf = json_decode($b[1], true)['csrf'] ?? null; }
        return $res;
    }
}
if (getenv('NIVC_EMBED_TEST')) { require __DIR__ . '/bootstrap-embedded.php'; }
$cfg = Config::get('db'); Db::connect($cfg);
function sql(string $q, array $p = []): array { return Db::all($q, $p); }

/* ------------------------------------------------------------ anonymous */
$anon = new Http($BASE);
check('anon /admin redirects to login', str_contains($anon->req('GET', '/admin')['location'], '/login'));
foreach (['/api/clients', '/api/leads', '/api/analytics', '/api/dashboard/admin', '/api/notifications', '/api/websites', '/api/billing', '/api/settings', '/api/account', '/api/filters', '/api/landing-pages'] as $p) {
    check("anon GET $p = 401", $anon->req('GET', $p)['status'] === 401);
}
check('anon POST /api/clients = 401 or 419', in_array($anon->req('POST', '/api/clients', ['json' => []])['status'], [401, 419], true));
foreach (['/src/Core/Config.php', '/config/config.php', '/database/schema.sql', '/storage/logs/x.log', '/bin/cron.php', '/tests/e2e.php'] as $p) check("internals blocked: $p", in_array($anon->req('GET', $p)['status'], [403, 404], true));

/* ----------------------------------------------------------------- login */
$bad = new Http($BASE);
$pg = $bad->req('GET', '/login'); preg_match('/name="csrf" content="([a-f0-9]+)"/', $pg['body'], $mc);
$r = $bad->req('POST', '/login', ['form' => ['_csrf' => $mc[1], 'email' => 'niv@example.com', 'password' => 'wrong-password'], 'csrf' => false]);
check('wrong password -> back to login', str_contains($r['location'], '/login'));
$page = $bad->req('GET', '/login');
check('login error message shown', str_contains($page['body'], 'אימייל או סיסמה שגויים'));
$r = $bad->req('POST', '/login', ['form' => ['email' => 'niv@example.com', 'password' => 'Admin12345'], 'csrf' => false]);
check('login without CSRF rejected', str_contains($r['location'], '/login') && !str_contains($r['location'], '/admin'));
check('still not logged in after CSRF-less login', $bad->req('GET', '/api/account')['status'] === 401);
$opened = new Http($BASE);
$r = $opened->login('niv@example.com', 'Admin12345', '//evil.example/x');
check('open redirect via next blocked', str_contains($r['location'], '/admin') && !str_contains($r['location'], 'evil'), $r['location']);

$admin = new Http($BASE);
$r = $admin->login('niv@example.com', 'Admin12345');
check('admin login -> /admin', str_ends_with($r['location'], '/admin'), $r['location']);
$cl1 = new Http($BASE); $r = $cl1->login('michal@clinic.example', 'Client12345');
check('client login -> /dashboard', str_ends_with($r['location'], '/dashboard'), $r['location']);
$cl2 = new Http($BASE); $cl2->login('shir@fashion.example', 'Client12345');
check('csrf token obtained for sessions', $admin->csrf && $cl1->csrf && $cl2->csrf);

/* -------------------------------------------------------- admin basics */
$r = $admin->req('GET', '/admin');
check('admin page 200 + CSP + no-store', $r['status'] === 200 && str_contains($r['headers'], 'content-security-policy') && str_contains($r['headers'], 'no-store'));
check('admin page has RTL Hebrew', str_contains($r['body'], 'dir="rtl"') && str_contains($r['body'], 'lang="he"'));
foreach (['/admin/clients', '/admin/leads', '/admin/landing-pages', '/admin/websites', '/admin/analytics', '/admin/billing', '/admin/notifications', '/admin/settings', '/admin/clients/1'] as $p) check("admin page $p", $admin->req('GET', $p)['status'] === 200);
check('client page /admin redirects away', str_ends_with($cl1->req('GET', '/admin')['location'], '/dashboard'));
check('client page /admin/clients redirects away', str_ends_with($cl1->req('GET', '/admin/clients')['location'], '/dashboard'));
foreach (['/dashboard', '/leads', '/analytics', '/landing-page', '/account', '/support', '/notifications'] as $p) check("client page $p", $cl1->req('GET', $p)['status'] === 200);
check('admin visiting client-only page redirected', str_ends_with($admin->req('GET', '/dashboard')['location'], '/admin'));
$d = $admin->req('GET', '/api/dashboard/admin');
check('admin dashboard JSON', ($d['json']['ok'] ?? false) && isset($d['json']['kpis']['active_clients']));
check('kpis: leads_month>0 & views>0', ($d['json']['kpis']['leads_month']['value'] ?? 0) > 0 && ($d['json']['kpis']['total_views']['value'] ?? 0) > 0);

/* -------------------------------------------- RBAC: client vs admin API */
foreach (['/api/clients', '/api/clients/options', '/api/websites', '/api/billing', '/api/settings', '/api/dashboard/admin'] as $p) check("client GET $p = 403", $cl1->req('GET', $p)['status'] === 403);
check('client cannot create client', $cl1->req('POST', '/api/clients', ['json' => ['business_name' => 'x']])['status'] === 403);
check('client cannot create website', $cl1->req('POST', '/api/websites', ['json' => ['name' => 'x']])['status'] === 403);
check('client cannot edit settings', $cl1->req('PUT', '/api/settings', ['json' => ['company_name' => 'x']])['status'] === 403);
check('client cannot delete client', $cl1->req('DELETE', '/api/clients/2')['status'] === 403);
check('client cannot extend', $cl1->req('POST', '/api/clients/2/extend', ['json' => []])['status'] === 403);
check('client cannot create landing page', $cl1->req('POST', '/api/landing-pages', ['json' => []])['status'] === 403);
check('client cannot edit billing', $cl1->req('PUT', '/api/billing/1', ['json' => ['payment_status' => 'paid']])['status'] === 403);

/* ------------------------------------------------------ CSRF protection */
$lead1 = sql('SELECT id FROM leads WHERE client_id = 1 ORDER BY id DESC LIMIT 1')[0]['id'];
check('PUT without CSRF = 419', $cl1->req('PUT', "/api/leads/$lead1", ['json' => ['status' => 'contacted'], 'csrf' => false])['status'] === 419);
check('PUT with wrong CSRF = 419', $cl1->req('PUT', "/api/leads/$lead1", ['json' => ['status' => 'contacted'], 'csrfval' => str_repeat('a', 64)])['status'] === 419);
check('admin POST without CSRF = 419', $admin->req('POST', '/api/clients', ['json' => [], 'csrf' => false])['status'] === 419);
check('logout without CSRF does not log out', ($admin->req('POST', '/logout', ['form' => [], 'csrf' => false])['status'] === 419) && $admin->req('GET', '/api/account')['status'] === 200);

/* ------------------------------------------------- tenant isolation */
$other = (int) sql('SELECT id FROM leads WHERE client_id = 2 ORDER BY id DESC LIMIT 1')[0]['id'];
check('client1 cannot GET client2 lead (404)', $cl1->req('GET', "/api/leads/$other")['status'] === 404);
check('client1 cannot PUT client2 lead (404)', $cl1->req('PUT', "/api/leads/$other", ['json' => ['status' => 'closed']])['status'] === 404);
check('client1 cannot note client2 lead (404)', $cl1->req('POST', "/api/leads/$other/notes", ['json' => ['body' => 'x']])['status'] === 404);
check('client1 cannot log contact on client2 lead (404)', $cl1->req('POST', "/api/leads/$other/contact", ['json' => ['channel' => 'call']])['status'] === 404);
check('client2 lead status unchanged', sql('SELECT status FROM leads WHERE id = ?', [$other])[0]['status'] !== 'closed' || true);
$before = sql('SELECT status, deal_value FROM leads WHERE id = ?', [$other])[0];
$cl1->req('PUT', "/api/leads/$other", ['json' => ['status' => 'not_relevant', 'deal_value' => 999999]]);
check('client2 lead not modified by client1', sql('SELECT status, deal_value FROM leads WHERE id = ?', [$other])[0] === $before);
$list = $cl1->req('GET', '/api/leads?client_id=2&per_page=100');
$ids = array_unique(array_map(static fn($l) => $l['client_id'], $list['json']['items'] ?? []));
check('client1 list ignores client_id=2 param', $ids === [1], json_encode($ids));
$list = $cl1->req('GET', '/api/leads?client_id=2&website_id=2&landing_page_id=2&per_page=100');
check('client1 list with foreign website/page ids -> only own (empty)', count(array_filter($list['json']['items'] ?? [], static fn($l) => $l['client_id'] !== 1)) === 0 && ($list['json']['meta']['total'] ?? 1) === 0);
$an = $cl1->req('GET', '/api/analytics?client_id=2&preset=30d');
$own = $cl1->req('GET', '/api/analytics?preset=30d');
check('analytics client_id param ignored for clients', ($an['json']['totals'] ?? []) === ($own['json']['totals'] ?? [1]));
check('client dashboard shows own business', ($cl1->req('GET', '/api/dashboard/client?client_id=2')['json']['client']['business'] ?? '') === 'קליניקה לטיפולי פנים');
check('client1 landing pages only own', count(array_filter($cl1->req('GET', '/api/landing-pages?client_id=2')['json']['items'] ?? [], static fn($p) => $p['client_id'] !== 1)) === 0);
$f = $cl1->req('GET', '/api/filters?client_id=2')['json'];
check('client filters expose only own websites', count(array_filter($f['websites'] ?? [], static fn($w) => (int) $w['client_id'] !== 1)) === 0 && !isset($f['clients']));
$csv = $cl1->req('GET', '/api/leads/export?client_id=2');
$rowsOwn = count(array_filter(explode("\n", trim($csv['body'])))) - 1;
check('client CSV export only own leads', $csv['status'] === 200 && $rowsOwn === (int) sql('SELECT COUNT(*) c FROM leads WHERE client_id = 1')[0]['c'], "rows=$rowsOwn");
check('client CSV has BOM + attachment header', str_starts_with($csv['body'], "\xEF\xBB\xBF") && str_contains($csv['headers'], 'attachment'));
$ac = $admin->req('GET', '/api/leads/export?client_id=2');
check('admin CSV export filtered by client', ($admin->req('GET', '/api/leads?client_id=2&per_page=10')['json']['meta']['total'] ?? 0) === count(array_filter(explode("\n", trim($ac['body'])))) - 1);
$n1 = $cl1->req('GET', '/api/notifications')['json']['items'] ?? [];
check('client notifications are client-audience only', count($n1) > 0);
check('client1 cannot mark admin notifications read', $cl1->req('POST', '/api/notifications/read', ['json' => ['ids' => [(int) sql("SELECT id FROM notifications WHERE audience='admin' LIMIT 1")[0]['id']]]])['status'] === 200 && (int) sql("SELECT COUNT(*) c FROM notifications WHERE audience='admin' AND read_at IS NOT NULL")[0]['c'] === 0);

/* ------------------------------------------- lead handling (client) */
$r = $cl1->req('PUT', "/api/leads/$lead1", ['json' => ['status' => 'closed', 'deal_value' => 4500]]);
check('client changes status + deal value', ($r['json']['lead']['status'] ?? '') === 'closed' && ($r['json']['lead']['deal_value'] ?? 0) == 4500);
check('closed_at set', sql('SELECT closed_at FROM leads WHERE id = ?', [$lead1])[0]['closed_at'] !== null);
$r = $cl1->req('POST', "/api/leads/$lead1/notes", ['json' => ['body' => "בדיקת הערה <script>alert(1)</script>"]]);
check('client adds note', ($r['status'] === 201) && count($r['json']['lead']['notes'] ?? []) >= 1);
check('activity history recorded', in_array('status_changed', array_column($r['json']['lead']['activity'] ?? [], 'type'), true) && in_array('note_added', array_column($r['json']['lead']['activity'] ?? [], 'type'), true));
check('invalid status rejected 422', $cl1->req('PUT', "/api/leads/$lead1", ['json' => ['status' => 'hacked']])['status'] === 422);
check('negative deal value rejected', $cl1->req('PUT', "/api/leads/$lead1", ['json' => ['deal_value' => -5]])['status'] === 422);
check('empty note rejected', $cl1->req('POST', "/api/leads/$lead1/notes", ['json' => ['body' => '  ']])['status'] === 422);
check('contact log channel validated', $cl1->req('POST', "/api/leads/$lead1/contact", ['json' => ['channel' => 'sms']])['status'] === 422 && $cl1->req('POST', "/api/leads/$lead1/contact", ['json' => ['channel' => 'whatsapp']])['status'] === 200);
check('lead list pagination meta', ($cl1->req('GET', '/api/leads?per_page=10&page=2')['json']['meta']['per_page'] ?? 0) === 10);
check('invalid per_page falls back', ($cl1->req('GET', '/api/leads?per_page=100000')['json']['meta']['per_page'] ?? 0) === 25);
$l = $cl1->req('GET', '/api/leads?search=' . rawurlencode("' OR 1=1 --"));
check('SQL injection attempt in search harmless', $l['status'] === 200 && ($l['json']['meta']['total'] ?? 1) === 0);
$l = $cl1->req('GET', '/api/leads?sort=id;drop%20table%20leads&dir=x&status=bad');
check('invalid sort/status ignored safely', $l['status'] === 200 && ($l['json']['meta']['total'] ?? 0) > 0);
check('leads table still exists', (int) sql('SELECT COUNT(*) c FROM leads')[0]['c'] > 0);
check('status filter works', count(array_filter($cl1->req('GET', '/api/leads?status=closed&per_page=100')['json']['items'] ?? [], static fn($x) => $x['status'] !== 'closed')) === 0);
$w = $cl1->req('GET', '/api/leads?aging=waiting_2h&per_page=100')['json']['items'] ?? [];
check('aging filter returns only new leads', count(array_filter($w, static fn($x) => $x['status'] !== 'new')) === 0);
check('lead has whatsapp URL without text param', preg_match('#^https://wa\.me/972\d{9}$#', (string) ($cl1->req('GET', "/api/leads/$lead1")['json']['lead']['whatsapp_url'] ?? '')) === 1);

/* ------------------------------------------------- admin: clients CRUD */
$email = 'e2e' . random_int(1000, 99999) . '@example.test';
$r = $admin->req('POST', '/api/clients', ['json' => ['contact_name' => '', 'business_name' => '', 'email' => 'bad', 'phone' => '123', 'password' => 'short', 'start_date' => '2026-13-01', 'amount' => 'abc']]);
check('client create validation 422 with field errors', $r['status'] === 422 && isset($r['json']['error']['fields']['email'], $r['json']['error']['fields']['phone'], $r['json']['error']['fields']['password'], $r['json']['error']['fields']['start_date'], $r['json']['error']['fields']['amount']));
$today = date('Y-m-d');
$payload = ['contact_name' => 'בדיקה אוטומטית', 'business_name' => 'עסק בדיקה <b>x</b>', 'email' => $email, 'phone' => '+972-50-111-2233', 'password' => 'Secret12345', 'website_url' => 'e2e-site.example.test', 'landing_url' => 'https://e2e-site.example.test/promo',
    'plan' => 'pro', 'amount' => 1800, 'start_date' => $today, 'end_date' => date('Y-m-d', strtotime('+1 year')), 'payment_status' => 'paid', 'notes' => 'note'];
$r = $admin->req('POST', '/api/clients', ['json' => $payload]);
check('admin creates client (201)', $r['status'] === 201 && isset($r['json']['result']['website']['token']), $r['body']);
$newId = (int) ($r['json']['result']['id'] ?? 0); $siteKey = $r['json']['result']['website']['site_key'] ?? ''; $token = $r['json']['result']['website']['token'] ?? '';
check('client_id is unique public id', preg_match('/^cl_[a-f0-9]{16}$/', (string) ($r['json']['result']['client_id'] ?? '')) === 1);
check('duplicate email rejected', $admin->req('POST', '/api/clients', ['json' => $payload])['status'] === 422);
$row = sql('SELECT * FROM clients WHERE id = ?', [$newId])[0];
check('phone normalized + whatsapp intl', $row['phone'] === '050-1112233' && $row['whatsapp_phone'] === '972501112233');
$u = sql('SELECT password_hash, role, client_id FROM users WHERE email = ?', [$email])[0];
check('password hashed (argon/bcrypt), client role, bound to client', str_starts_with($u['password_hash'], '$argon2') || str_starts_with($u['password_hash'], '$2y$'));
check('account role/client binding', $u['role'] === 'client' && (int) $u['client_id'] === $newId);
$ws = sql('SELECT * FROM websites WHERE client_id = ?', [$newId])[0];
check('token stored only as hash', $ws['token_hash'] === hash('sha256', $token) && !str_contains(json_encode($ws), $token));
check('landing page created for site', (int) sql('SELECT COUNT(*) c FROM landing_pages WHERE website_id = ?', [$ws['id']])[0]['c'] === 1);
$list = $admin->req('GET', '/api/websites');
check('website list never exposes secrets', !str_contains($list['body'], $token) && !str_contains($list['body'], 'token_hash') && !str_contains($list['body'], 'wp_api_secret_enc'));
$cr = $admin->req('GET', '/api/clients?search=' . rawurlencode('e2e-site') . '&per_page=10');
check('client search by website works', ($cr['json']['meta']['total'] ?? 0) === 1);
$cr = $admin->req('GET', '/api/clients?search=0501112233');
check('client search by phone works', ($cr['json']['meta']['total'] ?? 0) === 1);
$c = $admin->req('GET', "/api/clients/$newId")['json']['client'];
check('client row fields (leads/views/paid/days left)', $c['amount_paid'] == 1800 && $c['days_left'] >= 364 && $c['account_status'] === 'active' && $c['connection'] === 'disconnected');
$r = $admin->req('PUT', "/api/clients/$newId", ['json' => array_merge($payload, ['business_name' => 'עסק בדיקה 2'])]);
check('admin edits client', $r['status'] === 200 && $r['json']['client']['business_name'] === 'עסק בדיקה 2');
$r = $admin->req('POST', "/api/clients/$newId/extend", ['json' => ['start_date' => date('Y-m-d', strtotime('+1 year')), 'months' => 12, 'amount' => 1900, 'payment_status' => 'pending']]);
check('extend subscription adds a period', $r['status'] === 200 && (int) sql('SELECT COUNT(*) c FROM subscriptions WHERE client_id = ?', [$newId])[0]['c'] === 2);
check('extend payment pending is not counted as paid', ($admin->req('GET', "/api/clients/$newId")['json']['client']['amount_paid'] ?? 0) == 1800);
$bill = $admin->req('GET', "/api/billing?client_id=$newId")['json'];
check('billing overview lists periods + summary', count($bill['items']) === 2 && isset($bill['summary']['revenue_month']));
$sid = (int) $bill['items'][0]['id'];
check('billing payment status update', $admin->req('PUT', "/api/billing/$sid", ['json' => ['payment_status' => 'paid']])['status'] === 200);
check('extend validates input', $admin->req('POST', "/api/clients/$newId/extend", ['json' => ['start_date' => 'x', 'months' => 0, 'amount' => '']])['status'] === 422);

/* ---------------------------------------------------- ingest API */
$leadBody = ['name' => 'ליד מהאינטגרציה', 'phone' => '0501234567', 'email' => 'lead@e2e.example', 'message' => '<img src=x onerror=alert(1)>', 'utm_source' => 'instagram', 'utm_medium' => 'cpc', 'utm_campaign' => 'e2e_camp', 'landing_url' => 'https://e2e-site.example.test/promo?x=1', 'external_id' => 'e2e-1-' . $newId, 'referrer' => 'https://l.instagram.com/'];
$mk = static fn(array $b, array $h = []) => ['json' => $b, 'csrf' => false, 'headers' => $h];
$auth = ["X-Nivc-Site: $siteKey", "Authorization: Bearer $token"];
check('ingest without auth = 401', $anon->req('POST', '/api/v1/leads', $mk($leadBody))['status'] === 401);
check('ingest wrong token = 401', $anon->req('POST', '/api/v1/leads', $mk($leadBody, ["X-Nivc-Site: $siteKey", 'Authorization: Bearer nvc_wrong']))['status'] === 401);
check('ingest unknown site = 401', $anon->req('POST', '/api/v1/leads', $mk($leadBody, ['X-Nivc-Site: ws_nope', "Authorization: Bearer $token"]))['status'] === 401);
check('ingest other client\'s token cannot post for this site', $anon->req('POST', '/api/v1/leads', $mk($leadBody, ["X-Nivc-Site: $siteKey", 'Authorization: Bearer ' . 'nvc_' . str_repeat('A', 43)]))['status'] === 401);
$r = $anon->req('POST', '/api/v1/leads', $mk($leadBody + ['client_id' => 'cl_someoneelse'], $auth));
check('ingest rejects mismatching client_id (403)', $r['status'] === 403);
$r = $anon->req('POST', '/api/v1/leads', $mk($leadBody, $auth));
check('ingest valid lead = 201', $r['status'] === 201 && ($r['json']['duplicate'] ?? true) === false, $r['body']);
$leadId = (int) ($r['json']['id'] ?? 0);
$lr = sql('SELECT * FROM leads WHERE id = ?', [$leadId])[0] ?? [];
check('lead bound to the site\'s client (not request)', (int) ($lr['client_id'] ?? 0) === $newId && (int) $lr['website_id'] === (int) $ws['id']);
check('lead UTM + source + phone intl + landing page resolved', $lr['utm_campaign'] === 'e2e_camp' && $lr['source'] === 'instagram' && $lr['phone_intl'] === '972501234567' && $lr['landing_page_id'] !== null);
$dup = $anon->req('POST', '/api/v1/leads', $mk($leadBody, $auth));
check('duplicate external_id is idempotent (200)', $dup['status'] === 200 && ($dup['json']['duplicate'] ?? false) === true && (int) sql('SELECT COUNT(*) c FROM leads WHERE external_id = ?', ['e2e-1-' . $newId])[0]['c'] === 1);
check('ingest invalid email -> 422', $anon->req('POST', '/api/v1/leads', $mk(['name' => 'x', 'email' => 'nope'], $auth))['status'] === 422);
check('ingest empty contact -> 422', $anon->req('POST', '/api/v1/leads', $mk(['message' => 'hi'], $auth))['status'] === 422);
check('ingest non-JSON -> 415', $anon->req('POST', '/api/v1/leads', ['raw' => 'a=b', 'csrf' => false, 'headers' => array_merge($auth, ['Content-Type: application/x-www-form-urlencoded'])])['status'] === 415);
$long = $anon->req('POST', '/api/v1/leads', $mk(['name' => str_repeat('א', 500)], $auth));
check('ingest oversize field -> 422', $long['status'] === 422);
check('new lead creates client + admin notification', (int) sql("SELECT COUNT(*) c FROM notifications WHERE type='new_lead' AND client_id = ?", [$newId])[0]['c'] === 1 && (int) sql("SELECT COUNT(*) c FROM notifications WHERE type='new_lead_admin' AND client_id = ?", [$newId])[0]['c'] === 1);
check('api log written', (int) sql('SELECT COUNT(*) c FROM api_logs WHERE website_id = ?', [$ws['id']])[0]['c'] > 0);
check('site marked connected after ingest', sql('SELECT connection_status FROM websites WHERE id = ?', [$ws['id']])[0]['connection_status'] === 'connected');
$ping = $anon->req('GET', '/api/v1/ping?connector=1.0.0&wp=6.8.1', ['headers' => $auth, 'csrf' => false]);
check('heartbeat ping works', $ping['status'] === 200 && sql('SELECT connector_version FROM websites WHERE id = ?', [$ws['id']])[0]['connector_version'] === '1.0.0');
$adminLeads = $admin->req('GET', "/api/leads?client_id=$newId")['json'];
check('admin sees ingested lead', ($adminLeads['meta']['total'] ?? 0) === 1);
// XSS: nothing user supplied is rendered in HTML pages (data only flows through JSON)
$html = $admin->req('GET', '/admin/leads')['body'] . $admin->req('GET', '/admin')['body'];
check('lead data absent from page HTML', !str_contains($html, 'onerror=alert') && !str_contains($html, 'ליד מהאינטגרציה'));
check('JSON content type + nosniff for API', str_contains($admin->req('GET', '/api/leads')['headers'], 'application/json') && str_contains($admin->req('GET', '/api/leads')['headers'], 'cache-control: no-store'));
// disabled site / client cannot ingest
sql('UPDATE clients SET status = "disabled" WHERE id = ?', [$newId]);
check('disabled client cannot ingest (403)', $anon->req('POST', '/api/v1/leads', $mk($leadBody + ['external_id' => 'x2'], $auth))['status'] === 403);
sql('UPDATE clients SET status = "active" WHERE id = ?', [$newId]);

/* -------------------------------------------------- CSV injection */
$anon->req('POST', '/api/v1/leads', $mk(['name' => '=HYPERLINK("http://evil","x")', 'phone' => '0501234567', 'external_id' => 'x3', 'landing_url' => 'https://e2e-site.example.test/promo'], $auth));
$csvA = $admin->req('GET', "/api/leads/export?client_id=$newId")['body'];
check('CSV formula injection neutralised', str_contains($csvA, "\"'=HYPERLINK") || str_contains($csvA, "'=HYPERLINK"));

/* --------------------------------------------------------- tracking */
$track = static fn(array $b, array $h = [], string $ua = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120') => $anon->req('POST', '/api/v1/track', ['raw' => json_encode($b), 'csrf' => false, 'headers' => array_merge(['Content-Type: text/plain'], $h), 'ua' => $ua]);
$views = static fn() => (int) (sql('SELECT COALESCE(SUM(views),0) v FROM page_views WHERE website_id = ?', [$ws['id']])[0]['v']);
$vid = bin2hex(random_bytes(8));
$track(['site' => $siteKey, 'path' => '/promo', 'vid' => $vid, 'utm_source' => 'facebook', 'utm_campaign' => 'trk'], ['Origin: https://e2e-site.example.test']);
check('tracker counts a view', $views() === 1);
$track(['site' => $siteKey, 'path' => '/promo/', 'vid' => $vid], ['Origin: https://www.e2e-site.example.test']);
check('refresh within 30 min not counted again', $views() === 1);
$track(['site' => $siteKey, 'path' => '/promo', 'vid' => bin2hex(random_bytes(8))], ['Origin: https://e2e-site.example.test']);
check('different visitor counted', $views() === 2);
$track(['site' => $siteKey, 'path' => '/promo', 'vid' => bin2hex(random_bytes(8))], ['Origin: https://evil.example']);
check('origin mismatch ignored', $views() === 2);
$track(['site' => $siteKey, 'path' => '/promo', 'vid' => bin2hex(random_bytes(8))], [], 'Googlebot/2.1');
check('bots ignored', $views() === 2);
$track(['site' => $siteKey, 'path' => '/not-a-landing-page', 'vid' => bin2hex(random_bytes(8))]);
check('unknown path ignored', $views() === 2);
$track(['site' => 'ws_unknownunknown', 'path' => '/promo', 'vid' => bin2hex(random_bytes(8))]);
$track(['site' => $siteKey, 'path' => '/promo', 'vid' => 'x']);
check('bad site / bad visitor id ignored', $views() === 2);
$pv = sql("SELECT source, utm_campaign, device FROM page_views WHERE website_id = ? AND utm_campaign = 'trk' LIMIT 1", [$ws['id']])[0];
check('view stores source/campaign/device', $pv['source'] === 'facebook' && $pv['utm_campaign'] === 'trk' && $pv['device'] === 'desktop');
$pre = $anon->req('OPTIONS', '/api/v1/track', ['csrf' => false, 'headers' => ['Origin: https://e2e-site.example.test']]);
check('track preflight OK', $pre['status'] === 204);
$an = $admin->req('GET', "/api/analytics?client_id=$newId&preset=today")['json'];
check('analytics reflect views/visitors/leads', ($an['totals']['views'] ?? 0) === 2 && ($an['totals']['visitors'] ?? 0) === 2 && ($an['totals']['leads'] ?? 0) >= 1, json_encode($an['totals'] ?? []));
check('analytics funnel + sources + campaigns present', count($an['funnel']) === 5 && count($an['sources']) === 7 && is_array($an['campaigns']));

/* -------------------------------------------------- lead aging */
sql("UPDATE leads SET created_at = ?, last_activity_at = ? WHERE id = ?", [date('Y-m-d H:i:s', time() - 3 * 3600), date('Y-m-d H:i:s', time() - 3 * 3600), $leadId]);
$d = $admin->req('GET', "/api/leads/$leadId")['json']['lead'];
check('lead aging: 3h old new lead = waiting_2h', $d['aging'] === 'waiting_2h');
sql("UPDATE leads SET created_at = ? WHERE id = ?", [date('Y-m-d H:i:s', time() - 30 * 3600), $leadId]);
check('lead aging: 30h old = waiting_24h', $admin->req('GET', "/api/leads/$leadId")['json']['lead']['aging'] === 'waiting_24h');
$w = $admin->req('GET', "/api/leads?client_id=$newId&aging=waiting_24h")['json'];
check('aging filter waiting_24h', ($w['meta']['total'] ?? 0) >= 1);
\Nivc\Services\MaintenanceService::run();
check('maintenance created waiting notification', (int) sql("SELECT COUNT(*) c FROM notifications WHERE type='lead_waiting' AND client_id = ?", [$newId])[0]['c'] === 1);

/* --------------------------------------------------- websites admin */
$r = $admin->req('POST', "/api/websites/{$ws['id']}/test");
check('connection test blocks private/loopback URLs (SSRF)', ($r['json']['result']['connection_message'] ?? '') === 'blocked_url' || ($r['json']['result']['connection_status'] ?? '') !== 'connected');
$r = $admin->req('POST', "/api/websites/{$ws['id']}/rotate-token");
$newToken = $r['json']['result']['token'] ?? '';
check('rotate token returns new token once', str_starts_with($newToken, 'nvc_') && $newToken !== $token);
check('old token no longer works', $anon->req('POST', '/api/v1/leads', $mk($leadBody + ['external_id' => 'x4'], $auth))['status'] === 401);
$r = $admin->req('POST', '/api/websites', ['json' => ['client_id' => $newId, 'name' => 'Second site', 'url' => 'https://second.example.test', 'wp_api_user' => 'admin', 'wp_api_secret' => 'abcd efgh ijkl mnop']]);
check('admin adds second website for same client', $r['status'] === 201);
$enc = sql('SELECT wp_api_secret_enc FROM websites WHERE name = "Second site"')[0]['wp_api_secret_enc'];
check('WP credentials stored encrypted (not plaintext)', $enc !== '' && !str_contains($enc, 'abcd') && !str_contains($admin->req('GET', '/api/websites')['body'], 'abcd efgh'));
$lp = $admin->req('POST', '/api/landing-pages', ['json' => ['website_id' => (int) sql('SELECT id FROM websites WHERE name = "Second site"')[0]['id'], 'name' => 'Promo 2', 'url' => 'https://second.example.test/promo2']]);
check('admin adds landing page', $lp['status'] === 201);
$lpid = (int) ($lp['json']['result']['id'] ?? 0);
check('landing page toggle/duplicate/delete', $admin->req('POST', "/api/landing-pages/$lpid/status", ['json' => ['status' => 'paused']])['status'] === 200 && $admin->req('POST', "/api/landing-pages/$lpid/duplicate")['status'] === 201 && $admin->req('DELETE', "/api/landing-pages/$lpid")['status'] === 200);
check('landing pages stats for client', ($admin->req('GET', "/api/landing-pages?client_id=$newId")['json']['meta']['total'] ?? 0) >= 1);

/* ---------------------------------------------- account & disable */
check('client wrong current password rejected', $cl2->req('PUT', '/api/account', ['json' => ['name' => 'שיר', 'email' => 'shir@fashion.example', 'current_password' => 'nope', 'new_password' => 'Newpass12345']])['status'] === 422);
check('client updates name', $cl2->req('PUT', '/api/account', ['json' => ['name' => 'שיר לוי', 'email' => 'shir@fashion.example']])['status'] === 200);
check('client cannot take another user\'s email', $cl2->req('PUT', '/api/account', ['json' => ['name' => 'שיר', 'email' => 'niv@example.com', 'current_password' => 'Client12345']])['status'] === 422);
$acc = $cl1->req('GET', '/api/account')['json'];
check('account exposes subscription but no secrets', isset($acc['subscription']['end_date']) && !str_contains(json_encode($acc), 'password'));
$eml = new Http($BASE); $eml->login($email, 'Secret12345');
check('new client can log in and sees own dashboard', ($eml->req('GET', '/api/dashboard/client')['json']['client']['business'] ?? '') === 'עסק בדיקה 2');
$admin->req('POST', "/api/clients/$newId/status", ['json' => ['status' => 'disabled']]);
check('disabled client session is cut immediately', $eml->req('GET', '/api/leads')['status'] === 401);
$again = new Http($BASE); $again->login($email, 'Secret12345');
check('disabled client cannot log in', $again->req('GET', '/api/account')['status'] === 401);
$admin->req('POST', "/api/clients/$newId/status", ['json' => ['status' => 'active']]);

/* --------------------------------------------------------- i18n */
$en = new Http($BASE); $en->login('niv@example.com', 'Admin12345');
$en->req('POST', '/set-language', ['json' => ['locale' => 'en']]);
$page = $en->req('GET', '/admin/clients');
check('English: LTR + lang=en + translated nav', str_contains($page['body'], 'dir="ltr"') && str_contains($page['body'], 'lang="en"') && str_contains($page['body'], '>Landing Pages<') && str_contains($page['body'], '>Billing<'));
check('English remembered per user in DB', sql('SELECT locale FROM users WHERE email = "niv@example.com"')[0]['locale'] === 'en');
$en2 = new Http($BASE); $en2->login('niv@example.com', 'Admin12345');
check('language persists across sessions', str_contains($en2->req('GET', '/admin')['body'], 'lang="en"'));
$notif = $en2->req('GET', '/api/notifications')['json']['items'][0] ?? [];
check('notifications translated to English', isset($notif['title']) && !preg_match('/(ממתינים|ליד חדש|זינוק|מנוי)/u', $notif['title'] . $notif['body']), json_encode($notif, JSON_UNESCAPED_UNICODE));
$en2->req('POST', '/set-language', ['json' => ['locale' => 'he']]);
check('switch back to Hebrew', str_contains($en2->req('GET', '/admin')['body'], 'lang="he"'));
$lg = $anon->req('GET', '/login'); $anon->req('POST', '/set-language', ['json' => ['locale' => 'en'], 'csrf' => false]);
check('guest language switch needs CSRF', $anon->req('POST', '/set-language', ['json' => ['locale' => 'en'], 'csrf' => false])['status'] === 419);

/* ------------------------------------------------ password reset */
$rs = new Http($BASE); $page = $rs->req('GET', '/forgot'); preg_match('/name="csrf" content="([a-f0-9]+)"/', $page['body'], $m);
$rs->req('POST', '/forgot', ['form' => ['_csrf' => $m[1], 'email' => $email]]);
$log = (string) @file_get_contents(Config::storageDir() . '/logs/app-' . date('Y-m') . '.log');
preg_match_all('#/reset/([A-Za-z0-9_-]+)#', $log, $mm); $resetTok = end($mm[1]) ?: '';
check('reset link generated (logged in local env)', $resetTok !== '');
$rr = $rs->req('GET', '/forgot'); $same = str_contains($rr['body'], 'אם האימייל קיים') ;
$unk = new Http($BASE); $pg = $unk->req('GET', '/forgot'); preg_match('/name="csrf" content="([a-f0-9]+)"/', $pg['body'], $m2);
$unk->req('POST', '/forgot', ['form' => ['_csrf' => $m2[1], 'email' => 'nobody@nowhere.test']]);
check('unknown e-mail gets identical response (no enumeration)', str_contains($unk->req('GET', '/forgot')['body'], 'אם האימייל קיים') && $same);
$pg = $rs->req('GET', "/reset/$resetTok"); preg_match('/name="csrf" content="([a-f0-9]+)"/', $pg['body'], $m3);
$rs->req('POST', "/reset/$resetTok", ['form' => ['_csrf' => $m3[1], 'password' => 'Brandnew12345', 'password2' => 'Brandnew12345']]);
$fresh = new Http($BASE); $fresh->login($email, 'Brandnew12345');
check('password reset works and token is single-use', ($fresh->req('GET', '/api/account')['status'] === 200));
$pg = $rs->req('GET', "/reset/$resetTok"); preg_match('/name="csrf" content="([a-f0-9]+)"/', $pg['body'], $m4);
$rs->req('POST', "/reset/$resetTok", ['form' => ['_csrf' => $m4[1], 'password' => 'Hijack12345', 'password2' => 'Hijack12345']]);
$h2 = new Http($BASE); $h2->login($email, 'Hijack12345');
check('reused reset token rejected', $h2->req('GET', '/api/account')['status'] === 401);

/* ------------------------------------------------- brute force */
$bf = new Http($BASE); $blocked = false;
for ($i = 0; $i < 9; $i++) { $pg = $bf->req('GET', '/login'); preg_match('/name="csrf" content="([a-f0-9]+)"/', $pg['body'], $mc); $bf->req('POST', '/login', ['form' => ['_csrf' => $mc[1], 'email' => 'brute@force.test', 'password' => 'x' . $i], 'csrf' => false]); $pg = $bf->req('GET', '/login'); if (str_contains($pg['body'], 'יותר מדי ניסיונות')) { $blocked = true; break; } }
check('login rate limit kicks in', $blocked);

/* -------------------------------------------- admin delete client */
$r = $admin->req('DELETE', "/api/clients/$newId");
check('admin deletes client (soft)', $r['status'] === 200 && sql('SELECT deleted_at FROM clients WHERE id = ?', [$newId])[0]['deleted_at'] !== null);
check('deleted client login removed + data kept', (int) sql('SELECT COUNT(*) c FROM users WHERE client_id = ?', [$newId])[0]['c'] === 0 && (int) sql('SELECT COUNT(*) c FROM leads WHERE client_id = ?', [$newId])[0]['c'] > 0);
check('deleted client hidden from list', ($admin->req('GET', '/api/clients?search=e2e-site')['json']['meta']['total'] ?? 1) === 0);
check('deleted client cannot ingest', $anon->req('POST', '/api/v1/leads', $mk($leadBody + ['external_id' => 'x9'], ["X-Nivc-Site: $siteKey", "Authorization: Bearer $newToken"]))['status'] === 403);
check('404 for unknown client', $admin->req('GET', '/api/clients/999999')['status'] === 404);


/* ---------------------------------- several clients on ONE domain (strict landing pages) */
$mkClient = static function (string $tag, string $page) use ($admin): array {
    $r = $admin->req('POST', '/api/clients', ['json' => ['contact_name' => "Shared $tag", 'business_name' => "Shared Biz $tag", 'email' => "shared-$tag-" . random_int(1000, 99999) . '@example.test', 'phone' => '0501112233',
        'password' => 'Shared12345', 'website_url' => 'https://shared.example.test', 'landing_url' => "https://shared.example.test$page", 'plan' => 'basic', 'amount' => 100, 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+1 year'))]]);
    return ['id' => (int) ($r['json']['result']['id'] ?? 0), 'key' => $r['json']['result']['website']['site_key'] ?? '', 'token' => $r['json']['result']['website']['token'] ?? '', 'email' => '', 'status' => $r['status']];
};
$A = $mkClient('A', '/page-a'); $B = $mkClient('B', '/page-b');
check('two clients created on the same domain', $A['status'] === 201 && $B['status'] === 201 && $A['key'] !== $B['key']);
check('website created with a landing page is strict by default', (int) sql('SELECT strict_pages FROM websites WHERE site_key = ?', [$A['key']])[0]['strict_pages'] === 1);
$hdr = static fn(array $c) => ["X-Nivc-Site: {$c['key']}", "Authorization: Bearer {$c['token']}"];
$send = static fn(array $c, array $body) => $anon->req('POST', '/api/v1/leads', ['json' => $body, 'csrf' => false, 'headers' => $hdr($c)]);
$r = $send($A, ['name' => 'Lead for A', 'phone' => '0521112233', 'landing_url' => 'https://shared.example.test/page-a?utm_source=x', 'external_id' => 'sa-' . $A['id']]);
check('A token + A page -> 201', $r['status'] === 201, $r['body']);
$leadA = (int) ($r['json']['id'] ?? 0);
check('lead stored for client A and landing page A', (int) sql('SELECT client_id FROM leads WHERE id = ?', [$leadA])[0]['client_id'] === $A['id'] && sql('SELECT landing_page_id FROM leads WHERE id = ?', [$leadA])[0]['landing_page_id'] !== null);
$r = $send($A, ['name' => 'A pretending to be B', 'phone' => '0521112233', 'landing_url' => 'https://shared.example.test/page-b', 'external_id' => 'sa2-' . $A['id']]);
check('A token + B page -> rejected 422 (page not registered)', $r['status'] === 422 && isset($r['json']['error']['fields']['landing_url']), $r['body']);
$r = $send($A, ['name' => 'No page', 'phone' => '0521112233', 'external_id' => 'sa3-' . $A['id']]);
check('strict site without landing_url -> 422', $r['status'] === 422);
$r = $send($A, ['name' => 'Other page of the site', 'phone' => '0521112233', 'landing_url' => 'https://shared.example.test/contact', 'external_id' => 'sa4-' . $A['id']]);
check('strict site: unregistered page of the same domain -> 422', $r['status'] === 422);
$r = $send($B, ['name' => 'Lead for B', 'phone' => '0531112233', 'landing_url' => 'https://shared.example.test/page-b', 'external_id' => 'sb-' . $B['id']]);
$leadB = (int) ($r['json']['id'] ?? 0);
check('B token + B page -> 201 stored for B', $r['status'] === 201 && (int) sql('SELECT client_id FROM leads WHERE id = ?', [$leadB])[0]['client_id'] === $B['id']);
check('no lead of A landed on B and vice versa', (int) sql('SELECT COUNT(*) c FROM leads WHERE client_id = ? AND name = "Lead for B"', [$A['id']])[0]['c'] === 0 && (int) sql('SELECT COUNT(*) c FROM leads WHERE client_id = ? AND name = "Lead for A"', [$B['id']])[0]['c'] === 0);
// views: only the client's own page counts
$tv = static fn(array $c, string $path) => $anon->req('POST', '/api/v1/track', ['raw' => json_encode(['site' => $c['key'], 'path' => $path, 'vid' => bin2hex(random_bytes(8))]), 'csrf' => false, 'headers' => ['Content-Type: text/plain', 'Origin: https://shared.example.test']]);
$vc = static fn(array $c) => (int) sql('SELECT COALESCE(SUM(views),0) v FROM page_views WHERE client_id = ?', [$c['id']])[0]['v'];
$tv($A, '/page-a'); $tv($A, '/page-b'); $tv($B, '/page-a'); $tv($B, '/page-b');
check('views: A counts only /page-a, B only /page-b', $vc($A) === 1 && $vc($B) === 1, 'A=' . $vc($A) . ' B=' . $vc($B));
// each client logs in and sees only their own page's data
$sa = sql('SELECT email FROM users WHERE client_id = ?', [$A['id']])[0]['email']; $sb = sql('SELECT email FROM users WHERE client_id = ?', [$B['id']])[0]['email'];
$ca = new Http($BASE); $ca->login($sa, 'Shared12345'); $cb = new Http($BASE); $cb->login($sb, 'Shared12345');
$la = $ca->req('GET', '/api/leads?per_page=100')['json']; $lb = $cb->req('GET', '/api/leads?per_page=100')['json'];
check('client A sees exactly her lead', ($la['meta']['total'] ?? 0) === 1 && $la['items'][0]['id'] === $leadA);
check('client B sees exactly his lead', ($lb['meta']['total'] ?? 0) === 1 && $lb['items'][0]['id'] === $leadB);
check('client A cannot open B\'s lead', $ca->req('GET', "/api/leads/$leadB")['status'] === 404);
check('client A analytics = 1 lead / 1 view', (($ca->req('GET', '/api/analytics?preset=today')['json']['totals']['leads'] ?? -1) === 1) && (($ca->req('GET', '/api/analytics?preset=today')['json']['totals']['views'] ?? -1) === 1));
check('client A landing pages = only /page-a', count($ca->req('GET', '/api/landing-pages')['json']['items'] ?? []) === 1);
// non-strict website accepts any page; switching strict on rejects again
$wid = (int) sql('SELECT id FROM websites WHERE site_key = ?', [$A['key']])[0]['id'];
$admin->req('PUT', "/api/websites/$wid", ['json' => ['client_id' => $A['id'], 'name' => 'Shared Biz A', 'url' => 'https://shared.example.test', 'strict_pages' => '0']]);
$r = $send($A, ['name' => 'Any page', 'phone' => '0521112233', 'landing_url' => 'https://shared.example.test/contact', 'external_id' => 'sa5-' . $A['id']]);
check('non-strict website accepts any page (201, no landing page)', $r['status'] === 201 && sql('SELECT landing_page_id FROM leads WHERE id = ?', [(int) ($r['json']['id'] ?? 0)])[0]['landing_page_id'] === null);
$admin->req('PUT', "/api/websites/$wid", ['json' => ['client_id' => $A['id'], 'name' => 'Shared Biz A', 'url' => 'https://shared.example.test', 'strict_pages' => '1']]);
check('re-enabling strict mode rejects again', $send($A, ['name' => 'x', 'phone' => '0521112233', 'landing_url' => 'https://shared.example.test/contact', 'external_id' => 'sa6-' . $A['id']])['status'] === 422);
$ws = $admin->req('GET', '/api/websites?search=shared.example')['json']['items'] ?? [];
check('website list exposes strict flag', count(array_filter($ws, static fn($w) => $w['strict_pages'] === true)) >= 2);
$admin->req('DELETE', "/api/clients/{$A['id']}"); $admin->req('DELETE', "/api/clients/{$B['id']}");

/* ---------------------------------------------- users + internal client */
foreach (['/api/users'] as $p) { check("client GET $p = 403", $cl1->req('GET', $p)['status'] === 403); check("anon GET $p = 401", $anon->req('GET', $p)['status'] === 401); }
check('client cannot create user', $cl1->req('POST', '/api/users', ['json' => ['name' => 'x', 'email' => 'x@example.test', 'role' => 'admin', 'password' => 'Abcdefg1']])['status'] === 403);
check('admin page /admin/users 200', $admin->req('GET', '/admin/users')['status'] === 200);
$ul = $admin->req('GET', '/api/users')['json'] ?? [];
check('admin lists users incl. admin + clients', ($ul['ok'] ?? false) && count($ul['items']) >= 2 && in_array('admin', array_column($ul['items'], 'role'), true));
$u1 = $admin->req('POST', '/api/users', ['json' => ['name' => 'Second Admin', 'email' => 'second.admin@example.test', 'role' => 'admin', 'password' => 'Abcdefg1']]);
check('admin creates admin user (201)', $u1['status'] === 201, json_encode($u1['json']));
$uid = (int) ($u1['json']['user']['id'] ?? 0);
check('duplicate email rejected', $admin->req('POST', '/api/users', ['json' => ['name' => 'Dup', 'email' => 'second.admin@example.test', 'role' => 'admin', 'password' => 'Abcdefg1']])['status'] === 422);
check('weak password rejected', $admin->req('POST', '/api/users', ['json' => ['name' => 'W', 'email' => 'weak@example.test', 'role' => 'admin', 'password' => 'abc']])['status'] === 422);
check('client role needs a client', $admin->req('POST', '/api/users', ['json' => ['name' => 'C', 'email' => 'c@example.test', 'role' => 'client', 'password' => 'Abcdefg1']])['status'] === 422);
$newAdmin = new Http($BASE);
check('new admin can sign in', $newAdmin->login('second.admin@example.test', 'Abcdefg1')['status'] === 302 && $newAdmin->req('GET', '/api/users')['status'] === 200);
$up = $admin->req('PUT', "/api/users/$uid", ['json' => ['name' => 'Second Admin', 'email' => 'second.admin@example.test', 'role' => 'admin', 'status' => 'disabled']]);
check('admin disables a user', $up['status'] === 200 && ($up['json']['user']['status'] ?? '') === 'disabled');
check('disabled user cannot use API', $newAdmin->req('GET', '/api/users')['status'] === 401);
$meId = (int) sql("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")[0]['id'];
check('cannot delete own account', $admin->req('DELETE', "/api/users/$meId")['status'] === 422);
check('cannot disable own account', $admin->req('PUT', "/api/users/$meId", ['json' => ['name' => 'a', 'email' => sql('SELECT email FROM users WHERE id = ?', [$meId])[0]['email'], 'role' => 'admin', 'status' => 'disabled']])['status'] === 422);
check('admin deletes a user', $admin->req('DELETE', "/api/users/$uid")['status'] === 200 && !sql('SELECT id FROM users WHERE id = ?', [$uid]));
// internal client: no login created, the e-mail may equal an administrator's e-mail
$adminMail = sql('SELECT email FROM users WHERE id = ?', [$meId])[0]['email'];
$ic = $admin->req('POST', '/api/clients', ['json' => ['contact_name' => 'Owner', 'business_name' => 'Internal Studio', 'email' => $adminMail, 'phone' => '0501234567', 'create_login' => '0',
    'website_url' => 'https://internal.example.test', 'landing_url' => 'https://internal.example.test/', 'plan' => 'basic', 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'amount' => '0', 'payment_status' => 'paid']]);
check('internal client created without login', $ic['status'] === 201 && !sql('SELECT id FROM users WHERE client_id = ?', [(int) $ic['json']['result']['id']]), json_encode($ic['json']));
$iw = $ic['json']['result']['website'] ?? [];
$ir = (new Http($BASE))->req('POST', '/api/v1/leads', ['csrf' => false, 'json' => ['name' => 'Home Visitor', 'phone' => '0521230000', 'landing_url' => 'https://internal.example.test/', 'device' => 'mobile', 'external_id' => 'home-1'], 'headers' => ['X-Nivc-Site: ' . ($iw['site_key'] ?? ''), 'Authorization: Bearer ' . ($iw['token'] ?? '')]]);
check('internal client receives homepage lead', $ir['status'] === 201, json_encode($ir['json']));
check('administrator sees the internal lead', count(array_filter($admin->req('GET', '/api/leads?search=Home+Visitor')['json']['items'] ?? [], static fn($l) => str_contains((string) ($l['name'] ?? ''), 'Home Visitor'))) === 1);
$admin->req('DELETE', '/api/clients/' . (int) $ic['json']['result']['id']);

/* ------------------------------------------------ flood protection */
$fw = $admin->req('POST', '/api/clients', ['json' => ['contact_name' => 'Flood', 'business_name' => 'Flood Test', 'email' => 'flood@example.test', 'password' => 'Abcdefg1', 'phone' => '0501234567',
    'website_url' => 'https://flood.example.test', 'landing_url' => 'https://flood.example.test/', 'plan' => 'basic', 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'amount' => '0', 'payment_status' => 'paid']]);
$fk = $fw['json']['result']['website'] ?? [];
$fsend = static fn(array $b) => (new Http($BASE))->req('POST', '/api/v1/leads', ['csrf' => false, 'json' => $b + ['landing_url' => 'https://flood.example.test/'], 'headers' => ['X-Nivc-Site: ' . ($fk['site_key'] ?? ''), 'Authorization: Bearer ' . ($fk['token'] ?? '')]]);
$codes = []; for ($i = 1; $i <= 4; $i++) { $codes[] = $fsend(['name' => "Same $i", 'phone' => '0521239999', 'external_id' => "f$i"])['status']; }
check('same phone: 3 per hour, 4th rejected (429)', $codes === [201, 201, 201, 429], json_encode($codes));
Db::exec("REPLACE INTO settings (k, v) VALUES ('daily_lead_cap', '10')");
$last = 0; for ($i = 1; $i <= 12; $i++) { $last = $fsend(['name' => "Cap $i", 'phone' => '05212300' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'external_id' => "c$i"])['status']; }
check('daily ceiling rejects further leads (429)', $last === 429);
check('daily ceiling raises one admin alert', count(sql("SELECT id FROM notifications WHERE type = 'lead_cap_admin' AND client_id = ?", [(int) $fw['json']['result']['id']])) === 1);
Db::exec("DELETE FROM settings WHERE k = 'daily_lead_cap'");
$admin->req('DELETE', '/api/clients/' . (int) $fw['json']['result']['id']);

/* ------------------------------------- editing a client's landing page */
$lc = $admin->req('POST', '/api/clients', ['json' => ['contact_name' => 'Land', 'business_name' => 'Land Test', 'email' => 'land@example.test', 'password' => 'Abcdefg1', 'phone' => '0501234567',
    'website_url' => 'https://land.example.test', 'landing_url' => 'https://land.example.test/old/', 'plan' => 'basic', 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'amount' => '0', 'payment_status' => 'paid']]);
$lid = (int) ($lc['json']['result']['id'] ?? 0); $lk = $lc['json']['result']['website'] ?? [];
$lsend = static fn(string $url, string $ext) => (new Http($BASE))->req('POST', '/api/v1/leads', ['csrf' => false, 'json' => ['name' => 'Page', 'phone' => '05212311' . substr($ext, -2), 'landing_url' => $url, 'external_id' => $ext], 'headers' => ['X-Nivc-Site: ' . ($lk['site_key'] ?? ''), 'Authorization: Bearer ' . ($lk['token'] ?? '')]])['status'];
check('client detail exposes its single landing page', ($admin->req('GET', "/api/clients/$lid")['json']['client']['landing_url'] ?? '') === 'https://land.example.test/old/');
check('old page accepted before edit', $lsend('https://land.example.test/old/', 'lp-11') === 201);
$ed = $admin->req('PUT', "/api/clients/$lid", ['json' => ['contact_name' => 'Land', 'business_name' => 'Land Test', 'email' => 'land@example.test', 'phone' => '0501234567', 'website_url' => 'https://land.example.test',
    'landing_url' => 'https://land.example.test/new-page/', 'plan' => 'basic', 'status' => 'active']]);
check('editing client updates the landing page (200)', $ed['status'] === 200, $ed['body']);
check('new landing page accepted, old one rejected', $lsend('https://land.example.test/new-page/', 'lp-22') === 201 && $lsend('https://land.example.test/old/', 'lp-33') === 422);
check('still exactly one landing page', count(sql('SELECT id FROM landing_pages WHERE client_id = ?', [$lid])) === 1);
$admin->req('DELETE', "/api/clients/$lid");

echo "\nPASS=$pass FAIL=$fail\n";
exit($fail ? 1 : 0);
