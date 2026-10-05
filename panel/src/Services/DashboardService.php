<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Db;
use Nivc\Core\NowTime;

final class DashboardService
{
    private static function delta(float|int|null $cur, float|int|null $prev): ?float
    {
        if ($cur === null || $prev === null || (float) $prev == 0.0) {
            return null;
        }
        return round(((float) $cur - (float) $prev) * 100 / (float) $prev, 1);
    }

    /** Administrator overview. */
    public static function admin(Actor $a, array $q): array
    {
        $r = AnalyticsService::range($q);
        $group = AnalyticsService::group($q, $r['from'], $r['to']);
        $today = NowTime::today();
        $monthStart = date('Y-m-01', strtotime($today));
        $totals = AnalyticsService::totals($a, [], $r['from'], $r['to']);
        $prevR = AnalyticsService::previous($r['from'], $r['to']);
        $prev = AnalyticsService::totals($a, [], $prevR['from'], $prevR['to']);

        $activeClients = (int) Db::val(
            "SELECT COUNT(*) FROM clients c LEFT JOIN subscriptions s ON s.id = (SELECT s2.id FROM subscriptions s2 WHERE s2.client_id = c.id ORDER BY s2.end_date DESC, s2.id DESC LIMIT 1)
             WHERE c.deleted_at IS NULL AND c.status = 'active' AND (s.end_date IS NULL OR s.end_date >= ?)",
            [$today]
        );
        $leadsToday = (int) Db::val('SELECT COUNT(*) FROM leads l JOIN clients c ON c.id = l.client_id AND c.deleted_at IS NULL WHERE l.created_at >= ?', [$today . ' 00:00:00']);
        $leadsMonth = (int) Db::val('SELECT COUNT(*) FROM leads l JOIN clients c ON c.id = l.client_id AND c.deleted_at IS NULL WHERE l.created_at >= ?', [$monthStart . ' 00:00:00']);
        $revenueMonth = (float) Db::val("SELECT COALESCE(SUM(amount),0) FROM subscriptions s JOIN clients c ON c.id = s.client_id AND c.deleted_at IS NULL WHERE s.payment_status = 'paid' AND s.start_date >= ?", [$monthStart]);
        $revenuePrev = (float) Db::val(
            "SELECT COALESCE(SUM(amount),0) FROM subscriptions s JOIN clients c ON c.id = s.client_id AND c.deleted_at IS NULL WHERE s.payment_status = 'paid' AND s.start_date >= ? AND s.start_date < ?",
            [date('Y-m-01', strtotime($monthStart . ' -1 month')), $monthStart]
        );

        $kpis = [
            'active_clients'   => ['value' => $activeClients],
            'active_pages'     => ['value' => (int) Db::val("SELECT COUNT(*) FROM landing_pages lp JOIN clients c ON c.id = lp.client_id AND c.deleted_at IS NULL AND c.status = 'active' WHERE lp.status = 'active'")],
            'leads_today'      => ['value' => $leadsToday],
            'leads_month'      => ['value' => $leadsMonth],
            'total_leads'      => ['value' => (int) Db::val('SELECT COUNT(*) FROM leads l JOIN clients c ON c.id = l.client_id AND c.deleted_at IS NULL')],
            'total_views'      => ['value' => $totals['views'], 'delta' => self::delta($totals['views'], $prev['views'])],
            'avg_conversion'   => ['value' => $totals['conversion'], 'delta' => self::delta($totals['conversion'], $prev['conversion'])],
            'monthly_revenue'  => ['value' => $revenueMonth, 'delta' => self::delta($revenueMonth, $revenuePrev)],
            'expiring_soon'    => ['value' => BillingService::expiringCount()],
        ];

        // Revenue (NivCreative subscription payments) per month, last 12 months.
        $rev = [];
        $start = date('Y-m-01', strtotime($monthStart . ' -11 months'));
        $map = [];
        for ($i = 0; $i < 12; $i++) {
            $map[date('Y-m-01', strtotime($start . " +$i months"))] = 0.0;
        }
        foreach (Db::all("SELECT DATE_FORMAT(start_date,'%Y-%m-01') AS m, SUM(amount) AS amt FROM subscriptions s JOIN clients c ON c.id = s.client_id AND c.deleted_at IS NULL WHERE payment_status = 'paid' AND start_date >= ? GROUP BY m", [$start]) as $row) {
            if (isset($map[$row['m']])) {
                $map[$row['m']] = (float) $row['amt'];
            }
        }
        foreach ($map as $m => $amt) {
            $rev[] = ['date' => $m, 'revenue' => $amt];
        }

        $top = Db::all(
            "SELECT c.id, c.business_name, c.contact_name, COUNT(l.id) AS leads, SUM(l.status='closed') AS closed
             FROM clients c LEFT JOIN leads l ON l.client_id = c.id AND l.created_at BETWEEN ? AND ? WHERE c.deleted_at IS NULL GROUP BY c.id ORDER BY leads DESC, c.id LIMIT 6",
            [$r['from'] . ' 00:00:00', $r['to'] . ' 23:59:59']
        );

        return [
            'range' => $r + ['group' => $group], 'kpis' => $kpis, 'totals' => $totals,
            'series' => AnalyticsService::series($a, [], $r['from'], $r['to'], $group),
            'sources' => AnalyticsService::sources($a, [], $r['from'], $r['to']),
            'campaigns' => AnalyticsService::campaigns($a, [], $r['from'], $r['to'], 6),
            'revenue_series' => $rev,
            'top_clients' => array_map(static fn($t) => ['id' => (int) $t['id'], 'name' => $t['business_name'], 'contact' => $t['contact_name'], 'leads' => (int) $t['leads'], 'closed' => (int) $t['closed']], $top),
            'recent_leads' => LeadService::list($a, ['per_page' => 10, 'sort' => 'created', 'dir' => 'desc'])['items'],
            'waiting' => LeadService::waitingCount($a),
            'notifications' => NotificationService::list($a, 5, true),
        ];
    }

