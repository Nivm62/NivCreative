<?php
declare(strict_types=1);

// Pure-logic unit tests (no database / server needed).   php tests/unit.php
define('NIVC_PANEL_VERSION', 'test');
require dirname(__DIR__) . '/src/autoload.php';
use Nivc\Core\{I18n, NowTime, Config};
use Nivc\Services\{Phone, Domain, Csv};

Config::set(['timezone' => 'Asia/Jerusalem']);
I18n::setLocale('he');
$pass = 0; $fail = 0;
function eq(mixed $a, mixed $b, string $label): void { global $pass, $fail; if ($a === $b) { $pass++; } else { $fail++; echo "FAIL $label: got " . json_encode($a, JSON_UNESCAPED_UNICODE) . ' expected ' . json_encode($b, JSON_UNESCAPED_UNICODE) . "\n"; } }

foreach (['050-1234567' => '972501234567', '0501234567' => '972501234567', '+972 50 123 4567' => '972501234567', '00972501234567' => '972501234567', '+972-050-1234567' => '972501234567',
          '972501234567' => '972501234567', '03-1234567' => '97231234567', '072-3456789' => '972723456789', '052 123-4567' => '972521234567'] as $in => $out) {
    eq(Phone::normalize((string) $in)['intl'] ?? null, $out, "phone $in");
}
foreach (['', 'abc', '12345', '050123', '0601234567', '+1 415 555 2671', '05012345678'] as $bad) eq(Phone::normalize($bad), null, "bad phone '$bad'");
eq(Phone::normalize('0501234567')['display'], '050-1234567', 'display mobile');
eq(Phone::whatsappUrl('972501234567'), 'https://wa.me/972501234567', 'wa url has no text param');
eq(Phone::whatsappUrl(''), '', 'empty wa url');

eq(NowTime::addYears('2024-02-29'), '2025-02-28', 'leap day');
eq(NowTime::daysBetween('2026-03-28', '2026-03-30'), 2, 'days across DST');
eq(NowTime::daysBetween('2026-10-25', '2026-10-24'), -1, 'negative days');

// Source classification
$c = static fn(string $s, string $m, string $r) => Domain::classifySource($s, $m, $r);
eq($c('instagram', '', ''), 'instagram', 'utm instagram'); eq($c('ig', '', ''), 'instagram', 'utm ig');
eq($c('Facebook', 'cpc', ''), 'facebook', 'utm facebook'); eq($c('fb', '', ''), 'facebook', 'utm fb');
eq($c('google', 'cpc', ''), 'google', 'utm google'); eq($c('whatsapp', '', ''), 'whatsapp', 'utm whatsapp');
eq($c('newsletter', '', ''), 'other', 'utm other');
eq($c('', '', ''), 'direct', 'no referrer = direct'); eq($c('', '', 'https://www.google.com/search?q=x'), 'organic', 'google referrer = organic');
eq($c('', '', 'https://l.instagram.com/'), 'instagram', 'instagram referrer'); eq($c('', '', 'https://m.facebook.com/'), 'facebook', 'facebook referrer');
eq($c('', '', 'https://wa.me/123'), 'whatsapp', 'wa referrer'); eq($c('', '', 'https://example.org/page'), 'other', 'other referrer');
eq($c('', 'organic', ''), 'organic', 'medium organic');

eq(Domain::deviceFromUa('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile/15E148'), 'mobile', 'iphone');
eq(Domain::deviceFromUa('Mozilla/5.0 (iPad; CPU OS 17_0)'), 'tablet', 'ipad');
eq(Domain::deviceFromUa('Mozilla/5.0 (Linux; Android 14; Pixel 8) Mobile Safari'), 'mobile', 'android phone');
eq(Domain::deviceFromUa('Mozilla/5.0 (Windows NT 10.0) Chrome/120'), 'desktop', 'desktop'); eq(Domain::deviceFromUa(''), 'unknown', 'empty ua');

eq(Domain::pathKey('https://www.Example.com/Promo/?x=1#a'), '/promo', 'path key'); eq(Domain::pathKey('https://example.com'), '/', 'home path');
eq(Domain::normalizePath('/%D7%A9%D7%9C%D7%95%D7%9D/'), '/שלום', 'hebrew slug'); eq(Domain::host('https://www.Foo.co.il/x'), 'foo.co.il', 'host');

