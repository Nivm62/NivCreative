<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Crypto;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\RateLimiter;
use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Services\ApiLog;
use Nivc\Services\Domain;
use Nivc\Services\LeadService;
use Nivc\Services\TrackingService;

/**
 * Public ingest API used by external WordPress sites / the future NivCreative Connector.
 *  POST /api/v1/leads  — server-to-server: X-Nivc-Site + Bearer secret token
 *  GET  /api/v1/ping   — connector heartbeat (same auth)
 *  POST /api/v1/track  — browser beacon: site key + Origin must match the registered domain
 */
final class IngestApi
{
    /** Authenticates the secret token. client_id comes from the website row, never from the request. */
    private static function authSite(Request $r, string $endpoint): array
    {
        $ip = $r->ip();
        if (RateLimiter::exceeded('ingest:fail:' . $ip, 20, 600)) {
            ApiLog::write(null, $endpoint, 429, 'too many failures', $ip);
            throw new HttpException(429, t('api.rate_limited'), 'rate_limited');
        }
        $siteKey = trim($r->header('X-Nivc-Site'));
        $auth = $r->header('Authorization');
        $token = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : '';
        $site = $siteKey !== '' ? Db::one(
            "SELECT w.*, c.status AS cstatus, c.deleted_at FROM websites w JOIN clients c ON c.id = w.client_id WHERE w.site_key = ?",
            [$siteKey]
        ) : null;
        $ok = $site && $token !== '' && hash_equals($site['token_hash'], Crypto::hashToken($token));
        if (!$ok) {
            RateLimiter::hit('ingest:fail:' . $ip, 20, 600);
            ApiLog::write($site ? (int) $site['id'] : null, $endpoint, 401, 'bad credentials', $ip);
            throw new HttpException(401, 'invalid credentials', 'unauthorized');
        }
        if ($site['status'] !== 'active' || $site['cstatus'] !== 'active' || $site['deleted_at'] !== null) {
            ApiLog::write((int) $site['id'], $endpoint, 403, 'disabled', $ip);
            throw new HttpException(403, 'site or account disabled', 'disabled');
        }
        return $site;
    }

    public static function leads(Request $r, array $p): Response
    {
        $site = self::authSite($r, 'leads');
        $ip = $r->ip();
        if (!RateLimiter::hit('leads:ip:' . $ip, 60, 60) || !RateLimiter::hit('leads:site:' . $site['id'], 300, 60)) {
            ApiLog::write((int) $site['id'], 'leads', 429, 'rate limited', $ip);
            throw new HttpException(429, t('api.rate_limited'), 'rate_limited');
        }
        if (!$r->isJson()) {
            throw new HttpException(415, 'Content-Type must be application/json', 'unsupported_media_type');
        }
        if (strlen($r->rawBody()) > 65536) {
            throw new HttpException(413, 'Payload too large', 'payload_too_large');
        }
        try {
            $res = LeadService::createFromWebsite($site, $r->json(), $ip, $r->userAgent());
        } catch (HttpException $e) {
            ApiLog::write((int) $site['id'], 'leads', $e->status, $e->getMessage(), $ip);
            throw $e;
        }
        Db::update('websites', ['last_seen_at' => NowTime::mysql(), 'connection_status' => 'connected', 'connection_message' => ''], ['id' => $site['id']]);
        ApiLog::write((int) $site['id'], 'leads', $res['duplicate'] ? 200 : 201, $res['duplicate'] ? 'duplicate' : 'created', $ip);
        return Response::json(['ok' => true, 'id' => $res['id'], 'duplicate' => $res['duplicate']], $res['duplicate'] ? 200 : 201);
    }

    public static function ping(Request $r, array $p): Response
    {
        $site = self::authSite($r, 'ping');
        $d = [
            'last_seen_at' => NowTime::mysql(), 'connection_status' => 'connected', 'connection_message' => '',
            'connector_version' => mb_substr(preg_replace('/[^0-9A-Za-z.\-]/', '', (string) ($r->query['connector'] ?? '')) ?? '', 0, 20),
            'wp_version' => mb_substr(preg_replace('/[^0-9A-Za-z.\-]/', '', (string) ($r->query['wp'] ?? '')) ?? '', 0, 20),
        ];
        Db::update('websites', $d, ['id' => $site['id']]);
        ApiLog::write((int) $site['id'], 'ping', 200, 'ok', $r->ip());
        return Response::json(['ok' => true, 'site' => $site['name'], 'time' => NowTime::mysql()]);
    }

    /** Browser tracker. Authenticates by site key + matching Origin (it can't hold a secret). */
    public static function track(Request $r, array $p): Response
    {
        $origin = $r->header('Origin');
        $cors = [];
        $siteKey = trim((string) ($r->json()['site'] ?? ($r->query['site'] ?? '')));
        $site = $siteKey !== '' ? Db::one("SELECT w.*, c.status AS cstatus, c.deleted_at FROM websites w JOIN clients c ON c.id = w.client_id WHERE w.site_key = ?", [$siteKey]) : null;
        if (!$site || $site['status'] !== 'active' || $site['cstatus'] !== 'active' || $site['deleted_at'] !== null) {
            return new Response(204);
        }
        if ($origin !== '') {
            if (Domain::host($origin) !== Domain::host($site['url'])) {
                ApiLog::write((int) $site['id'], 'track', 403, 'origin mismatch', $r->ip());
                return new Response(204);
            }
            $cors = ['Access-Control-Allow-Origin' => $origin, 'Vary' => 'Origin'];
        }
        if (!RateLimiter::hit('track:ip:' . $r->ip(), 240, 60) || !RateLimiter::hit('track:site:' . $site['id'], 3000, 60)) {
            return new Response(204, '', $cors);
        }
        if (strlen($r->rawBody()) > 4096) {
            return new Response(204, '', $cors);
        }
        TrackingService::record($site, $r->json(), $r->userAgent(), $r->ip());
        return new Response(204, '', $cors + ['Cache-Control' => 'no-store']);
    }

    public static function trackPreflight(Request $r, array $p): Response
    {
        return new Response(204, '', [
            'Access-Control-Allow-Origin' => $r->header('Origin') ?: '*', 'Vary' => 'Origin',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type', 'Access-Control-Max-Age' => '86400',
        ]);
    }
}
