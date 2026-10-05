<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Actor;
use Nivc\Core\Db;
use Nivc\Core\NowTime;

/**
 * In-app notifications. Message text is NOT stored: only a type + params, translated at read time
 * (so each user sees their own language). External channels (email, WhatsApp, SMS, Telegram, push)
 * can be registered via registerChannel() without touching callers.
 */
final class NotificationService
{
    /** @var array<string,callable(array):void> */
    private static array $channels = [];

    public static function registerChannel(string $name, callable $handler): void
    {
        self::$channels[$name] = $handler;
    }

    public static function notify(string $audience, ?int $clientId, string $type, string $severity, array $params = [], string $link = '', ?string $dedupeKey = null, bool $refresh = false): void
    {
        $row = [
            'audience' => $audience, 'client_id' => $clientId, 'type' => $type, 'severity' => $severity,
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE), 'link' => $link, 'dedupe_key' => $dedupeKey,
            'read_at' => null, 'created_at' => NowTime::mysql(),
        ];
        if ($dedupeKey !== null) {
            $existing = Db::one('SELECT id FROM notifications WHERE dedupe_key = ?', [$dedupeKey]);
            if ($existing) {
                if ($refresh) {
                    Db::update('notifications', ['params' => $row['params']], ['id' => $existing['id']]);
                }
                return;
            }
        }
        try {
            Db::insert('notifications', $row);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') { // duplicate dedupe_key race: ignore
                throw $e;
            }
            return;
        }
        foreach (self::$channels as $handler) {
            try {
                $handler($row);
            } catch (\Throwable) {
                // channel failures never block the app
            }
        }
    }

    private static function scopeSql(Actor $a, array &$p): string
    {
        if ($a->isAdmin()) {
            return "audience = 'admin'";
        }
        $p[] = $a->clientId;
        return "audience = 'client' AND client_id = ?";
    }

    public static function list(Actor $a, int $limit = 30, bool $unreadOnly = false): array
    {
        $p = [];
        $where = self::scopeSql($a, $p);
        if ($unreadOnly) {
            $where .= ' AND read_at IS NULL';
        }
        $rows = Db::all("SELECT id, type, severity, params, link, read_at, created_at FROM notifications WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT " . (int) $limit, $p);
        $out = [];
        foreach ($rows as $r) {
            $params = json_decode((string) $r['params'], true) ?: [];
            $out[] = [
                'id' => (int) $r['id'], 'type' => $r['type'], 'severity' => $r['severity'],
                'title' => t('notif.' . $r['type'] . '.title', $params), 'body' => t('notif.' . $r['type'] . '.body', $params),
                'link' => $r['link'], 'read' => $r['read_at'] !== null, 'created_at' => $r['created_at'],
            ];
        }
        return $out;
    }

    public static function unreadCount(Actor $a): int
    {
        $p = [];
        $where = self::scopeSql($a, $p);
        return (int) Db::val("SELECT COUNT(*) FROM notifications WHERE {$where} AND read_at IS NULL", $p);
    }

    /** @param int[]|null $ids null = all */
    public static function markRead(Actor $a, ?array $ids): void
    {
        $p = [NowTime::mysql()];
        $where = self::scopeSql($a, $p);
        $sql = "UPDATE notifications SET read_at = ? WHERE {$where} AND read_at IS NULL";
        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) {
                return;
            }
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $p = array_merge($p, $ids);
        }
        Db::exec($sql, $p);
    }
}