// REST probe candidates
eq(Nivc\Services\WebsiteService::restCandidates('https://nivcreative.com'), ['https://nivcreative.com/wp-json/', 'https://nivcreative.com/?rest_route=/'], 'rest candidates: root');
eq(Nivc\Services\WebsiteService::restCandidates('https://nivcreative.com/shir-boutique/'), ['https://nivcreative.com/shir-boutique/wp-json/', 'https://nivcreative.com/wp-json/', 'https://nivcreative.com/?rest_route=/'], 'rest candidates: page URL falls back to site root');

// Aging
$now = time(); $fmt = static fn(int $ago) => date('Y-m-d H:i:s', $now - $ago);
$a = static fn(string $st, int $created, int $act) => \Nivc\Services\LeadService::aging(['status' => $st, 'created_at' => $fmt($created), 'last_activity_at' => $fmt($act)]);
eq($a('new', 600, 600), 'ok', 'fresh lead'); eq($a('new', 3 * 3600, 3 * 3600), 'waiting_2h', '3h new'); eq($a('new', 30 * 3600, 30 * 3600), 'waiting_24h', '30h new');
eq($a('contacted', 5 * 86400, 4 * 86400), 'stale', 'stale contacted'); eq($a('contacted', 5 * 86400, 3600), 'ok', 'recent activity'); eq($a('closed', 9 * 86400, 9 * 86400), 'ok', 'closed never ages');

// CSV
$csv = Csv::build(['a', 'b'], [['=1+1', 'x,"y"'], ['+cmd', '-5'], ["@sum", 'ok']]);
eq(str_starts_with($csv, "\xEF\xBB\xBF"), true, 'csv BOM'); eq(str_contains($csv, "'=1+1"), true, 'csv formula =');
eq(str_contains($csv, "'+cmd"), true, 'csv formula +'); eq(str_contains($csv, "'@sum"), true, 'csv formula @'); eq(str_contains($csv, ',-5'), true, 'csv numeric negative untouched');

// Validation helpers
$v = new Nivc\Core\Validator(['email' => 'bad', 'date' => '2026-02-30', 'amount' => '-1', 'url' => 'javascript:alert(1)', 'name' => str_repeat('x', 300)]);
$v->email('email'); $v->date('date'); $v->money('amount'); $v->url('url'); $v->str('name', true, 190);
eq(array_keys($v->errors()), ['email', 'date', 'amount', 'url', 'name'], 'validator flags all bad fields');
$v = new Nivc\Core\Validator(['url' => 'example.com/x', 'date' => '2026-02-28', 'amount' => '12.345']);
eq($v->url('url'), 'https://example.com/x', 'url gets https'); eq($v->date('date'), '2026-02-28', 'valid date'); eq($v->money('amount'), 12.35, 'money rounds'); eq($v->errors(), [], 'no errors');
eq(Nivc\Core\Auth::validPasswordRule('Abcdef12'), true, 'password ok'); eq(Nivc\Core\Auth::validPasswordRule('abcdefgh'), false, 'password needs digit'); eq(Nivc\Core\Auth::validPasswordRule('Ab1'), false, 'password too short');

// i18n parity
$he = require dirname(__DIR__) . '/lang/he.php'; $en = require dirname(__DIR__) . '/lang/en.php';
eq(array_keys($he) === array_keys($en), true, 'he/en have identical keys');
eq(I18n::t('dash.waiting_banner', ['count' => 4]), '4 לידים עדיין ממתינים לטיפול', 'translation + params');

// Crypto: sodium backend + blobs from either backend
Config::set(['app_key' => base64_encode(random_bytes(32))]);
$b = Nivc\Core\Crypto::encrypt('secret value');
eq(str_starts_with($b, 's1:'), true, 'sodium blob prefix'); eq(Nivc\Core\Crypto::decrypt($b), 'secret value', 'sodium roundtrip');
eq(Nivc\Core\Crypto::decrypt('garbage'), null, 'garbage blob');

echo "PASS=$pass FAIL=$fail\n";
exit($fail ? 1 : 0);
