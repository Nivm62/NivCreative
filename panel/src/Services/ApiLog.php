<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Db;
use Nivc\Core\NowTime;

/** Audit trail for public ingest endpoints (kept 30 days). */
final class ApiLog
{
    public static function write(?int $websiteId, string $endpoint, int $status, string $message, string $ip): void
    {
        try {
            Db::insert('api_logs', [
                'website_id' => $websiteId, 'endpoint' => $endpoint, 'status' => $status,
                'message' => mb_substr($message, 0, 250), 'ip' => mb_substr($ip, 0, 45), 'created_at' => NowTime::mysql(),
            ]);
            if (random_int(1, 300) === 1) {
                Db::exec('DELETE FROM api_logs WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
            }
        } catch (\Throwable) {
            // Logging must never break the request.
        }
    }
}