    /** Client dashboard (admins may pass client_id to view a client's dashboard). */
    public static function client(Actor $a, array $q): array
    {
        $cid = $a->scopeClientId($q['client_id'] ?? null);
        if ($cid === null) {
            return [];
        }
        $q['client_id'] = $cid;
        $r = AnalyticsService::range($q);
        $group = AnalyticsService::group($q, $r['from'], $r['to']);
        $totals = AnalyticsService::totals($a, $q, $r['from'], $r['to']);
        $prevR = AnalyticsService::previous($r['from'], $r['to']);
        $prev = AnalyticsService::totals($a, $q, $prevR['from'], $prevR['to']);
        $newLeads = (int) Db::val("SELECT COUNT(*) FROM leads WHERE client_id = ? AND status = 'new'", [$cid]);
        $client = Db::one('SELECT contact_name, business_name FROM clients WHERE id = ? AND deleted_at IS NULL', [$cid]);
        return [
            'client' => ['name' => $client['contact_name'] ?? '', 'business' => $client['business_name'] ?? ''],
            'range' => $r + ['group' => $group],
            'kpis' => [
                'leads'        => ['value' => $totals['leads'], 'delta' => self::delta($totals['leads'], $prev['leads'])],
                'new_leads'    => ['value' => $newLeads],
                'views'        => ['value' => $totals['views'], 'delta' => self::delta($totals['views'], $prev['views'])],
                'conversion'   => ['value' => $totals['conversion'], 'delta' => self::delta($totals['conversion'], $prev['conversion'])],
                'waiting'      => ['value' => LeadService::waitingCount($a, $cid)],
                'closed'       => ['value' => $totals['closed'], 'delta' => self::delta($totals['closed'], $prev['closed'])],
                'revenue'      => ['value' => $totals['revenue'], 'delta' => self::delta($totals['revenue'], $prev['revenue'])],
                'avg_deal'     => ['value' => $totals['avg_deal']],
            ],
            'series' => AnalyticsService::series($a, $q, $r['from'], $r['to'], $group),
            'sources' => AnalyticsService::sources($a, $q, $r['from'], $r['to']),
            'funnel' => AnalyticsService::funnel($a, $q, $r['from'], $r['to'], $totals['views']),
            'recent_leads' => LeadService::list($a, ['client_id' => $cid, 'per_page' => 6, 'sort' => 'created', 'dir' => 'desc'])['items'],
            'subscription' => BillingService::current($cid),
            'notifications' => NotificationService::list($a, 5, true),
        ];
    }
}
