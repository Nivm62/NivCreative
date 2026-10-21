<?php
declare(strict_types=1);

namespace Nivc\Services\Mail;

/** RFC 5322 / MIME parsing (read side) and composing (write side). Output is always UTF-8 text; HTML is reduced to text. */
final class Mime
{
    /* ----------------------------------------------------------------- charset / encoded words */

    public static function toUtf8(string $s, string $charset): string
    {
        $cs = strtolower(trim($charset, " \t\"'"));
        if ($cs === '' || $cs === 'utf-8' || $cs === 'utf8' || $cs === 'us-ascii' || $cs === 'ascii') {
            return self::cleanUtf8($s);
        }
        if (function_exists('iconv')) {
            $r = @iconv($cs, 'UTF-8//IGNORE', $s);
            if (is_string($r) && $r !== '') {
                return $r;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            try {
                $r = @mb_convert_encoding($s, 'UTF-8', $cs);
                if (is_string($r)) {
                    return self::cleanUtf8($r);
                }
            } catch (\Throwable) { // unsupported charset name
            }
        }
        return self::cleanUtf8($s);
    }

    private static function cleanUtf8(string $s): string
    {
        if (preg_match('//u', $s)) {
            return $s;
        }
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if (is_string($r)) {
                return $r;
            }
        }
        return (string) preg_replace('/[^\x09\x0a\x0d\x20-\x7e]/', '?', $s);
    }

