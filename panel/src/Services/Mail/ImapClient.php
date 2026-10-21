<?php
declare(strict_types=1);

namespace Nivc\Services\Mail;

/**
 * Minimal IMAP4rev1 client over a plain socket (no php-imap extension needed).
 * Only what the panel's mailbox needs: LOGIN, LIST, SELECT, UID SEARCH/FETCH/STORE/COPY, EXPUNGE, APPEND.
 */
final class ImapClient
{
    /** @var resource|null */
    private $fp = null;
    private int $tag = 0;
    /** @var string[] literal payloads of the response being parsed */
    private array $lits = [];

    public function __construct(private string $host, private int $port, private string $secure = 'ssl', private int $timeout = 15)
    {
    }

    /* --------------------------------------------------------------- connection */

    public function connect(): void
    {
        $scheme = $this->secure === 'ssl' ? 'ssl' : 'tcp';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->host, 'SNI_enabled' => true]]);
        $errno = 0;
        $err = '';
        $fp = @stream_socket_client($scheme . '://' . $this->host . ':' . $this->port, $errno, $err, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new MailException('connect_failed', $err !== '' ? $err : 'connection failed');
        }
        stream_set_timeout($fp, $this->timeout);
        $this->fp = $fp;
        $greeting = $this->readLine();
        if (!preg_match('/^\* (OK|PREAUTH)/i', $greeting)) {
            throw new MailException('bad_greeting', trim($greeting));
        }
        if ($this->secure === 'tls') {
            $this->cmd('STARTTLS');
            if (!@stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailException('tls_failed', 'STARTTLS failed');
            }
        }
    }

    public function login(string $user, string $pass): void
    {
        if (preg_match('/[\r\n\0]/', $user . $pass)) {
            throw new MailException('auth_failed', 'invalid characters');
        }
        try {
            $this->cmd('LOGIN ' . self::quote($user) . ' ' . self::quote($pass));
        } catch (MailException $e) {
            throw new MailException('auth_failed', $e->getMessage());
        }
    }

    public function logout(): void
    {
        if ($this->fp) {
            try {
                $this->cmd('LOGOUT');
            } catch (\Throwable) {
            }
            @fclose($this->fp);
            $this->fp = null;
        }
    }

    /* --------------------------------------------------------------- commands */

    /** @return array<int, array{name:string,attrs:string[],delim:string}> */
    public function folders(): array
    {
        $out = [];
        foreach ($this->cmd('LIST "" "*"')['untagged'] as $u) {
            $t = $this->tokens($u);
            // * LIST (\HasNoChildren) "/" "INBOX.Sent"
            if (count($t) >= 5 && strtoupper((string) $t[1]) === 'LIST' && is_array($t[2])) {
                $name = (string) $t[4];
                if (!in_array('\\noselect', array_map('strtolower', $t[2]), true)) {
                    $out[] = ['name' => $name, 'attrs' => array_map('strval', $t[2]), 'delim' => (string) $t[3]];
                }
            }
        }
        return $out;
    }

    /** @return array{exists:int,uidvalidity:int} */
    public function select(string $folder): array
    {
        $r = $this->cmd('SELECT ' . self::quote(self::encodeFolder($folder)));
        $exists = 0;
        $uidv = 0;
        foreach ($r['untagged'] as $u) {
            if (preg_match('/^\* (\d+) EXISTS/i', $u, $m)) {
                $exists = (int) $m[1];
            }
            if (preg_match('/UIDVALIDITY (\d+)/i', $u, $m)) {
                $uidv = (int) $m[1];
            }
        }
        return ['exists' => $exists, 'uidvalidity' => $uidv];
    }

    /** @return int[] ascending UIDs matching the criteria ("ALL", "UNSEEN" or a text query) */
    public function search(string $criteria = 'ALL', ?string $text = null): array
    {
        if ($text !== null && $text !== '') {
            $q = self::quote(self::oneLine($text));
            $cmd = 'UID SEARCH CHARSET UTF-8 OR OR FROM ' . $q . ' SUBJECT ' . $q . ' TO ' . $q . ($criteria === 'UNSEEN' ? ' UNSEEN' : '');
        } else {
            $cmd = 'UID SEARCH ' . $criteria;
        }
        $uids = [];
        foreach ($this->cmd($cmd)['untagged'] as $u) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', trim($u), $m) && trim($m[1]) !== '') {
                foreach (preg_split('/\s+/', trim($m[1])) as $n) {
                    if (ctype_digit($n)) {
                        $uids[] = (int) $n;
                    }
                }
            }
        }
        sort($uids);
        return $uids;
    }

    /**
     * Headers + flags for a set of UIDs.
     * @param int[] $uids
     * @return array<int, array{uid:int,flags:string[],size:int,headers:string}>
     */
    public function fetchHeaders(array $uids): array
    {
        if (!$uids) {
            return [];
        }
        $set = implode(',', array_map('intval', $uids));
        $r = $this->cmd('UID FETCH ' . $set . ' (UID FLAGS RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE MESSAGE-ID)])');
        $out = [];
        foreach ($r['untagged'] as $u) {
            $m = $this->fetchItems($u);
            if ($m && isset($m['UID'])) {
                $out[(int) $m['UID']] = [
                    'uid' => (int) $m['UID'], 'flags' => array_map('strval', (array) ($m['FLAGS'] ?? [])),
                    'size' => (int) ($m['RFC822.SIZE'] ?? 0), 'headers' => self::headerItem($m),
                ];
            }
        }
        return $out;
    }

    private static function headerItem(array $m): string
    {
        foreach ($m as $k => $v) {
            if (str_starts_with((string) $k, 'BODY[HEADER') && is_string($v)) {
                return $v;
            }
        }
        return '';
    }

    /** Full raw RFC822 message, or null when it does not exist. */
    public function fetchRaw(int $uid): ?string
    {
        $r = $this->cmd('UID FETCH ' . $uid . ' (UID FLAGS BODY.PEEK[])');
        foreach ($r['untagged'] as $u) {
            $m = $this->fetchItems($u);
            if ($m && (int) ($m['UID'] ?? 0) === $uid && isset($m['BODY[]'])) {
                return (string) $m['BODY[]'];
            }
        }
        return null;
    }

    public function size(int $uid): int
    {
        $r = $this->cmd('UID FETCH ' . $uid . ' (RFC822.SIZE)');
        foreach ($r['untagged'] as $u) {
            if (preg_match('/RFC822\.SIZE (\d+)/i', $u, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /** @param int[] $uids */
    public function setSeen(array $uids, bool $seen): void
    {
        if ($uids) {
            $this->cmd('UID STORE ' . implode(',', array_map('intval', $uids)) . ($seen ? ' +FLAGS.SILENT (\\Seen)' : ' -FLAGS.SILENT (\\Seen)'));
        }
    }

    /** Copies to $dest (when given) then flags \Deleted and expunges. */
    public function remove(int $uid, ?string $dest = null): void
    {
        if ($dest !== null) {
            $this->cmd('UID COPY ' . $uid . ' ' . self::quote(self::encodeFolder($dest)));
        }
        $this->cmd('UID STORE ' . $uid . ' +FLAGS.SILENT (\\Deleted)');
        $this->cmd('EXPUNGE');
    }

    public function append(string $folder, string $raw, bool $seen = true): void
    {
        $tag = $this->nextTag();
        $this->write($tag . ' APPEND ' . self::quote(self::encodeFolder($folder)) . ($seen ? ' (\\Seen)' : '') . ' {' . strlen($raw) . "}\r\n");
        $line = $this->readLine();
        if ($line === '' || $line[0] !== '+') {
            throw new MailException('append_failed', trim($line));
        }
        $this->write($raw . "\r\n");
        $this->finish($tag);
    }

    /* --------------------------------------------------------------- folder names */

    public static function decodeFolder(string $name): string
    {
        if (!str_contains($name, '&')) {
            return $name;
        }
        if (function_exists('mb_convert_encoding')) {
            $r = @mb_convert_encoding($name, 'UTF-8', 'UTF7-IMAP');
            return is_string($r) && $r !== '' ? $r : $name;
        }
        return $name;
    }

    public static function encodeFolder(string $name): string
    {
        if (preg_match('/^[\x20-\x7e]*$/', $name) || !function_exists('mb_convert_encoding')) {
            return $name;
        }
        return (string) mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');
    }

    /* --------------------------------------------------------------- protocol plumbing */

    private function nextTag(): string
    {
        return 'A' . str_pad((string) ++$this->tag, 4, '0', STR_PAD_LEFT);
    }

    /** @return array{untagged:string[],status:string} untagged lines carry literals as \0L<n>\0 markers */
    private function cmd(string $line): array
    {
        $tag = $this->nextTag();
        $this->write($tag . ' ' . $line . "\r\n");
        return $this->finish($tag);
    }

    private function finish(string $tag): array
    {
        $this->lits = [];
        $untagged = [];
        while (true) {
            $line = $this->readResponseLine();
            if (str_starts_with($line, $tag . ' ')) {
                if (!preg_match('/^' . $tag . ' (OK|NO|BAD)\b\s*(.*)$/is', $line, $m)) {
                    throw new MailException('protocol', trim($line));
                }
                if (strtoupper($m[1]) !== 'OK') {
                    throw new MailException(strtoupper($m[1]) === 'NO' ? 'command_failed' : 'protocol', trim($m[2]));
                }
                return ['untagged' => $untagged, 'status' => 'OK'];
            }
            $untagged[] = $line;
        }
    }

    /** Reads one logical response line, pulling {n} literals into $this->lits and leaving a marker. */
    private function readResponseLine(): string
    {
        $out = '';
        while (true) {
            $l = $this->readLine();
            if (preg_match('/^(.*)\{(\d+)\}\r?\n$/s', $l, $m)) {
                $n = (int) $m[2];
                if ($n > 15 * 1024 * 1024) {
                    throw new MailException('too_large', 'message too large');
                }
                $buf = '';
                while (strlen($buf) < $n) {
                    $chunk = fread($this->fp, min(8192, $n - strlen($buf)));
                    if ($chunk === false || $chunk === '') {
                        $meta = stream_get_meta_data($this->fp);
                        throw new MailException(!empty($meta['timed_out']) ? 'timeout' : 'connection_lost', 'connection lost');
                    }
                    $buf .= $chunk;
                }
                $this->lits[] = $buf;
                $out .= $m[1] . "\0L" . (count($this->lits) - 1) . "\0";
                continue;
            }
            return $out . rtrim($l, "\r\n");
        }
    }

    private function readLine(): string
    {
        $l = fgets($this->fp, 65536);
        if ($l === false) {
            $meta = stream_get_meta_data($this->fp);
            throw new MailException(!empty($meta['timed_out']) ? 'timeout' : 'connection_lost', 'connection lost');
        }
        return $l;
    }

    private function write(string $data): void
    {
        $len = strlen($data);
        $sent = 0;
        while ($sent < $len) {
            $n = @fwrite($this->fp, substr($data, $sent, 65536));
            if ($n === false || $n === 0) {
                throw new MailException('connection_lost', 'write failed');
            }
            $sent += $n;
        }
    }

    public static function quote(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    private static function oneLine(string $s): string
    {
        return trim(preg_replace('/[\r\n\0]+/', ' ', $s) ?? '');
    }

    /* --------------------------------------------------------------- response parsing */

    /** Tokenizes a response line into atoms / quoted strings / literals / nested lists. */
    public function tokens(string $s): array
    {
        $pos = 0;
        return $this->parseSeq($s, $pos, false);
    }

    private function parseSeq(string $s, int &$pos, bool $inList): array
    {
        $out = [];
        $n = strlen($s);
        while ($pos < $n) {
            $c = $s[$pos];
            if ($c === ' ') {
                $pos++;
            } elseif ($c === '(') {
                $pos++;
                $out[] = $this->parseSeq($s, $pos, true);
            } elseif ($c === ')') {
                $pos++;
                if ($inList) {
                    return $out;
                }
            } elseif ($c === '"') {
                $pos++;
                $v = '';
                while ($pos < $n && $s[$pos] !== '"') {
                    if ($s[$pos] === '\\' && $pos + 1 < $n) {
                        $pos++;
                    }
                    $v .= $s[$pos++];
                }
                $pos++;
                $out[] = $v;
            } elseif ($c === "\0" && ($s[$pos + 1] ?? '') === 'L') {
                $end = strpos($s, "\0", $pos + 2);
                $idx = (int) substr($s, $pos + 2, $end - $pos - 2);
                $out[] = $this->lits[$idx] ?? '';
                $pos = $end + 1;
            } else {
                $st = $pos;
                while ($pos < $n && !in_array($s[$pos], [' ', '(', ')'], true)) {
                    if ($s[$pos] === '[') { // BODY[HEADER.FIELDS (A B)] contains spaces / parens inside brackets
                        $close = strpos($s, ']', $pos);
                        $pos = $close === false ? $n : $close + 1;
                        continue;
                    }
                    $pos++;
                }
                $a = substr($s, $st, $pos - $st);
                $out[] = strcasecmp($a, 'NIL') === 0 ? null : $a;
            }
        }
        return $out;
    }

    /** @return array<string, mixed>|null FETCH data items keyed by upper-cased name (BODY[...] keeps its section) */
    private function fetchItems(string $line): ?array
    {
        if (!preg_match('/^\* \d+ FETCH \(/i', $line, $m)) {
            return null;
        }
        $pos = strlen($m[0]);
        $t = $this->parseSeq($line, $pos, true);
        $out = [];
        for ($i = 0; $i + 1 < count($t); $i += 2) {
            $out[strtoupper((string) $t[$i])] = $t[$i + 1];
        }
        return $out;
    }
}
