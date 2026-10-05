<?php
declare(strict_types=1);

namespace Nivc\Services;

/** SSRF protection for server-side requests to customer websites. */
final class UrlGuard
{
    public static function isSafe(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        $port = $p['port'] ?? null;
        if ($port !== null && !in_array((int) $port, [80, 443], true)) {
            return false;
        }
        $ips = [];
        if (filter_var($p['host'], FILTER_VALIDATE_IP)) {
            $ips[] = $p['host'];
        } else {
            $recs = @dns_get_record($p['host'], DNS_A | DNS_AAAA) ?: [];
            foreach ($recs as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? '';
            }
        }
        if (!$ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    /** GET a URL with a short timeout, no redirects to unsafe hosts. @return array{status:int,body:string,error:string} */
    public static function get(string $url, array $headers = []): array
    {
        if (!self::isSafe($url)) {
            return ['status' => 0, 'body' => '', 'error' => 'blocked'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'NivCreativePanel/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_MAXFILESIZE => 1048576,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $err];
    }
}