    /** Decodes =?charset?B|Q?...?= words in a header value. */
    public static function decodeWords(string $v): string
    {
        $v = preg_replace('/\r?\n[ \t]+/', ' ', $v) ?? $v; // unfold
        $v = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $v) ?? $v; // adjacent encoded words join
        return self::cleanUtf8((string) preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', static function (array $m): string {
            $text = strtoupper($m[2]) === 'B' ? (string) base64_decode($m[3], false) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return self::toUtf8($text, preg_replace('/\*.*$/', '', $m[1]) ?? $m[1]);
        }, $v));
    }

    /** RFC 2047 encoded word for non-ASCII header text. */
    public static function encodeWord(string $s): string
    {
        $s = preg_replace('/[\r\n]+/', ' ', $s) ?? $s;
        if (preg_match('/^[\x20-\x7e]*$/', $s)) {
            return $s;
        }
        $parts = [];
        $chunk = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($chunk . $ch) > 36) {
                $parts[] = $chunk;
                $chunk = '';
            }
            $chunk .= $ch;
        }
        if ($chunk !== '') {
            $parts[] = $chunk;
        }
        return implode("\r\n ", array_map(static fn($p) => '=?UTF-8?B?' . base64_encode($p) . '?=', $parts));
    }

    /* ----------------------------------------------------------------- headers */

    /** @return array<string,string> lower-cased header name => decoded value (last wins, except Received) */
    public static function parseHeaders(string $block): array
    {
        $block = preg_replace('/\r?\n[ \t]+/', ' ', $block) ?? $block;
        $out = [];
        foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
            $p = strpos($line, ':');
            if ($p === false || $p === 0) {
                continue;
            }
            $out[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
        }
        return $out;
    }

    /** @return array{0:string,1:string} [header block, body] */
    private static function split(string $raw): array
    {
        if (preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE)) {
            return [substr($raw, 0, $m[0][1]), substr($raw, $m[0][1] + strlen($m[0][0]))];
        }
        return [$raw, ''];
    }

    /** Parameter of a structured header (charset, boundary, filename...), RFC 2231 continuations/encoding included. */
    public static function param(string $header, string $name): string
    {
        $name = strtolower($name);
        $pieces = [];
        if (preg_match_all('/;\s*([A-Za-z0-9_-]+)(\*(\d+))?(\*)?\s*=\s*("((?:[^"\\\\]|\\\\.)*)"|[^;]*)/', $header, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (strtolower($m[1]) !== $name) {
                    continue;
                }
                $val = isset($m[6]) && $m[6] !== '' || (isset($m[5]) && str_starts_with($m[5], '"')) ? stripslashes($m[6] ?? '') : trim($m[5]);
                $pieces[(int) ($m[3] === '' ? 0 : $m[3])] = [$val, ($m[4] ?? '') === '*'];
            }
        }
        if (!$pieces) {
            return '';
        }
        ksort($pieces);
        $out = '';
        $cs = 'utf-8';
        foreach ($pieces as $i => [$val, $enc]) {
            if ($enc) {
                if ($i === 0 && preg_match("/^([^']*)'[^']*'(.*)$/s", $val, $m)) {
                    $cs = $m[1] !== '' ? $m[1] : 'utf-8';
                    $val = $m[2];
                }
                $val = self::toUtf8(rawurldecode($val), $cs);
            }
            $out .= $val;
        }
        return self::decodeWords($out);
    }

    /* ----------------------------------------------------------------- body parsing */

    /**
     * @return array{headers:array<string,string>,text:string,html:string,attachments:array<int,array{part:string,name:string,type:string,size:int}>}
     */
    public static function parse(string $raw): array
    {
        [$hb, ] = self::split($raw);
        $res = ['headers' => self::parseHeaders($hb), 'text' => '', 'html' => '', 'attachments' => []];
        self::walk($raw, '', $res, 0);
        if ($res['text'] === '' && $res['html'] !== '') {
            $res['text'] = self::htmlToText($res['html']);
        }
        $res['html'] = ''; // never forwarded to the browser
        return $res;
    }

    private static function walk(string $part, string $path, array &$res, int $depth): void
    {
        [$hb, $body] = self::split($part);
        $h = self::parseHeaders($hb);
        $ct = $h['content-type'] ?? 'text/plain; charset=us-ascii';
        $type = strtolower(trim(explode(';', $ct)[0]));
        if (str_starts_with($type, 'multipart/') && $depth < 8) {
            $b = self::param($ct, 'boundary');
            if ($b === '') {
                return;
            }
            $i = 0;
            foreach (self::splitMultipart($body, $b) as $sub) {
                $i++;
                self::walk($sub, $path === '' ? (string) $i : $path . '.' . $i, $res, $depth + 1);
            }
            return;
        }
        if ($type === 'message/rfc822' && $depth < 8) {
            self::walk(self::decodeBody($body, $h['content-transfer-encoding'] ?? ''), $path === '' ? '1' : $path . '.1', $res, $depth + 1);
            return;
        }
        $disp = strtolower($h['content-disposition'] ?? '');
        $name = self::param($h['content-disposition'] ?? '', 'filename') ?: self::param($ct, 'name');
        $isAttachment = str_starts_with($disp, 'attachment') || $name !== '' || !in_array($type, ['text/plain', 'text/html'], true);
        $data = self::decodeBody($body, $h['content-transfer-encoding'] ?? '');
        if (!$isAttachment) {
            $txt = self::toUtf8($data, self::param($ct, 'charset'));
            if ($type === 'text/html') {
                $res['html'] .= $txt;
            } else {
                $res['text'] .= ($res['text'] !== '' ? "\n" : '') . $txt;
            }
            return;
        }
        if (str_starts_with($type, 'image/') && str_starts_with($h['content-id'] ?? '', '<') && $name === '') {
            $name = 'inline-' . ($path ?: '1');
        }
        $res['attachments'][] = ['part' => $path === '' ? '1' : $path, 'name' => $name !== '' ? $name : 'attachment-' . ($path ?: '1'), 'type' => $type, 'size' => strlen($data)];
    }

    /** @return string[] */
    private static function splitMultipart(string $body, string $boundary): array
    {
        $parts = preg_split('/\r?\n?--' . preg_quote($boundary, '/') . '(--)?[ \t]*\r?\n?/', $body) ?: [];
        array_shift($parts); // preamble
        $out = [];
        foreach ($parts as $p) {
            if (trim($p) !== '') {
                $out[] = ltrim($p, "\r\n");
            }
        }
        return $out;
    }

    private static function decodeBody(string $body, string $cte): string
    {
        return match (strtolower(trim($cte))) {
            'base64' => (string) base64_decode($body, false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /** Decoded bytes of one leaf part (by the path returned in attachments[]), or null. */
    public static function partData(string $raw, string $wanted): ?array
    {
        $found = null;
        self::findPart($raw, '', $wanted, $found, 0);
        return $found;
    }

    private static function findPart(string $part, string $path, string $wanted, ?array &$found, int $depth): void
    {
        if ($found !== null) {
            return;
        }
        [$hb, $body] = self::split($part);
        $h = self::parseHeaders($hb);
        $ct = $h['content-type'] ?? 'text/plain';
        $type = strtolower(trim(explode(';', $ct)[0]));
        if (str_starts_with($type, 'multipart/') && $depth < 8) {
            $i = 0;
            foreach (self::splitMultipart($body, self::param($ct, 'boundary')) as $sub) {
                $i++;
                self::findPart($sub, $path === '' ? (string) $i : $path . '.' . $i, $wanted, $found, $depth + 1);
            }
            return;
        }
        if ($type === 'message/rfc822' && $depth < 8) {
            self::findPart(self::decodeBody($body, $h['content-transfer-encoding'] ?? ''), $path === '' ? '1' : $path . '.1', $wanted, $found, $depth + 1);
            return;
        }
        if (($path === '' ? '1' : $path) === $wanted) {
            $name = self::param($h['content-disposition'] ?? '', 'filename') ?: self::param($ct, 'name');
            $found = ['name' => $name !== '' ? $name : 'attachment', 'type' => $type, 'data' => self::decodeBody($body, $h['content-transfer-encoding'] ?? '')];
        }
    }

    public static function htmlToText(string $html): string
    {
        $t = preg_replace('#<(script|style|head)\b.*?</\1>#is', '', $html) ?? $html;
        $t = preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6]|table|blockquote)>#i', "\n", $t) ?? $t;
        $t = preg_replace('#<li\b[^>]*>#i', '• ', $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t\xC2\xA0]+/", ' ', $t) ?? $t;
        $t = preg_replace("/ *\n */", "\n", $t) ?? $t;
        return trim(preg_replace("/\n{3,}/", "\n\n", $t) ?? $t);
    }

    /** "Name <a@b.c>, d@e.f" → [['name'=>..,'email'=>..], ...] */
    public static function addresses(string $header): array
    {
        $header = self::decodeWords($header);
        $out = [];
        // split on commas that are outside quotes and angle brackets
        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)(?![^<]*>)/', $header) ?: [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^(.*?)<([^>]+)>/', $p, $m)) {
                $out[] = ['name' => trim($m[1], " \t\"'"), 'email' => strtolower(trim($m[2]))];
            } elseif (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[] = ['name' => '', 'email' => strtolower($p)];
            }
        }
        return $out;
    }

    /* ----------------------------------------------------------------- composing */

    /**
     * Builds a plain-text UTF-8 message.
     * @param array{from:string,from_name?:string,to:string[],cc?:string[],subject:string,body:string,in_reply_to?:string,references?:string} $m
     * @return array{raw:string,message_id:string}
     */
    public static function build(array $m): array
    {
        $dom = substr(strrchr($m['from'], '@') ?: '@localhost', 1);
        $mid = '<' . bin2hex(random_bytes(12)) . '@' . $dom . '>';
        $addr = static function (string $email, string $name = ''): string {
            $email = str_replace(["\r", "\n", '<', '>'], '', $email);
            return $name !== '' ? self::encodeWord(str_replace(['"', "\r", "\n"], '', $name)) . ' <' . $email . '>' : $email;
        };
        $h = [
            'Date: ' . date('r'),
            'From: ' . $addr($m['from'], $m['from_name'] ?? ''),
            'To: ' . implode(', ', array_map(static fn($e) => $addr($e), $m['to'])),
        ];
        if (!empty($m['cc'])) {
            $h[] = 'Cc: ' . implode(', ', array_map(static fn($e) => $addr($e), $m['cc']));
        }
        $h[] = 'Subject: ' . self::encodeWord($m['subject']);
        $h[] = 'Message-ID: ' . $mid;
        if (!empty($m['in_reply_to'])) {
            $h[] = 'In-Reply-To: ' . str_replace(["\r", "\n"], '', $m['in_reply_to']);
            $h[] = 'References: ' . str_replace(["\r", "\n"], '', $m['references'] ?? $m['in_reply_to']);
        }
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'X-Mailer: NivCreative Panel';
        $h[] = 'Content-Type: text/plain; charset=UTF-8';
        $h[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $m['body'])), 76, "\r\n");
        return ['raw' => implode("\r\n", $h) . "\r\n\r\n" . $body, 'message_id' => $mid];
    }
}
