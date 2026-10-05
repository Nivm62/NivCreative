<?php
declare(strict_types=1);

// DEVELOPMENT ONLY: fills the database with fake clients/leads/views. Never run on production.
//   php bin/seed-demo.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('NIVC_PANEL_VERSION', '1.0.0');
require dirname(__DIR__) . '/src/autoload.php';
use Nivc\Core\{Auth, Config, Db, I18n, NowTime};
use Nivc\Services\{BillingService, ClientService, LandingPageService, LeadService, MaintenanceService, WebsiteService};

if (!Config::installed() || Config::get('env') !== 'local') {
    fwrite(STDERR, "Refusing to seed: env must be 'local' and the panel installed.\n");
    exit(1);
}
date_default_timezone_set('Asia/Jerusalem');
I18n::setLocale('he');
mt_srand(42);
$today = NowTime::today();
$d = static fn(int $n) => (new DateTimeImmutable($today))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');

$clients = [
    ['מיכל כהן', 'קליניקה לטיפולי פנים', 'michal@clinic.example', '0501234567', 'https://clinic-michal.example', 'pro', 1500, $d(-120), $d(245)],
    ['שיר לוי', 'חנות אופנה', 'shir@fashion.example', '0527654321', 'https://shir-fashion.example', 'basic', 900, $d(-330), $d(35)],
    ['דניאל כהן', 'מאמן כושר', 'daniel@fit.example', '0541112233', 'https://daniel-fit.example', 'premium', 2400, $d(-358), $d(7)],
    ['רוני אדרי', 'ייעוץ עסקי', 'roni@consult.example', '0584567890', 'https://roni-consult.example', 'pro', 1500, $d(-400), $d(-35)],
    ['אלה מזרחי', 'עיצוב פנים', 'ella@design.example', '0509876543', 'https://ella-design.example', 'basic', 900, $d(-60), $d(305)],
    ['אופיר חיים', 'מוסך אופיר', 'ofir@garage.example', '031234567', 'https://ofir-garage.example', 'custom', 600, $d(-200), $d(165)],
];
$sources = [['instagram', 'utm_social_a', 'cpc'], ['facebook', 'summer_sale', 'paid'], ['google', 'brand_search', 'cpc'], ['', '', ''], ['whatsapp', 'status_share', 'social'], ['google', '', 'organic']];
$names = ['יעל ברק', 'עומר דהן', 'נועה פרץ', 'איתי שמש', 'מאיה גולן', 'תומר אביב', 'ליאור רז', 'דנה אלון', 'גיא נבון', 'הילה סער', 'רן עמית', 'טל ברוש'];
$statuses = ['new', 'new', 'contacted', 'in_progress', 'meeting', 'proposal', 'closed', 'closed', 'not_relevant'];
$sites = [];
foreach ($clients as $i => $c) {
    $res = ClientService::create(['contact_name' => $c[0], 'business_name' => $c[1], 'email' => $c[2], 'phone' => $c[3], 'password' => 'Client12345', 'website_url' => $c[4],
        'landing_url' => $c[4] . '/landing', 'plan' => $c[5], 'amount' => $c[6], 'start_date' => $c[7], 'end_date' => $c[8], 'payment_status' => $i === 3 ? 'overdue' : 'paid']);
    $site = Db::one('SELECT * FROM websites WHERE id = ?', [$res['website']['id']]);
    Db::update('websites', ['connection_status' => $i === 5 ? 'error' : 'connected', 'last_seen_at' => NowTime::mysql(), 'connector_version' => $i === 5 ? '' : '1.0.0', 'wp_version' => '6.8'], ['id' => $site['id']]);
    $lp = Db::one('SELECT * FROM landing_pages WHERE website_id = ?', [$site['id']]);
    $sites[] = [$res['id'], $site, $lp, $res['website']['token']];
}
// Leads + views over the last 75 days
foreach ($sites as $i => [$cid, $site, $lp]) {
    $per = [4, 3, 3, 2, 2, 1][$i];
    for ($day = 75; $day >= 0; $day--) {
        $date = $d(-$day);
        $views = mt_rand(8, 40) * ($i === 0 ? 2 : 1);
        for ($v = 0; $v < $views; $v++) {
            $src = $sources[mt_rand(0, count($sources) - 1)];
            $hash = substr(hash('sha256', "$i-$day-$v"), 0, 32);
            $ts = $date . ' ' . sprintf('%02d:%02d:00', mt_rand(7, 22), mt_rand(0, 59));
            Db::exec('INSERT IGNORE INTO page_views (landing_page_id, day, visitor_hash, client_id, website_id, views, source, utm_source, utm_campaign, device, first_seen, last_seen) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$lp['id'], $date, $hash, $cid, $site['id'], 1, $src[0] ?: 'direct', $src[0], $src[1], ['mobile', 'mobile', 'desktop', 'tablet'][mt_rand(0, 3)], $ts, $ts]);
        }
        $n = (int) round($per * mt_rand(0, 100) / 100 * ($day < 5 ? 1.4 : 1));
        for ($k = 0; $k < $n; $k++) {
            $src = $sources[mt_rand(0, count($sources) - 1)];
            $res = LeadService::createFromWebsite($site + ['client_id' => $cid], [
                'name' => $names[mt_rand(0, count($names) - 1)], 'phone' => '05' . mt_rand(0, 4) . mt_rand(1000000, 9999999), 'email' => 'lead' . mt_rand(1, 99999) . '@mail.example',
                'message' => 'מעוניין/ת בפרטים נוספים', 'utm_source' => $src[0], 'utm_campaign' => $src[1], 'utm_medium' => $src[2], 'landing_url' => $lp['url'],
                'referrer' => $src[0] === '' && mt_rand(0, 1) ? 'https://www.google.com/' : '', 'external_id' => "seed-$i-$day-$k",
            ], '127.0.0.1', 'Mozilla/5.0 (iPhone)');
            $ts = $date . ' ' . sprintf('%02d:%02d:00', mt_rand(8, 21), mt_rand(0, 59));
            $st = $day < 1 ? 'new' : $statuses[mt_rand(0, count($statuses) - 1)];
            Db::exec('UPDATE leads SET created_at = ?, updated_at = ?, last_activity_at = ?, status = ?, deal_value = ?, closed_at = ?, first_contact_at = ? WHERE id = ?',
                [$ts, $ts, $ts, $st, $st === 'closed' ? mt_rand(8, 60) * 100 : null, $st === 'closed' ? $ts : null, $st === 'new' ? null : $ts, $res['id']]);
        }
    }
}
Db::exec('DELETE FROM notifications');
// A few earlier subscription periods for history
BillingService::addSubscription(1, ['plan' => 'basic', 'amount' => 900, 'start_date' => $d(-485), 'end_date' => $d(-120), 'payment_status' => 'paid']);
echo json_encode(MaintenanceService::run()), "\n";
echo "Seeded. Admin: niv@example.com / Admin12345 — Client: michal@clinic.example / Client12345\n";
