<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Auth;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\Validator;

/** Client (tenant) management. Admin-only; controllers enforce the role. */
final class ClientService
{
    private const SORTS = [
        'name' => 'business_name', 'leads' => 'leads_count', 'views' => 'views', 'paid' => 'amount_paid',
        'start' => 'start_date', 'expires' => 'end_date', 'created' => 'created_at', 'conversion' => 'conversion',
    ];

    private static function baseSql(): string
    {
        return "SELECT c.*, cs.plan AS sub_plan, cs.start_date, cs.end_date, cs.payment_status, cs.id AS subscription_id,
                COALESCE(paid.amount, 0) AS amount_paid, COALESCE(lpc.cnt, 0) AS pages_count, COALESCE(ldc.cnt, 0) AS leads_count,
                COALESCE(pvc.views, 0) AS views, COALESCE(wsc.total, 0) AS sites_total, COALESCE(wsc.connected, 0) AS sites_connected,
                ldc.cnt * 100 / NULLIF(pvc.views, 0) AS conversion,
                DATEDIFF(cs.end_date, ?) AS days_left,
                CASE WHEN c.status = 'disabled' THEN 'disabled'
                     WHEN cs.end_date IS NULL THEN 'active'
                     WHEN DATEDIFF(cs.end_date, ?) < 0 THEN 'expired'
                     WHEN DATEDIFF(cs.end_date, ?) <= " . Domain::SOON_DAYS . " THEN 'expiring'
                     ELSE 'active' END AS account_status
            FROM clients c
            LEFT JOIN subscriptions cs ON cs.id = (SELECT s.id FROM subscriptions s WHERE s.client_id = c.id ORDER BY s.end_date DESC, s.id DESC LIMIT 1)
            LEFT JOIN (SELECT client_id, SUM(amount) AS amount FROM subscriptions WHERE payment_status IN ('paid') GROUP BY client_id) paid ON paid.client_id = c.id
            LEFT JOIN (SELECT client_id, COUNT(*) AS cnt FROM landing_pages GROUP BY client_id) lpc ON lpc.client_id = c.id
            LEFT JOIN (SELECT client_id, COUNT(*) AS cnt FROM leads GROUP BY client_id) ldc ON ldc.client_id = c.id
            LEFT JOIN (SELECT client_id, SUM(views) AS views FROM page_views GROUP BY client_id) pvc ON pvc.client_id = c.id
            LEFT JOIN (SELECT client_id, COUNT(*) AS total, SUM(connection_status = 'connected') AS connected FROM websites GROUP BY client_id) wsc ON wsc.client_id = c.id
            WHERE c.deleted_at IS NULL";
    }

