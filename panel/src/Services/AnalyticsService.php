<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Db;
use Nivc\Core\NowTime;

/**
 * Analytics definitions (documented in docs/ARCHITECTURE.md):
 *  - views      = visits to a landing page (a refresh inside 30 min is not a new visit)
 *  - visitors   = distinct anonymous visitor hashes
 *  - leads      = leads created in the range
 *  - conversion = leads / views * 100
 *  - closed/revenue = leads whose closed_at is in the range / sum of their deal_value
 *  - funnel     = leads created in range grouped by their CURRENT status stage
 */
final class AnalyticsService
{
    /** @return array{from:string,to:string} */
    public static function range(array $q, string $defaultPreset = 'month'): array
    {
        $today = NowTime::today();
        $from = $q['from'] ?? '';
        $to   = $q['to'] ?? '';
        if (NIVC_valid_date($from) && NIVC_valid_date($to) && $from <= $to) {
            // clamp to 2 years to protect the database
            if (NowTime::daysBetween($from, $to) > 731) {
                $from = (new \DateTimeImmutable($to))->modify('-731 days')->format('Y-m-d');
            }
            return ['from' => $from, 'to' => $to];
        }
        $preset = (string) ($q['preset'] ?? $defaultPreset);
        $t = new \DateTimeImmutable($today);
        return match ($preset) {
            'today'      => ['from' => $today, 'to' => $today],
            '7d'         => ['from' => $t->modify('-6 days')->format('Y-m-d'), 'to' => $today],
            '30d'        => ['from' => $t->modify('-29 days')->format('Y-m-d'), 'to' => $today],
            'last_month' => ['from' => $t->modify('first day of last month')->format('Y-m-d'), 'to' => $t->modify('last day of last month')->format('Y-m-d')],
            'year'       => ['from' => $t->format('Y-01-01'), 'to' => $today],
            default      => ['from' => $t->format('Y-m-01'), 'to' => $today],
        };
    }

