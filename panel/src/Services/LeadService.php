<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\Validator;

final class LeadService
{
    private const SORTS = ['created' => 'l.created_at', 'name' => 'l.name', 'status' => 'l.status', 'value' => 'l.deal_value', 'activity' => 'l.last_activity_at'];

    /* ------------------------------------------------------------- ingest */

    /**
     * Creates a lead for an authenticated website. client_id ALWAYS comes from the website record.
     *
     * @return array{id:int,duplicate:bool}
     */
    public static function createFromWebsite(array $site, array $in, string $ip, string $ua): array
    {
        $v = new Validator($in);
        $name    = $v->str('name', false, 190);
        $phoneIn = $v->str('phone', false, 40);
        $email   = $v->email('email', false);
        $message = $v->str('message', false, 5000, true);
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
            $utm[$k] = $v->str($k, false, 190);
        }
        $referrer = $v->str('referrer', false, 500);
        $landingUrl = $v->str('landing_url', false, 500);
        $external = $v->str('external_id', false, 64);
        $v->check();
        if ($name === '' && $phoneIn === '' && $email === '') {
            throw HttpException::invalid(['name' => t('validation.lead_contact_required')]);
        }
        // Never trust a client_id sent by the browser/connector: it must match the authenticated website.
        if (isset($in['client_id']) && (string) $in['client_id'] !== '' && (string) $in['client_id'] !== (string) Db::val('SELECT public_id FROM clients WHERE id = ?', [$site['client_id']])) {
            throw new HttpException(403, 'client_mismatch', 'client_mismatch');
        }
        if ($external !== '') {
            $dup = Db::val('SELECT id FROM leads WHERE website_id = ? AND external_id = ?', [$site['id'], $external]);
            if ($dup) {
                return ['id' => (int) $dup, 'duplicate' => true];
            }
        }
        $phone = $phoneIn;
        $intl  = '';
        if ($phoneIn !== '') {
            $n = Phone::normalize($phoneIn);
            if ($n) {
                $phone = $n['display'];
                $intl = $n['intl'];
            }
        }
        // Resolve landing page: explicit id (must belong to this website) or by URL path.
        $lpId = null;
        $explicit = $v->int('landing_page_id', 1);
        if ($explicit) {
            $lpId = Db::val('SELECT id FROM landing_pages WHERE id = ? AND website_id = ?', [$explicit, $site['id']]);
        }
        if (!$lpId && $landingUrl !== '') {
            $lpId = Db::val('SELECT id FROM landing_pages WHERE website_id = ? AND path_key = ? ORDER BY status = \'active\' DESC, id LIMIT 1', [$site['id'], Domain::pathKey($landingUrl)]);
        }
        $deviceIn = (string) ($in['device'] ?? '');
        $device = in_array($deviceIn, ['desktop', 'mobile', 'tablet'], true) ? $deviceIn : Domain::deviceFromUa($ua);
        $source = Domain::classifySource($utm['utm_source'], $utm['utm_medium'], $referrer);
        $now = NowTime::mysql();
        $id = Db::tx(static function () use ($site, $name, $phone, $intl, $email, $message, $utm, $referrer, $device, $source, $lpId, $external, $now) {
            $id = Db::insert('leads', [
                'client_id' => $site['client_id'], 'website_id' => $site['id'], 'landing_page_id' => $lpId ?: null, 'external_id' => $external !== '' ? $external : null,
                'name' => $name, 'phone' => $phone, 'phone_intl' => $intl, 'email' => $email, 'message' => $message, 'status' => 'new',
                'source' => $source, 'utm_source' => $utm['utm_source'], 'utm_medium' => $utm['utm_medium'], 'utm_campaign' => $utm['utm_campaign'],
                'utm_content' => $utm['utm_content'], 'utm_term' => $utm['utm_term'], 'referrer' => $referrer, 'device' => $device,
                'created_at' => $now, 'updated_at' => $now, 'last_activity_at' => $now,
            ]);
            self::activity($id, (int) $site['client_id'], null, 'created', ['source' => $source]);
            return $id;
        });
        $cname = (string) Db::val('SELECT business_name FROM clients WHERE id = ?', [$site['client_id']]);
        $label = $name !== '' ? $name : ($phone !== '' ? $phone : $email);
        NotificationService::notify('client', (int) $site['client_id'], 'new_lead', 'success', ['name' => $label, 'source' => t('source.' . $source)], '/leads?open=' . $id);
        NotificationService::notify('admin', (int) $site['client_id'], 'new_lead_admin', 'info', ['name' => $label, 'client' => $cname], '/admin/leads?open=' . $id);
        return ['id' => $id, 'duplicate' => false];
    }

    /* -------------------------------------------------------------- query */

    private static function filters(Actor $a, array $q, array &$p): string
    {
        $w = ['c.deleted_at IS NULL'];
        $cid = $a->scopeClientId($q['client_id'] ?? null);
        if ($cid !== null) {
            $w[] = 'l.client_id = ?';
            $p[] = $cid;
        }
        foreach (['website_id' => 'l.website_id', 'landing_page_id' => 'l.landing_page_id'] as $k => $col) {
            if (!empty($q[$k]) && ctype_digit((string) $q[$k])) {
                $w[] = "$col = ?";
                $p[] = (int) $q[$k];
            }
        }
        if (in_array($q['status'] ?? '', Domain::LEAD_STATUSES, true)) {
            $w[] = 'l.status = ?';
            $p[] = $q['status'];
        }
        if (in_array($q['source'] ?? '', Domain::SOURCES, true)) {
            $w[] = 'l.source = ?';
            $p[] = $q['source'];
        }
        if (($q['campaign'] ?? '') !== '') {
            $w[] = 'l.utm_campaign = ?';
            $p[] = mb_substr((string) $q['campaign'], 0, 190);
        }
        if (NIVC_valid_date($q['from'] ?? '')) {
            $w[] = 'l.created_at >= ?';
            $p[] = $q['from'] . ' 00:00:00';
        }
        if (NIVC_valid_date($q['to'] ?? '')) {
            $w[] = 'l.created_at <= ?';
            $p[] = $q['to'] . ' 23:59:59';
        }
        if (in_array($q['sub_status'] ?? '', ['active', 'expiring', 'expired', 'disabled'], true)) {
            $today = NowTime::today();
            $cond = [
                'active'   => "(cs.end_date IS NULL OR DATEDIFF(cs.end_date, '$today') > " . Domain::SOON_DAYS . ') AND c.status = \'active\'',
                'expiring' => "DATEDIFF(cs.end_date, '$today') BETWEEN 0 AND " . Domain::SOON_DAYS,
                'expired'  => "DATEDIFF(cs.end_date, '$today') < 0",
                'disabled' => "c.status = 'disabled'",
            ];
            $w[] = '(' . $cond[$q['sub_status']] . ')';
        }
        $now = NowTime::now();
        switch ($q['aging'] ?? '') {
            case 'waiting_2h':
                $w[] = "l.status = 'new' AND l.created_at <= ?";
                $p[] = $now->modify('-' . Domain::WAIT_WARN_H . ' hours')->format('Y-m-d H:i:s');
                break;
            case 'waiting_24h':
                $w[] = "l.status = 'new' AND l.created_at <= ?";
                $p[] = $now->modify('-' . Domain::WAIT_LATE_H . ' hours')->format('Y-m-d H:i:s');
                break;
            case 'stale':
                $w[] = "l.status NOT IN ('closed','not_relevant') AND l.last_activity_at <= ?";
                $p[] = $now->modify('-' . Domain::STALE_DAYS . ' days')->format('Y-m-d H:i:s');
                break;
        }
        $s = trim((string) ($q['search'] ?? ''));
        if ($s !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $parts = ['l.name LIKE ?', 'l.phone LIKE ?', 'l.email LIKE ?', 'l.message LIKE ?'];
            array_push($p, $like, $like, $like, $like);
            $digits = ltrim(preg_replace('/\D+/', '', $s) ?? '', '0');
            if (strlen($digits) >= 3) {
                $parts[] = 'l.phone_intl LIKE ?';
                $p[] = '%' . $digits . '%';
            }
            $w[] = '(' . implode(' OR ', $parts) . ')';
        }
        return implode(' AND ', $w);
    }

    private const FROM = 'FROM leads l
        JOIN clients c ON c.id = l.client_id
        LEFT JOIN websites w ON w.id = l.website_id
        LEFT JOIN landing_pages lp ON lp.id = l.landing_page_id
        LEFT JOIN subscriptions cs ON cs.id = (SELECT s.id FROM subscriptions s WHERE s.client_id = c.id ORDER BY s.end_date DESC, s.id DESC LIMIT 1)';

    public static function list(Actor $a, array $q): array
    {
        $p = [];
        $where = self::filters($a, $q, $p);
        $pg = Paginator::params($q);
        $total = (int) Db::val('SELECT COUNT(*) ' . self::FROM . ' WHERE ' . $where, $p);
        $sort = self::SORTS[$q['sort'] ?? ''] ?? 'l.created_at';
        $dir  = (($q['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $rows = Db::all(
            'SELECT l.*, c.business_name AS client_name, w.name AS website_name, lp.name AS landing_name ' . self::FROM
            . " WHERE {$where} ORDER BY {$sort} {$dir}, l.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $p
        );
        return ['items' => array_map([self::class, 'present'], $rows), 'meta' => Paginator::meta($total, $pg['page'], $pg['per'])];
    }

    public static function present(array $r, bool $full = false): array
    {
        $out = [
            'id' => (int) $r['id'], 'client_id' => (int) $r['client_id'], 'client_name' => $r['client_name'] ?? null,
            'name' => $r['name'], 'phone' => $r['phone'], 'email' => $r['email'], 'whatsapp_url' => Phone::whatsappUrl((string) $r['phone_intl']),
            'status' => $r['status'], 'deal_value' => $r['deal_value'] === null ? null : (float) $r['deal_value'],
            'source' => $r['source'], 'campaign' => $r['utm_campaign'], 'landing_name' => $r['landing_name'] ?? null, 'website_name' => $r['website_name'] ?? null,
            'created_at' => $r['created_at'], 'last_activity_at' => $r['last_activity_at'], 'aging' => self::aging($r),
        ];
        if ($full) {
            $out += [
                'message' => (string) $r['message'], 'utm_source' => $r['utm_source'], 'utm_medium' => $r['utm_medium'], 'utm_content' => $r['utm_content'],
                'utm_term' => $r['utm_term'], 'referrer' => $r['referrer'], 'device' => $r['device'], 'landing_page_id' => $r['landing_page_id'] === null ? null : (int) $r['landing_page_id'],
                'website_id' => $r['website_id'] === null ? null : (int) $r['website_id'],
            ];
        }
        return $out;
    }

    /** ok | waiting_2h | waiting_24h | stale (closed/irrelevant leads are never "aging"). */
    public static function aging(array $r): string
    {
        $now = NowTime::now()->getTimestamp();
        if ($r['status'] === 'new') {
            $age = $now - strtotime($r['created_at']);
            if ($age >= Domain::WAIT_LATE_H * 3600) {
                return 'waiting_24h';
            }
            if ($age >= Domain::WAIT_WARN_H * 3600) {
                return 'waiting_2h';
            }
            return 'ok';
        }
        if (!in_array($r['status'], ['closed', 'not_relevant'], true) && $now - strtotime($r['last_activity_at']) >= Domain::STALE_DAYS * 86400) {
            return 'stale';
        }
        return 'ok';
    }

    public static function findOwned(Actor $a, int $id): array
    {
        $r = Db::one('SELECT l.*, c.business_name AS client_name, w.name AS website_name, lp.name AS landing_name ' . self::FROM . ' WHERE l.id = ? AND c.deleted_at IS NULL', [$id]);
        if (!$r) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $a->assertOwns((int) $r['client_id']);
        return $r;
    }

    public static function detail(Actor $a, int $id): array
    {
        $r = self::findOwned($a, $id);
        $out = self::present($r, true);
        $out['notes'] = array_map(static fn($n) => [
            'id' => (int) $n['id'], 'body' => $n['body'], 'author' => $n['author'] ?? '', 'created_at' => $n['created_at'],
        ], Db::all('SELECT n.*, u.name AS author FROM lead_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.lead_id = ? ORDER BY n.created_at DESC, n.id DESC LIMIT 200', [$id]));
        $out['activity'] = array_map(static fn($x) => [
            'type' => $x['type'], 'meta' => json_decode((string) $x['meta'], true) ?: new \stdClass(), 'author' => $x['author'] ?? '', 'created_at' => $x['created_at'],
        ], Db::all('SELECT x.*, u.name AS author FROM lead_activity x LEFT JOIN users u ON u.id = x.user_id WHERE x.lead_id = ? ORDER BY x.created_at DESC, x.id DESC LIMIT 200', [$id]));
        return $out;
    }

    /* ------------------------------------------------------------- updates */

    public static function update(Actor $a, int $id, array $in): array
    {
        $lead = self::findOwned($a, $id);
        $v = new Validator($in);
        $d = [];
        $acts = [];
        $now = NowTime::mysql();
        if (array_key_exists('status', $in)) {
            $status = $v->enum('status', Domain::LEAD_STATUSES);
            if ($status !== $lead['status']) {
                $d['status'] = $status;
                $acts[] = ['status_changed', ['from' => $lead['status'], 'to' => $status]];
                if ($lead['status'] === 'new' && $lead['first_contact_at'] === null) {
                    $d['first_contact_at'] = $now;
                }
                $d['closed_at'] = $status === 'closed' ? $now : null;
            }
        }
        if (array_key_exists('deal_value', $in)) {
            $val = $v->money('deal_value', false);
            if ($val !== ($lead['deal_value'] === null ? null : (float) $lead['deal_value'])) {
                $d['deal_value'] = $val;
                $acts[] = ['deal_value_set', ['value' => $val]];
            }
        }
        $v->check();
        if ($d) {
            $d['updated_at'] = $now;
            $d['last_activity_at'] = $now;
            Db::tx(function () use ($id, $d, $acts, $a, $lead) {
                Db::update('leads', $d, ['id' => $id]);
                foreach ($acts as [$type, $meta]) {
                    self::activity($id, (int) $lead['client_id'], $a->userId, $type, $meta);
                }
            });
        }
        return self::detail($a, $id);
    }

    public static function addNote(Actor $a, int $id, array $in): array
    {
        $lead = self::findOwned($a, $id);
        $v = new Validator($in);
        $body = $v->str('body', true, 5000, true);
        $v->check();
        $now = NowTime::mysql();
        Db::tx(function () use ($id, $lead, $a, $body, $now) {
            Db::insert('lead_notes', ['lead_id' => $id, 'client_id' => $lead['client_id'], 'user_id' => $a->userId, 'body' => $body, 'created_at' => $now]);
            Db::update('leads', ['updated_at' => $now, 'last_activity_at' => $now], ['id' => $id]);
            self::activity($id, (int) $lead['client_id'], $a->userId, 'note_added', []);
        });
        return self::detail($a, $id);
    }

    /** Records that the user opened WhatsApp / a call / an e-mail for this lead. */
    public static function logContact(Actor $a, int $id, string $channel): void
    {
        if (!in_array($channel, ['whatsapp', 'call', 'email'], true)) {
            throw HttpException::invalid(['channel' => t('validation.invalid')]);
        }
        $lead = self::findOwned($a, $id);
        $now = NowTime::mysql();
        Db::update('leads', ['last_activity_at' => $now, 'updated_at' => $now], ['id' => $id]);
        self::activity($id, (int) $lead['client_id'], $a->userId, 'contact_' . $channel, []);
    }

    private static function activity(int $leadId, int $clientId, ?int $userId, string $type, array $meta): void
    {
        Db::insert('lead_activity', [
            'lead_id' => $leadId, 'client_id' => $clientId, 'user_id' => $userId, 'type' => $type,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, 'created_at' => NowTime::mysql(),
        ]);
    }

    /* -------------------------------------------------------------- export */

    /** CSV of the leads matching the current filters (all pages), scoped to the actor. */
    public static function exportCsv(Actor $a, array $q): string
    {
        $p = [];
        $where = self::filters($a, $q, $p);
        $header = [t('lead.name'), t('lead.phone'), t('lead.email'), t('lead.message'), t('lead.date'), t('lead.source'), t('lead.campaign'),
            t('lead.landing_page'), t('lead.status'), t('lead.deal_value'), t('lead.referrer'), t('lead.device')];
        if ($a->isAdmin()) {
            array_unshift($header, t('nav.clients'));
        }
        $rows = (function () use ($where, $p, $a) {
            $lastId = PHP_INT_MAX;
            do {
                $batch = Db::all(
                    'SELECT l.*, c.business_name AS client_name, lp.name AS landing_name ' . self::FROM . " WHERE {$where} AND l.id < ? ORDER BY l.id DESC LIMIT 1000",
                    array_merge($p, [$lastId])
                );
                foreach ($batch as $r) {
                    $row = [$r['name'], $r['phone'], $r['email'], $r['message'], $r['created_at'], t('source.' . $r['source']), $r['utm_campaign'],
                        $r['landing_name'] ?? '', t('status.' . $r['status']), $r['deal_value'] ?? '', $r['referrer'], $r['device']];
                    if ($a->isAdmin()) {
                        array_unshift($row, $r['client_name']);
                    }
                    yield $row;
                    $lastId = (int) $r['id'];
                }
            } while (count($batch) === 1000);
        })();
        return Csv::build($header, $rows);
    }

    /** Count of leads still waiting for a first response (status new for > 2h). */
    public static function waitingCount(Actor $a, ?int $clientId = null): int
    {
        $p = [NowTime::now()->modify('-' . Domain::WAIT_WARN_H . ' hours')->format('Y-m-d H:i:s')];
        $sql = "SELECT COUNT(*) FROM leads l JOIN clients c ON c.id = l.client_id AND c.deleted_at IS NULL WHERE l.status = 'new' AND l.created_at <= ?";
        $cid = $a->scopeClientId($clientId);
        if ($cid !== null) {
            $sql .= ' AND l.client_id = ?';
            $p[] = $cid;
        }
        return (int) Db::val($sql, $p);
    }
}
