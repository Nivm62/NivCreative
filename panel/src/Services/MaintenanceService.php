<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Db;
use Nivc\Core\NowTime;

/**
 * Periodic checks that generate notifications. Runs from bin/cron.php (recommended: every 10 min)
 * and, as a fallback on shared hosting, opportunistically (throttled) from dashboard requests.
 * Every notification has a dedupe key so repeated runs never spam.
 */
final class MaintenanceService
{
    public static function runThrottled(int $everySeconds = 600): void
    {
        $last = (int) Settings::get('maint_last', '0');
        if (time() - $last < $everySeconds) {
            return;
        }
        Settings::set('maint_last', (string) time());
        try {
            self::run();
        } catch (\Throwable $e) {
            \Nivc\Core\Logger::error('maintenance failed: ' . $e->getMessage());
        }
    }

    public static function run(): array
    {
        $today = NowTime::today();
        $created = ['expiring' => 0, 'waiting' => 0, 'sites' => 0, 'spike' => 0];

        // 1. Subscriptions expiring / expired.
        $rows = Db::all(
            "SELECT c.id, c.business_name, s.end_date, DATEDIFF(s.end_date, ?) AS left_days
             FROM clients c JOIN subscriptions s ON s.id = (SELECT s2.id FROM subscriptions s2 WHERE s2.client_id = c.id ORDER BY s2.end_date DESC, s2.id DESC LIMIT 1)
             WHERE c.deleted_at IS NULL AND c.status = 'active' AND DATEDIFF(s.end_date, ?) <= " . Domain::SOON_DAYS,
            [$today, $today]
        );
        foreach ($rows as $r) {
            $left = (int) $r['left_days'];
            $params = ['client' => $r['business_name'], 'days' => abs($left), 'date' => $r['end_date']];
            if ($left < 0) {
                NotificationService::notify('client', (int) $r['id'], 'subscription_expired', 'danger', $params, '/account', 'sub_expired:c' . $r['id'] . ':' . $r['end_date']);
                NotificationService::notify('admin', (int) $r['id'], 'subscription_expired_admin', 'danger', $params, '/admin/billing', 'sub_expired_a:c' . $r['id'] . ':' . $r['end_date']);
            } else {
                $stage = $left <= 7 ? '7' : '30';
                NotificationService::notify('client', (int) $r['id'], 'subscription_expiring', 'warning', $params, '/account', 'sub_exp:c' . $r['id'] . ':' . $r['end_date'] . ':' . $stage);
                NotificationService::notify('admin', (int) $r['id'], 'subscription_expiring_admin', 'warning', $params, '/admin/billing', 'sub_exp_a:c' . $r['id'] . ':' . $r['end_date'] . ':' . $stage);
            }
            $created['expiring']++;
        }

        // 2. Leads waiting too long: one rolling notification per client per day with the current count.
        $cut = NowTime::now()->modify('-' . Domain::WAIT_WARN_H . ' hours')->format('Y-m-d H:i:s');
        foreach (Db::all("SELECT l.client_id, COUNT(*) AS n FROM leads l JOIN clients c ON c.id = l.client_id AND c.deleted_at IS NULL WHERE l.status = 'new' AND l.created_at <= ? GROUP BY l.client_id", [$cut]) as $r) {
            $name = (string) Db::val('SELECT business_name FROM clients WHERE id = ?', [$r['client_id']]);
            NotificationService::notify('client', (int) $r['client_id'], 'lead_waiting', 'warning', ['count' => (int) $r['n']], '/leads?aging=waiting_2h', 'waiting:c' . $r['client_id'] . ':' . $today, true);
            NotificationService::notify('admin', (int) $r['client_id'], 'lead_waiting_admin', 'warning', ['count' => (int) $r['n'], 'client' => $name], '/admin/leads?aging=waiting_2h&client_id=' . $r['client_id'], 'waiting_a:c' . $r['client_id'] . ':' . $today, true);
            $created['waiting']++;
        }

        // 3. Connector websites that stopped reporting for 48h.
        $stale = date('Y-m-d H:i:s', time() - 48 * 3600);
        foreach (Db::all("SELECT w.id, w.name, w.client_id FROM websites w JOIN clients c ON c.id = w.client_id AND c.deleted_at IS NULL WHERE w.connector_version <> '' AND w.connection_status = 'connected' AND w.last_seen_at < ?", [$stale]) as $w) {
            Db::update('websites', ['connection_status' => 'disconnected', 'connection_message' => 'no_heartbeat'], ['id' => $w['id']]);
            NotificationService::notify('admin', (int) $w['client_id'], 'website_disconnected', 'danger', ['site' => $w['name']], '/admin/websites', 'site_down:' . $w['id'] . ':' . $today);
            $created['sites']++;
        }

        // 4. Significant increase in leads: today >= 2x the 7-day daily average (and at least 5).
        foreach (Db::all("SELECT l.client_id, COUNT(*) AS n FROM leads l WHERE l.created_at >= ? GROUP BY l.client_id HAVING n >= 5", [$today . ' 00:00:00']) as $r) {
            $prev = (int) Db::val('SELECT COUNT(*) FROM leads WHERE client_id = ? AND created_at >= ? AND created_at < ?', [$r['client_id'], date('Y-m-d 00:00:00', strtotime($today . ' -7 days')), $today . ' 00:00:00']);
            $avg = $prev / 7;
            if ($avg > 0 && (int) $r['n'] >= 2 * $avg) {
                $name = (string) Db::val('SELECT business_name FROM clients WHERE id = ?', [$r['client_id']]);
                NotificationService::notify('client', (int) $r['client_id'], 'leads_spike', 'success', ['count' => (int) $r['n']], '/leads', 'spike:c' . $r['client_id'] . ':' . $today);
                NotificationService::notify('admin', (int) $r['client_id'], 'leads_spike_admin', 'success', ['count' => (int) $r['n'], 'client' => $name], '/admin/leads?client_id=' . $r['client_id'], 'spike_a:c' . $r['client_id'] . ':' . $today);
                $created['spike']++;
            }
        }
        // Housekeeping
        Db::exec('DELETE FROM notifications WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 90 * 86400)]);
        Db::exec('DELETE FROM remember_tokens WHERE expires_at < ?', [NowTime::mysql()]);
        Db::exec('DELETE FROM password_resets WHERE expires_at < ?', [NowTime::mysql()]);
        return $created;
    }
}