    public static function list(array $q): array
    {
        $today = NowTime::today();
        $w = [];
        $p = [$today, $today, $today];
        $s = trim((string) ($q['search'] ?? ''));
        if ($s !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $parts = ['business_name LIKE ?', 'contact_name LIKE ?', 'email LIKE ?', 'phone LIKE ?', 'website_url LIKE ?'];
            array_push($p, $like, $like, $like, $like, $like);
            $digits = ltrim(preg_replace('/\D+/', '', $s) ?? '', '0');
            if (strlen($digits) >= 3) {
                $parts[] = 'whatsapp_phone LIKE ?';
                $p[] = '%' . $digits . '%';
            }
            $w[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (in_array($q['status'] ?? '', ['active', 'disabled', 'expired', 'expiring'], true)) {
            $w[] = 'account_status = ?';
            $p[] = $q['status'];
        }
        if (in_array($q['payment_status'] ?? '', Domain::PAYMENT, true)) {
            $w[] = 'payment_status = ?';
            $p[] = $q['payment_status'];
        }
        if (in_array($q['connection'] ?? '', ['connected', 'disconnected'], true)) {
            $w[] = $q['connection'] === 'connected' ? '(sites_total > 0 AND sites_connected = sites_total)' : '(sites_total = 0 OR sites_connected < sites_total)';
        }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $pg    = Paginator::params($q);
        $total = (int) Db::val('SELECT COUNT(*) FROM (' . self::baseSql() . ') t' . $where, $p);
        $sort  = self::SORTS[$q['sort'] ?? ''] ?? 'created_at';
        $dir   = (($q['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $rows  = Db::all('SELECT * FROM (' . self::baseSql() . ') t' . $where . " ORDER BY {$sort} {$dir}, id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        return ['items' => array_map([self::class, 'present'], $rows), 'meta' => Paginator::meta($total, $pg['page'], $pg['per'])];
    }

    public static function present(array $r): array
    {
        $left = $r['days_left'] === null ? null : (int) $r['days_left'];
        return [
            'id' => (int) $r['id'], 'client_id' => $r['public_id'], 'contact_name' => $r['contact_name'], 'business_name' => $r['business_name'],
            'email' => $r['email'], 'phone' => $r['phone'], 'whatsapp_url' => Phone::whatsappUrl((string) $r['whatsapp_phone']),
            'website_url' => $r['website_url'], 'plan' => $r['plan'], 'status' => $r['status'], 'account_status' => $r['account_status'],
            'notes' => (string) $r['notes'], 'created_at' => $r['created_at'],
            'pages_count' => (int) $r['pages_count'], 'leads_count' => (int) $r['leads_count'], 'views' => (int) $r['views'],
            'conversion' => $r['conversion'] === null ? null : round((float) $r['conversion'], 1),
            'amount_paid' => (float) $r['amount_paid'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
            'payment_status' => $r['payment_status'], 'subscription_id' => $r['subscription_id'] === null ? null : (int) $r['subscription_id'],
            'days_left' => $left, 'sites_total' => (int) $r['sites_total'], 'sites_connected' => (int) $r['sites_connected'],
            'connection' => (int) $r['sites_total'] === 0 ? 'none' : ((int) $r['sites_connected'] === (int) $r['sites_total'] ? 'connected' : 'disconnected'),
        ];
    }

    public static function get(int $id): array
    {
        $today = NowTime::today();
        $r = Db::one('SELECT * FROM (' . self::baseSql() . ') t WHERE id = ?', [$today, $today, $today, $id]);
        if (!$r) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $out = self::present($r);
        $pages = Db::all("SELECT id, url FROM landing_pages WHERE client_id = ? AND status <> 'archived' ORDER BY id", [$id]);
        $out['landing_url'] = count($pages) === 1 ? $pages[0]['url'] : '';
        $out['landing_count'] = count($pages);
        return $out;
    }

    /** @return array validated + sanitized fields, throws 422 */
    private static function validate(array $in, bool $creating, ?int $clientId): array
    {
        $v = new Validator($in);
        $d = [];
        $d['contact_name']  = $v->str('contact_name', true, 190);
        $d['business_name'] = $v->str('business_name', true, 190);
        $d['email']         = $v->email('email');
        $d['website_url']   = $v->url('website_url');
        $d['plan']          = $v->str('plan', false, 60);
        $d['status']        = $v->enum('status', ['active', 'disabled'], 'active');
        $d['notes']         = $v->str('notes', false, 5000, true);
        $phone = $v->str('phone', true, 40);
        $norm = $phone !== '' ? Phone::normalize($phone) : null;
        if ($phone !== '' && !$norm) {
            $v->fail('phone', t('validation.phone'));
        }
        $d['phone'] = $norm['display'] ?? $phone;
        $d['whatsapp_phone'] = $norm['intl'] ?? '';
        $wa = $v->str('whatsapp', false, 40);
        if ($wa !== '') {
            $n = Phone::normalize($wa);
            if (!$n) {
                $v->fail('whatsapp', t('validation.phone'));
            } else {
                $d['whatsapp_phone'] = $n['intl'];
            }
        }
        $pw = (string) ($in['password'] ?? '');
        // An "internal" client (e.g. the studio's own site) has no login: its leads are handled by the administrators.
        $d['create_login'] = !array_key_exists('create_login', $in) || !in_array(strtolower(trim((string) $in['create_login'])), ['0', 'false', 'no'], true);
        if ($creating && !$d['create_login']) {
            $pw = '';
        } elseif ($creating || $pw !== '') {
            if (!Auth::validPasswordRule($pw)) {
                $v->fail('password', t('validation.password_rule'));
            }
        }
        $d['password'] = $pw;
        $d['start_date']     = $v->date('start_date', $creating);
        $d['end_date']       = $v->date('end_date', $creating);
        $d['amount']         = $v->money('amount', $creating);
        $d['payment_status'] = $v->enum('payment_status', Domain::PAYMENT, 'paid');
        $d['landing_url']    = $v->url('landing_url');
        if ($d['start_date'] && $d['end_date'] && $d['end_date'] <= $d['start_date']) {
            $v->fail('end_date', t('validation.end_after_start'));
        }
        // E-mail is the login name: must be unique across users.
        if ($d['email'] !== '' && !isset($v->errors()['email']) && ($d['create_login'] || !$creating)) {
            $uid = Db::val('SELECT u.id FROM users u WHERE u.email = ? AND (u.client_id IS NULL OR u.client_id <> ?)', [$d['email'], $clientId ?? 0]);
            if ($uid) {
                $v->fail('email', t('validation.email_taken'));
            }
        }
        $v->check();
        return $d;
    }

    /** @return array{id:int,client_id:string,website?:array} */
    public static function create(array $in): array
    {
        $d = self::validate($in, true, null);
        return Db::tx(static function () use ($d) {
            $now = NowTime::mysql();
            $publicId = 'cl_' . bin2hex(random_bytes(8));
            $id = Db::insert('clients', [
                'public_id' => $publicId, 'contact_name' => $d['contact_name'], 'business_name' => $d['business_name'], 'email' => $d['email'],
                'phone' => $d['phone'], 'whatsapp_phone' => $d['whatsapp_phone'], 'website_url' => $d['website_url'], 'plan' => $d['plan'],
                'status' => $d['status'], 'notes' => $d['notes'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($d['create_login']) {
                Db::insert('users', [
                    'client_id' => $id, 'role' => 'client', 'email' => $d['email'], 'password_hash' => Auth::hashPassword($d['password']),
                    'name' => $d['contact_name'], 'locale' => 'he', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            BillingService::addSubscription($id, [
                'plan' => $d['plan'], 'amount' => $d['amount'], 'start_date' => $d['start_date'], 'end_date' => $d['end_date'],
                'payment_status' => $d['payment_status'], 'note' => '',
            ]);
            $out = ['id' => $id, 'client_id' => $publicId];
            // A client may start with a website and landing page.
            if ($d['website_url'] !== '') {
                $site = WebsiteService::create($id, $d['business_name'], $d['website_url'], $d['landing_url'] !== ''); // with a landing page: only that page is accepted
                $out['website'] = ['id' => $site['id'], 'site_key' => $site['site_key'], 'token' => $site['token']];
                if ($d['landing_url'] !== '') {
                    LandingPageService::create($id, $site['id'], $d['business_name'], $d['landing_url']);
                }
            }
            return $out;
        });
    }

    public static function update(int $id, array $in): void
    {
        $row = Db::one('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$row) {
            throw HttpException::notFound(t('error.not_found'));
        }
        // Edit form: dates/amount/payment fields update the CURRENT subscription when supplied.
        $d = self::validate($in, false, $id);
        Db::tx(static function () use ($id, $d, $row) {
            $now = NowTime::mysql();
            Db::update('clients', [
                'contact_name' => $d['contact_name'], 'business_name' => $d['business_name'], 'email' => $d['email'], 'phone' => $d['phone'],
                'whatsapp_phone' => $d['whatsapp_phone'], 'website_url' => $d['website_url'], 'plan' => $d['plan'], 'status' => $d['status'],
                'notes' => $d['notes'], 'updated_at' => $now,
            ], ['id' => $id]);
            $u = ['email' => $d['email'], 'name' => $d['contact_name'], 'updated_at' => $now];
            if ($d['password'] !== '') {
                $u['password_hash'] = Auth::hashPassword($d['password']);
            }
            Db::update('users', $u, ['client_id' => $id]);
            self::syncLandingPage($id, $d['landing_url']);
            $sub = Db::val('SELECT id FROM subscriptions WHERE client_id = ? ORDER BY end_date DESC, id DESC LIMIT 1', [$id]);
            if ($sub && $d['start_date'] && $d['end_date']) {
                Db::update('subscriptions', [
                    'plan' => $d['plan'], 'start_date' => $d['start_date'], 'end_date' => $d['end_date'], 'payment_status' => $d['payment_status'],
                ] + ($d['amount'] !== null ? ['amount' => $d['amount']] : []), ['id' => $sub]);
            }
        });
    }

    /**
     * Edit form: the landing page address. One existing page -> its URL is updated (strict websites only accept registered pages,
     * so a stale address would silently drop leads); no page yet -> one is created on the client's website. Several pages -> untouched.
     */
    private static function syncLandingPage(int $clientId, string $url): void
    {
        if ($url === '') {
            return;
        }
        $pages = Db::all("SELECT id FROM landing_pages WHERE client_id = ? AND status <> 'archived' ORDER BY id", [$clientId]);
        if (count($pages) === 1) {
            Db::update('landing_pages', ['url' => $url, 'path_key' => Domain::pathKey($url), 'updated_at' => NowTime::mysql()], ['id' => (int) $pages[0]['id']]);
        } elseif (!$pages) {
            $wid = Db::val('SELECT id FROM websites WHERE client_id = ? ORDER BY id LIMIT 1', [$clientId]);
            $client = Db::val('SELECT business_name FROM clients WHERE id = ?', [$clientId]);
            if ($wid) {
                LandingPageService::create($clientId, (int) $wid, (string) $client, $url);
            }
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw HttpException::invalid(['status' => t('validation.invalid')]);
        }
        if (!Db::update('clients', ['status' => $status, 'updated_at' => NowTime::mysql()], ['id' => $id]) && !Db::val('SELECT id FROM clients WHERE id = ?', [$id])) {
            throw HttpException::notFound(t('error.not_found'));
        }
    }

    /** Soft delete: the client's data is kept, logins are removed. */
    public static function delete(int $id): void
    {
        if (!Db::val('SELECT id FROM clients WHERE id = ? AND deleted_at IS NULL', [$id])) {
            throw HttpException::notFound(t('error.not_found'));
        }
        Db::tx(static function () use ($id) {
            Db::update('clients', ['deleted_at' => NowTime::mysql(), 'status' => 'disabled'], ['id' => $id]);
            Db::exec('DELETE FROM users WHERE client_id = ?', [$id]);
        });
    }

    /** Lightweight id/name list for filter dropdowns. */
    public static function options(): array
    {
        return array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => $r['business_name']],
            Db::all('SELECT id, business_name FROM clients WHERE deleted_at IS NULL ORDER BY business_name LIMIT 1000'));
    }
}
