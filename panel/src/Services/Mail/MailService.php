<?php
declare(strict_types=1);

namespace Nivc\Services\Mail;

use Nivc\Core\Actor;
use Nivc\Core\Crypto;
use Nivc\Core\Db;
use Nivc\Core\HttpException;
use Nivc\Core\NowTime;
use Nivc\Core\RateLimiter;
use Nivc\Core\Validator;
use Nivc\Services\LeadService;

/** Each administrator can connect one mailbox (IMAP + SMTP). The password is stored encrypted and never returned. */
final class MailService
{
    public const PRESETS = [
        'hostinger' => ['imap_host' => 'imap.hostinger.com', 'imap_port' => 993, 'imap_secure' => 'ssl', 'smtp_host' => 'smtp.hostinger.com', 'smtp_port' => 465, 'smtp_secure' => 'ssl'],
    ];
    private const MAX_RAW = 12 * 1024 * 1024;

    /* ------------------------------------------------------------------ account */

    public static function row(Actor $a): ?array
    {
        try {
            return Db::one('SELECT * FROM mailboxes WHERE user_id = ?', [$a->userId]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S02') { // base table missing: an upgrade did not create it, do it now (schema is idempotent)
                throw $e;
            }
            try {
                \Nivc\Controllers\Web\InstallController::runSchema(Db::connect());
                return Db::one('SELECT * FROM mailboxes WHERE user_id = ?', [$a->userId]);
            } catch (\Throwable $e2) {
                throw new HttpException(500, t('mail.err.db_setup') . ' — ' . mb_substr($e2->getMessage(), 0, 300), 'mail_db_setup');
            }
        }
    }

    public static function present(?array $r): ?array
    {
        if (!$r) {
            return null;
        }
        return [
            'email' => $r['email'], 'display_name' => $r['display_name'], 'username' => $r['username'], 'signature' => (string) $r['signature'],
            'imap_host' => $r['imap_host'], 'imap_port' => (int) $r['imap_port'], 'imap_secure' => $r['imap_secure'],
            'smtp_host' => $r['smtp_host'], 'smtp_port' => (int) $r['smtp_port'], 'smtp_secure' => $r['smtp_secure'],
            'last_error' => $r['last_error'], 'has_password' => $r['password_enc'] !== '',
        ];
    }

    public static function save(Actor $a, array $in): array
    {
        $old = self::row($a);
        $v = new Validator($in);
        $d = [
            'email' => $v->email('email'), 'display_name' => $v->str('display_name', false, 190), 'signature' => $v->str('signature', false, 2000, true),
            'imap_host' => strtolower($v->str('imap_host', true, 190)), 'smtp_host' => strtolower($v->str('smtp_host', true, 190)),
            'imap_port' => $v->int('imap_port', 1, 65535, true) ?? 993, 'smtp_port' => $v->int('smtp_port', 1, 65535, true) ?? 465,
            'imap_secure' => $v->enum('imap_secure', ['ssl', 'tls', 'none'], 'ssl'), 'smtp_secure' => $v->enum('smtp_secure', ['ssl', 'tls', 'none'], 'ssl'),
        ];
        $d['username'] = trim((string) ($in['username'] ?? '')) !== '' ? trim((string) $in['username']) : $d['email'];
        $pw = (string) ($in['password'] ?? '');
        if ($pw === '' && !$old) {
            $v->fail('password', t('validation.required'));
        }
        if (preg_match('/[\r\n\0]/', $pw . $d['username'])) {
            $v->fail('password', t('validation.invalid'));
        }
        foreach (['imap_host', 'smtp_host'] as $k) {
            if ($d[$k] !== '' && !self::hostAllowed($d[$k])) {
                $v->fail($k, t('mail.err.host_blocked'));
            }
        }
        if (!self::localAllowed() && ($d['imap_secure'] === 'none' || $d['smtp_secure'] === 'none')) {
            $v->fail('imap_secure', t('mail.err.insecure'));
        }
        $v->check();
        $now = NowTime::mysql();
        $row = $d + ['last_error' => '', 'updated_at' => $now];
        $row['password_enc'] = $pw !== '' ? Crypto::encrypt($pw) : $old['password_enc'];
        if ($old) {
            Db::update('mailboxes', $row, ['user_id' => $a->userId]);
        } else {
            Db::insert('mailboxes', $row + ['user_id' => $a->userId, 'created_at' => $now]);
        }
        return self::present(self::row($a));
    }