    /** Previous period of equal length (for delta chips). */
    public static function previous(string $from, string $to): array
    {
        $days = NowTime::daysBetween($from, $to) + 1;
        $pf = (new \DateTimeImmutable($from))->modify('-' . $days . ' days')->format('Y-m-d');
        $pt = (new \DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
        return ['from' => $pf, 'to' => $pt];
    }

    /** Builds a scoped WHERE for tables aliased with client_id/website_id/landing_page_id. */
    private static function scope(Actor $a, array $q, string $alias, string $lpCol, array &$p): string
    {
        $w = [];
        $cid = $a->scopeClientId($q['client_id'] ?? null);
        if ($cid !== null) {
            $w[] = "{$alias}.client_id = ?";
            $p[] = $cid;
        }
        if (!empty($q['website_id']) && ctype_digit((string) $q['website_id'])) {
            $w[] = "{$alias}.website_id = ?";
            $p[] = (int) $q['website_id'];
        }
        if (!empty($q['landing_page_id']) && ctype_digit((string) $q['landing_page_id'])) {
            $w[] = "{$alias}.{$lpCol} = ?";
            $p[] = (int) $q['landing_page_id'];
        }
        return $w ? ' AND ' . implode(' AND ', $w) : '';
    }

    /** Totals for one period. */
    public static function totals(Actor $a, array $q, string $from, string $to): array
    {
        $p = [$from, $to];
        $sc = self::scope($a, $q, 'pv', 'landing_page_id', $p);
        $pv = Db::one("SELECT COALESCE(SUM(views),0) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM page_views pv WHERE pv.day BETWEEN ? AND ?{$sc}", $p);
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $sl = self::scope($a, $q, 'l', 'landing_page_id', $p);
        $leads = (int) Db::val("SELECT COUNT(*) FROM leads l WHERE l.created_at BETWEEN ? AND ?{$sl}", $p);
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $sl = self::scope($a, $q, 'l', 'landing_page_id', $p);
        $cl = Db::one("SELECT COUNT(*) AS closed, COALESCE(SUM(deal_value),0) AS revenue FROM leads l WHERE l.status = 'closed' AND l.closed_at BETWEEN ? AND ?{$sl}", $p);
        $views = (int) $pv['views'];
        return [
            'views' => $views, 'visitors' => (int) $pv['visitors'], 'leads' => $leads,
            'conversion' => $views > 0 ? round($leads * 100 / $views, 1) : null,
            'closed' => (int) $cl['closed'], 'revenue' => (float) $cl['revenue'],
            'avg_deal' => (int) $cl['closed'] > 0 ? round((float) $cl['revenue'] / (int) $cl['closed'], 2) : null,
        ];
    }

    private static function bucketExpr(string $col, string $group): string
    {
        return match ($group) {
            'month' => "DATE_FORMAT({$col}, '%Y-%m-01')",
            'week'  => "DATE_SUB(DATE({$col}), INTERVAL WEEKDAY({$col}) DAY)",
            default => "DATE({$col})",
        };
    }

    /** @return string[] bucket keys covering the range */
    private static function buckets(string $from, string $to, string $group): array
    {
        $out = [];
        $d = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        if ($group === 'month') {
            $d = $d->modify('first day of this month');
            while ($d <= $end) {
                $out[] = $d->format('Y-m-01');
                $d = $d->modify('+1 month');
            }
        } elseif ($group === 'week') {
            $d = $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
            while ($d <= $end) {
                $out[] = $d->format('Y-m-d');
                $d = $d->modify('+7 days');
            }
        } else {
            while ($d <= $end) {
                $out[] = $d->format('Y-m-d');
                $d = $d->modify('+1 day');
            }
        }
        return $out;
    }

    public static function group(array $q, string $from, string $to): string
    {
        $g = (string) ($q['group'] ?? '');
        if (in_array($g, ['day', 'week', 'month'], true)) {
            return $g;
        }
        $days = NowTime::daysBetween($from, $to) + 1;
        return $days > 180 ? 'month' : ($days > 62 ? 'week' : 'day');
    }

    public static function series(Actor $a, array $q, string $from, string $to, string $group): array
    {
        $keys = self::buckets($from, $to, $group);
        $map = array_fill_keys($keys, ['views' => 0, 'visitors' => 0, 'leads' => 0, 'closed' => 0, 'revenue' => 0.0]);

        $p = [$from, $to];
        $sc = self::scope($a, $q, 'pv', 'landing_page_id', $p);
        $b = self::bucketExpr('pv.day', $group);
        foreach (Db::all("SELECT {$b} AS b, SUM(views) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM page_views pv WHERE pv.day BETWEEN ? AND ?{$sc} GROUP BY b", $p) as $r) {
            if (isset($map[$r['b']])) {
                $map[$r['b']]['views'] = (int) $r['views'];
                $map[$r['b']]['visitors'] = (int) $r['visitors'];
            }
        }
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $sl = self::scope($a, $q, 'l', 'landing_page_id', $p);
        $b = self::bucketExpr('l.created_at', $group);
        foreach (Db::all("SELECT {$b} AS b, COUNT(*) AS leads FROM leads l WHERE l.created_at BETWEEN ? AND ?{$sl} GROUP BY b", $p) as $r) {
            if (isset($map[$r['b']])) {
                $map[$r['b']]['leads'] = (int) $r['leads'];
            }
        }
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $sl = self::scope($a, $q, 'l', 'landing_page_id', $p);
        $b = self::bucketExpr('l.closed_at', $group);
        foreach (Db::all("SELECT {$b} AS b, COUNT(*) AS closed, COALESCE(SUM(deal_value),0) AS revenue FROM leads l WHERE l.status='closed' AND l.closed_at BETWEEN ? AND ?{$sl} GROUP BY b", $p) as $r) {
            if (isset($map[$r['b']])) {
                $map[$r['b']]['closed'] = (int) $r['closed'];
                $map[$r['b']]['revenue'] = (float) $r['revenue'];
            }
        }
        $out = [];
        foreach ($map as $k => $v) {
            $v['conversion'] = $v['views'] > 0 ? round($v['leads'] * 100 / $v['views'], 1) : null;
            $out[] = ['date' => $k] + $v;
        }
        return $out;
    }

    public static function sources(Actor $a, array $q, string $from, string $to): array
    {
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $sl = self::scope($a, $q, 'l', 'landing_page_id', $p);
        $leads = [];
        foreach (Db::all("SELECT source, COUNT(*) AS n FROM leads l WHERE l.created_at BETWEEN ? AND ?{$sl} GROUP BY source", $p) as $r) {
            $leads[$r['source']] = (int) $r['n'];
        }
        $p = [$from, $to];
        $sc = self::scope($a, $q, 'pv', 'landing_page_id', $p);
        $views = [];
        foreach (Db::all("SELECT source, SUM(views) AS n FROM page_views pv WHERE pv.day BETWEEN ? AND ?{$sc} GROUP BY source", $p) as $r) {
            $views[$r['source']] = (int) $r['n'];
        }
        $out = [];
        foreach (Domain::SOURCES as $s) {
            $out[] = ['source' => $s, 'leads' => $leads[$s] ?? 0, 'views' => $views[$s] ?? 0];
        }
        return $out;
    }

    public static function campaigns(Actor $a, array $q, string $from, string $to, int $limit = 10): array
    {
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $sl = self::scope($a, $q, 'l', 'landing_page_id', $p);
        $rows = Db::all(
            "SELECT utm_campaign AS campaign, COUNT(*) AS leads, SUM(status='closed') AS closed, COALESCE(SUM(CASE WHEN status='closed' THEN deal_value END),0) AS revenue
             FROM leads l WHERE l.created_at BETWEEN ? AND ? AND utm_campaign <> ''{$sl} GROUP BY utm_campaign ORDER BY leads DESC LIMIT " . (int) $limit,
            $p
        );
        $views = [];
        $p = [$from, $to];
        $sc = self::scope($a, $q, 'pv', 'landing_page_id', $p);
        foreach (Db::all("SELECT utm_campaign AS campaign, SUM(views) AS n FROM page_views pv WHERE pv.day BETWEEN ? AND ? AND utm_campaign <> ''{$sc} GROUP BY utm_campaign", $p) as $r) {
            $views[$r['campaign']] = (int) $r['n'];
        }
        return array_map(static function ($r) use ($views) {
            $v = $views[$r['campaign']] ?? 0;
            return [
                'campaign' => $r['campaign'], 'leads' => (int) $r['leads'], 'closed' => (int) $r['closed'], 'revenue' => (float) $r['revenue'],
                'views' => $v, 'conversion' => $v > 0 ? round((int) $r['leads'] * 100 / $v, 1) : null,
            ];
        }, $rows);
    }

    public static function funnel(Actor $a, array $q, string $from, string $to, int $views): array
    {
        $p = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $sl = self::scope($a, $q, 'l', 'landing_page_id', $p);
        $r = Db::one(
            "SELECT COUNT(*) AS leads,
                COALESCE(SUM(status IN ('contacted','in_progress','meeting','proposal','closed')),0) AS contacted,
                COALESCE(SUM(status IN ('meeting','proposal','closed')),0) AS meetings,
                COALESCE(SUM(status = 'closed'),0) AS closed
             FROM leads l WHERE l.created_at BETWEEN ? AND ?{$sl}",
            $p
        );
        return [
            ['key' => 'views', 'value' => $views], ['key' => 'leads', 'value' => (int) $r['leads']],
            ['key' => 'contacted', 'value' => (int) $r['contacted']], ['key' => 'meetings', 'value' => (int) $r['meetings']],
            ['key' => 'closed', 'value' => (int) $r['closed']],
        ];
    }

    /** Full analytics payload for the Analytics screen. */
    public static function overview(Actor $a, array $q): array
    {
        $r = self::range($q);
        $group = self::group($q, $r['from'], $r['to']);
        $totals = self::totals($a, $q, $r['from'], $r['to']);
        $prev = self::previous($r['from'], $r['to']);
        return [
            'range' => $r + ['group' => $group], 'totals' => $totals, 'previous' => self::totals($a, $q, $prev['from'], $prev['to']),
            'series' => self::series($a, $q, $r['from'], $r['to'], $group), 'sources' => self::sources($a, $q, $r['from'], $r['to']),
            'campaigns' => self::campaigns($a, $q, $r['from'], $r['to']), 'funnel' => self::funnel($a, $q, $r['from'], $r['to'], $totals['views']),
        ];
    }
}
