<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\Validator;

/**
 * Subscription records + payment status. No payment processing happens here;
 * a gateway integration can later create/update rows through addSubscription().
 */
final class BillingService
{
    public static function addSubscription(int $clientId, array $d): int
    {
        return Db::insert('subscriptions', [
            'client_id' => $clientId, 'plan' => $d['plan'], 'amount' => $d['amount'], 'currency' => 'ILS',
            'start_date' => $d['start_date'], 'end_date' => $d['end_date'], 'payment_status' => $d['payment_status'],
            'note' => $d['note'] ?? '', 'created_at' => NowTime::mysql(),
        ]);
    }

    /** "Extend subscription": appends a new period to the history. */
    public static function extend(int $clientId, array $in): array
    {
        $client = Db::one('SELECT id FROM clients WHERE id = ? AND deleted_at IS NULL', [$clientId]);
        if (!$client) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $v = new Validator($in);
        $start   = $v->date('start_date');
        $months  = $v->int('months', 1, 60, true) ?? 12;
        $amount  = $v->money('amount', true);
        $plan    = $v->str('plan', false, 60);
        $status  = $v->enum('payment_status', Domain::PAYMENT, 'paid');
        $note    = $v->str('note', false, 255);
        $v->check();
        $end = (new \DateTimeImmutable($start))->modify('+' . $months . ' months')->format('Y-m-d');
        // Keep calendar semantics for 12-month periods (29 Feb → 28 Feb).
        if ($months % 12 === 0) {
            $end = NowTime::addYears($start, intdiv($months, 12));
        }
        if ($plan === '') {
            $plan = (string) Db::val('SELECT plan FROM clients WHERE id = ?', [$clientId]);
        }
        $id = self::addSubscription($clientId, ['plan' => $plan, 'amount' => $amount, 'start_date' => $start, 'end_date' => $end, 'payment_status' => $status, 'note' => $note]);
        Db::update('clients', ['plan' => $plan, 'updated_at' => NowTime::mysql()], ['id' => $clientId]);
        return ['id' => $id, 'end_date' => $end];
    }

    public static function updateSubscription(int $id, array $in): void
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE id = ?', [$id]);
        if (!$row) {
            throw HttpException::notFound(t('error.not_found'));
        }
        $v = new Validator($in);
        $d = [];
        if (array_key_exists('payment_status', $in)) {
            $d['payment_status'] = $v->enum('payment_status', Domain::PAYMENT);
        }
        if (array_key_exists('amount', $in)) {
            $d['amount'] = $v->money('amount', true);
        }
        if (array_key_exists('start_date', $in)) {
            $d['start_date'] = $v->date('start_date');
        }
        if (array_key_exists('end_date', $in)) {
            $d['end_date'] = $v->date('end_date');
        }
        if (array_key_exists('plan', $in)) {
            $d['plan'] = $v->str('plan', false, 60);
        }
        if (array_key_exists('note', $in)) {
            $d['note'] = $v->str('note', false, 255);
        }
        $v->check();
        if (isset($d['start_date'], $d['end_date']) && $d['end_date'] <= $d['start_date']) {
            throw HttpException::invalid(['end_date' => t('validation.end_after_start')]);
        }
        if ($d) {
            Db::update('subscriptions', $d, ['id' => $id]);
        }
    }

    /** Billing screen data: subscription history with days-left and a summary. */
    public static function overview(array $q): array
    {
        $today = NowTime::today();
        $where = ['c.deleted_at IS NULL'];
        $p = [$today];
        if (!empty($q['client_id']) && ctype_digit((string) $q['client_id'])) {
            $where[] = 's.client_id = ?';
            $p[] = (int) $q['client_id'];
        }
        if (in_array($q['payment_status'] ?? '', Domain::PAYMENT, true)) {
            $where[] = 's.payment_status = ?';
            $p[] = $q['payment_status'];
        }
        $pg = Paginator::params($q);
        $base = 'FROM subscriptions s JOIN clients c ON c.id = s.client_id WHERE ' . implode(' AND ', array_slice($where, 0));
        $total = (int) Db::val('SELECT COUNT(*) ' . $base, array_slice($p, 1));
        $rows  = Db::all(
            'SELECT s.*, c.business_name, c.contact_name, DATEDIFF(s.end_date, ?) AS days_left,
                (s.id = (SELECT s2.id FROM subscriptions s2 WHERE s2.client_id = s.client_id ORDER BY s2.end_date DESC, s2.id DESC LIMIT 1)) AS is_current '
            . $base . ' ORDER BY s.created_at DESC, s.id DESC LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'],
            $p
        );
        $monthStart = date('Y-m-01', strtotime($today));
        $summary = [
            'revenue_month' => (float) Db::val("SELECT COALESCE(SUM(amount),0) FROM subscriptions WHERE payment_status IN ('paid') AND start_date >= ? AND start_date <= ?", [$monthStart, $today]),
            'pending'       => (int) Db::val("SELECT COUNT(*) FROM subscriptions s JOIN clients c ON c.id=s.client_id AND c.deleted_at IS NULL WHERE s.payment_status IN ('pending','overdue')"),
            'expiring'      => self::expiringCount(),
            'total_paid'    => (float) Db::val("SELECT COALESCE(SUM(amount),0) FROM subscriptions s JOIN clients c ON c.id=s.client_id AND c.deleted_at IS NULL WHERE s.payment_status = 'paid'"),
        ];
        $items = array_map(static function (array $r) {
            return [
                'id' => (int) $r['id'], 'client_id' => (int) $r['client_id'], 'client_name' => $r['business_name'], 'contact_name' => $r['contact_name'],
                'plan' => $r['plan'], 'amount' => (float) $r['amount'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
                'payment_status' => $r['payment_status'], 'note' => $r['note'], 'days_left' => (int) $r['days_left'], 'is_current' => (bool) $r['is_current'],
            ];
        }, $rows);
        return ['items' => $items, 'meta' => Paginator::meta($total, $pg['page'], $pg['per']), 'summary' => $summary];
    }

    public static function expiringCount(): int
    {
        return (int) Db::val(
            'SELECT COUNT(*) FROM clients c JOIN subscriptions s ON s.id = (SELECT s2.id FROM subscriptions s2 WHERE s2.client_id = c.id ORDER BY s2.end_date DESC, s2.id DESC LIMIT 1)
             WHERE c.deleted_at IS NULL AND c.status = \'active\' AND DATEDIFF(s.end_date, ?) BETWEEN 0 AND ' . Domain::SOON_DAYS,
            [NowTime::today()]
        );
    }

    /** The current subscription of a client (for the account screen). */
    public static function current(int $clientId): ?array
    {
        $r = Db::one('SELECT * FROM subscriptions WHERE client_id = ? ORDER BY end_date DESC, id DESC LIMIT 1', [$clientId]);
        if (!$r) {
            return null;
        }
        $left = NowTime::daysBetween(NowTime::today(), $r['end_date']);
        return [
            'plan' => $r['plan'], 'amount' => (float) $r['amount'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
            'payment_status' => $r['payment_status'], 'days_left' => $left, 'state' => self::state($left),
        ];
    }

    public static function state(?int $daysLeft): string
    {
        if ($daysLeft === null) {
            return 'none';
        }
        return $daysLeft < 0 ? 'expired' : ($daysLeft <= Domain::SOON_DAYS ? 'expiring' : 'active');
    }
}
