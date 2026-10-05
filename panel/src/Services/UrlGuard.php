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
        if (!function_exists('curl_init')) {
            return self::getWithStreams($url, $headers);
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

    /** Fallback when the curl extension is missing. @return array{status:int,body:string,error:string} */
    private static function getWithStreams(string $url, array $headers): array
    {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 8, 'follow_location' => 0, 'ignore_errors' => true,
            'header' => implode("\r\n", array_merge(['User-Agent: NivCreativePanel/1.0'], $headers)),
        ]]);
        $body = @file_get_contents($url, false, $ctx, 0, 1048576);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $status === 0 ? 'unreachable' : ''];
    }
}