    public static function remove(Actor $a): void
    {
        Db::exec('DELETE FROM mailboxes WHERE user_id = ?', [$a->userId]);
    }

    private static function localAllowed(): bool
    {
        return (bool) getenv('NIVC_MAIL_ALLOW_LOCAL');
    }

    /** Blocks private/loopback targets (SSRF) unless the test switch is on. */
    private static function hostAllowed(string $host): bool
    {
        if (self::localAllowed()) {
            return true;
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host) || !str_contains($host, '.') && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? '';
            }
        }
        if (!$ips) {
            return true; // cannot resolve here (DNS may differ); the connection attempt will fail cleanly
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    /* ------------------------------------------------------------------ connections */

    private static function need(Actor $a): array
    {
        $r = self::row($a);
        if (!$r) {
            throw new HttpException(409, t('mail.err.not_configured'), 'mail_not_configured');
        }
        return $r;
    }

    private static function imap(array $acc): ImapClient
    {
        $pass = Crypto::decrypt($acc['password_enc']);
        if ($pass === null) {
            throw new MailException('auth_failed', 'stored password cannot be decrypted');
        }
        $c = new ImapClient($acc['imap_host'], (int) $acc['imap_port'], $acc['imap_secure'], 12);
        $c->connect();
        $c->login($acc['username'], $pass);
        return $c;
    }

    /** Runs $fn with a connected IMAP client; converts protocol failures into API errors and records the last error. */
    private static function withImap(Actor $a, callable $fn): mixed
    {
        $acc = self::need($a);
        $c = null;
        try {
            $c = self::imap($acc);
            $res = $fn($c, $acc);
            if ($acc['last_error'] !== '') {
                Db::update('mailboxes', ['last_error' => ''], ['user_id' => $a->userId]);
            }
            return $res;
        } catch (MailException $e) {
            throw self::fail($a, $e);
        } finally {
            if ($c) {
                $c->logout();
            }
        }
    }

    private static function fail(Actor $a, MailException $e): HttpException
    {
        $msg = t('mail.err.' . $e->errorCode);
        if ($msg === 'mail.err.' . $e->errorCode) {
            $msg = t('mail.err.generic');
        }
        $detail = mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 160);
        Db::update('mailboxes', ['last_error' => mb_substr($msg . ($detail !== '' ? ' (' . $detail . ')' : ''), 0, 250)], ['user_id' => $a->userId]);
        return new HttpException(502, $msg . ($detail !== '' ? ' — ' . $detail : ''), 'mail_' . $e->errorCode);
    }

    /** @return array{imap:array{ok:bool,error:string},smtp:array{ok:bool,error:string}} */
    public static function test(Actor $a): array
    {
        $acc = self::need($a);
        $out = ['imap' => ['ok' => true, 'error' => ''], 'smtp' => ['ok' => true, 'error' => '']];
        try {
            $c = self::imap($acc);
            $c->select('INBOX');
            $c->logout();
        } catch (MailException $e) {
            $out['imap'] = ['ok' => false, 'error' => t('mail.err.' . $e->errorCode) . ' — ' . mb_substr($e->getMessage(), 0, 160)];
        }
        try {
            $s = new SmtpClient($acc['smtp_host'], (int) $acc['smtp_port'], $acc['smtp_secure'], 12);
            $s->connect(self::ehlo($acc));
            $s->login($acc['username'], (string) Crypto::decrypt($acc['password_enc']));
            $s->quit();
        } catch (MailException $e) {
            $out['smtp'] = ['ok' => false, 'error' => t('mail.err.' . $e->errorCode) . ' — ' . mb_substr($e->getMessage(), 0, 160)];
        }
        $err = (!$out['imap']['ok'] ? 'IMAP: ' . $out['imap']['error'] : '') . (!$out['smtp']['ok'] ? ' SMTP: ' . $out['smtp']['error'] : '');
        Db::update('mailboxes', ['last_error' => mb_substr(trim($err), 0, 250)], ['user_id' => $a->userId]);
        return $out;
    }

    private static function ehlo(array $acc): string
    {
        return substr((string) strrchr($acc['email'], '@'), 1) ?: 'localhost';
    }

    /* ------------------------------------------------------------------ folders / messages */

    public static function folders(Actor $a): array
    {
        return self::withImap($a, static fn(ImapClient $c) => self::folderList($c));
    }

    /** @return array<int,array{name:string,label:string,role:string}> */
    private static function folderList(ImapClient $c): array
    {
        $out = [];
        foreach ($c->folders() as $f) {
            $attrs = strtolower(implode(' ', $f['attrs']));
            $n = strtolower(ImapClient::decodeFolder($f['name']));
            $base = strtolower(preg_replace('/^.*[\/.]/', '', $n) ?? $n);
            $role = match (true) {
                $n === 'inbox' => 'inbox',
                str_contains($attrs, '\\sent') || in_array($base, ['sent', 'sent items', 'sent mail', 'sent messages'], true) => 'sent',
                str_contains($attrs, '\\trash') || in_array($base, ['trash', 'deleted', 'deleted items', 'deleted messages'], true) => 'trash',
                str_contains($attrs, '\\drafts') || $base === 'drafts' => 'drafts',
                str_contains($attrs, '\\junk') || in_array($base, ['junk', 'spam', 'junk email'], true) => 'junk',
                str_contains($attrs, '\\archive') || $base === 'archive' => 'archive',
                default => 'other',
            };
            $out[] = ['name' => ImapClient::decodeFolder($f['name']), 'label' => ImapClient::decodeFolder($f['name']), 'role' => $role];
        }
        usort($out, static function ($x, $y) {
            $order = ['inbox' => 0, 'sent' => 1, 'drafts' => 2, 'archive' => 3, 'junk' => 4, 'trash' => 5, 'other' => 6];
            return [$order[$x['role']], $x['name']] <=> [$order[$y['role']], $y['name']];
        });
        return $out;
    }

    private static function assertFolder(ImapClient $c, string $folder): string
    {
        foreach (self::folderList($c) as $f) {
            if ($f['name'] === $folder) {
                return $folder;
            }
        }
        throw new HttpException(404, t('error.not_found'), 'not_found');
    }

    public static function messages(Actor $a, array $q): array
    {
        $folder = (string) ($q['folder'] ?? 'INBOX');
        $per = (int) ($q['per_page'] ?? 25);
        $per = in_array($per, [10, 25, 50], true) ? $per : 25;
        $page = max(1, (int) ($q['page'] ?? 1));
        $text = trim((string) ($q['q'] ?? ''));
        $unseen = ($q['unseen'] ?? '') === '1';
        return self::withImap($a, static function (ImapClient $c) use ($folder, $per, $page, $text, $unseen) {
            self::assertFolder($c, $folder);
            $c->select($folder);
            $uids = $c->search($unseen ? 'UNSEEN' : 'ALL', $text !== '' ? $text : null);
            $total = count($uids);
            $slice = array_slice(array_reverse($uids), ($page - 1) * $per, $per);
            $hdrs = $c->fetchHeaders($slice);
            $items = [];
            foreach ($slice as $uid) {
                if (!isset($hdrs[$uid])) {
                    continue;
                }
                $h = Mime::parseHeaders($hdrs[$uid]['headers']);
                $from = Mime::addresses($h['from'] ?? '')[0] ?? ['name' => '', 'email' => ''];
                $ts = strtotime($h['date'] ?? '');
                $items[] = [
                    'uid' => $uid, 'from' => $from, 'to' => array_column(Mime::addresses($h['to'] ?? ''), 'email'),
                    'subject' => Mime::decodeWords($h['subject'] ?? ''), 'date' => $ts ? self::local($ts) : null,
                    'seen' => in_array('\\seen', array_map('strtolower', $hdrs[$uid]['flags']), true), 'size' => $hdrs[$uid]['size'],
                    'lead_id' => null,
                ];
            }
            self::attachLeads($items);
            return ['items' => $items, 'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per, 'pages' => max(1, (int) ceil($total / $per))]];
        });
    }

    private static function local(int $ts): string
    {
        return (new \DateTimeImmutable('@' . $ts))->setTimezone(\Nivc\Core\NowTime::tz())->format('Y-m-d H:i:s');
    }

    /** Marks messages whose sender / recipient is a known lead. */
    private static function attachLeads(array &$items): void
    {
        $emails = [];
        foreach ($items as $m) {
            $emails[] = $m['from']['email'];
            foreach ($m['to'] as $e) {
                $emails[] = $e;
            }
        }
        $emails = array_values(array_unique(array_filter($emails)));
        if (!$emails) {
            return;
        }
        $map = [];
        foreach (array_chunk($emails, 100) as $chunk) {
            $rows = Db::all('SELECT id, email FROM leads WHERE email IN (' . implode(',', array_fill(0, count($chunk), '?')) . ') ORDER BY id', $chunk);
            foreach ($rows as $r) {
                $map[strtolower($r['email'])] = (int) $r['id'];
            }
        }
        foreach ($items as &$m) {
            $m['lead_id'] = $map[$m['from']['email']] ?? (isset($m['to'][0]) ? ($map[$m['to'][0]] ?? null) : null);
        }
    }

    public static function message(Actor $a, string $folder, int $uid): array
    {
        return self::withImap($a, static function (ImapClient $c) use ($folder, $uid) {
            self::assertFolder($c, $folder);
            $c->select($folder);
            if ($c->size($uid) > self::MAX_RAW) {
                throw new MailException('too_large', 'message too large');
            }
            $raw = $c->fetchRaw($uid);
            if ($raw === null) {
                throw new HttpException(404, t('error.not_found'), 'not_found');
            }
            $c->setSeen([$uid], true);
            $p = Mime::parse($raw);
            $h = $p['headers'];
            $from = Mime::addresses($h['from'] ?? '')[0] ?? ['name' => '', 'email' => ''];
            $ts = strtotime($h['date'] ?? '');
            $leads = [];
            $addr = array_values(array_unique(array_filter([$from['email'], ...array_column(Mime::addresses($h['to'] ?? ''), 'email')])));
            if ($addr) {
                $leads = array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'status' => $r['status'], 'client' => $r['business_name']],
                    Db::all('SELECT l.id, l.name, l.status, c.business_name FROM leads l JOIN clients c ON c.id = l.client_id WHERE l.email IN (' . implode(',', array_fill(0, count($addr), '?')) . ') ORDER BY l.id DESC LIMIT 5', $addr));
            }
            return ['message' => [
                'uid' => $uid, 'folder' => $folder, 'from' => $from, 'to' => Mime::addresses($h['to'] ?? ''), 'cc' => Mime::addresses($h['cc'] ?? ''),
                'reply_to' => Mime::addresses($h['reply-to'] ?? '')[0]['email'] ?? '',
                'subject' => Mime::decodeWords($h['subject'] ?? ''), 'date' => $ts ? self::local($ts) : null,
                'message_id' => $h['message-id'] ?? '', 'references' => $h['references'] ?? '',
                'text' => mb_substr($p['text'], 0, 200000), 'attachments' => $p['attachments'], 'leads' => $leads,
            ]];
        });
    }

    public static function attachment(Actor $a, string $folder, int $uid, string $part): array
    {
        if (!preg_match('/^\d+(\.\d+)*$/', $part)) {
            throw new HttpException(404, t('error.not_found'), 'not_found');
        }
        return self::withImap($a, static function (ImapClient $c) use ($folder, $uid, $part) {
            self::assertFolder($c, $folder);
            $c->select($folder);
            if ($c->size($uid) > self::MAX_RAW) {
                throw new MailException('too_large', 'message too large');
            }
            $raw = $c->fetchRaw($uid);
            $found = $raw === null ? null : Mime::partData($raw, $part);
            if (!$found) {
                throw new HttpException(404, t('error.not_found'), 'not_found');
            }
            return $found;
        });
    }

    /** @param int[] $uids */
    public static function setSeen(Actor $a, string $folder, array $uids, bool $seen): void
    {
        self::withImap($a, static function (ImapClient $c) use ($folder, $uids, $seen) {
            self::assertFolder($c, $folder);
            $c->select($folder);
            $c->setSeen(array_filter(array_map('intval', $uids), static fn($u) => $u > 0), $seen);
        });
    }

    /** Moves to Trash (or deletes for good when already in Trash / no Trash folder exists). */
    public static function delete(Actor $a, string $folder, int $uid): void
    {
        self::withImap($a, static function (ImapClient $c) use ($folder, $uid) {
            self::assertFolder($c, $folder);
            $trash = null;
            $inTrash = false;
            foreach (self::folderList($c) as $f) {
                if ($f['role'] === 'trash') {
                    $trash = $f['name'];
                    $inTrash = $f['name'] === $folder;
                }
            }
            $c->select($folder);
            $c->remove($uid, $trash !== null && !$inTrash ? $trash : null);
        });
    }

    /* ------------------------------------------------------------------ sending */

    public static function send(Actor $a, array $in): array
    {
        $acc = self::need($a);
        if (!RateLimiter::hit('mail:send:' . $a->userId, 30, 3600)) {
            throw new HttpException(429, t('api.rate_limited'), 'rate_limited');
        }
        $v = new Validator($in);
        $to = self::emails((string) ($in['to'] ?? ''));
        $cc = self::emails((string) ($in['cc'] ?? ''));
        $subject = $v->str('subject', false, 255);
        $body = (string) ($in['body'] ?? '');
        if (!$to) {
            $v->fail('to', t('mail.err.recipient'));
        }
        if (count($to) + count($cc) > 20) {
            $v->fail('to', t('mail.err.too_many'));
        }
        if (trim($body) === '' || strlen($body) > 200000) {
            $v->fail('body', t('validation.required'));
        }
        $v->check();
        $msg = Mime::build([
            'from' => $acc['email'], 'from_name' => $acc['display_name'], 'to' => $to, 'cc' => $cc, 'subject' => $subject, 'body' => $body,
            'in_reply_to' => (string) ($in['in_reply_to'] ?? ''), 'references' => (string) ($in['references'] ?? ''),
        ]);
        $saved = false;
        try {
            $s = new SmtpClient($acc['smtp_host'], (int) $acc['smtp_port'], $acc['smtp_secure'], 15);
            $s->connect(self::ehlo($acc));
            $s->login($acc['username'], (string) Crypto::decrypt($acc['password_enc']));
            $s->send($acc['email'], array_merge($to, $cc), $msg['raw']);
            $s->quit();
        } catch (MailException $e) {
            throw self::fail($a, $e);
        }
        try { // keep a copy in the Sent folder (best effort: the mail is already out)
            $c = self::imap($acc);
            foreach (self::folderList($c) as $f) {
                if ($f['role'] === 'sent') {
                    $c->append($f['name'], $msg['raw'], true);
                    $saved = true;
                    break;
                }
            }
            $c->logout();
        } catch (MailException) {
        }
        $leadId = (int) ($in['lead_id'] ?? 0);
        if ($leadId > 0) {
            try {
                LeadService::logContact($a, $leadId, 'email');
            } catch (HttpException) {
            }
        }
        return ['message_id' => $msg['message_id'], 'saved_to_sent' => $saved];
    }

    /** @return string[] valid, de-duplicated addresses from "a@b.c, Name <d@e.f>; g@h.i" */
    private static function emails(string $s): array
    {
        $out = [];
        foreach (preg_split('/[,;\n]+/', $s) ?: [] as $p) {
            $p = trim($p);
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $p = trim($m[1]);
            }
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $p)) {
                $out[strtolower($p)] = strtolower($p);
            }
        }
        return array_values($out);
    }
}
