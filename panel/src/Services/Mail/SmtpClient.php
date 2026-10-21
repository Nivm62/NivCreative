<?php
declare(strict_types=1);

namespace Nivc\Services\Mail;

/** Minimal authenticated SMTP submission client (implicit TLS on 465, STARTTLS on 587, or plain for tests). */
final class SmtpClient
{
    /** @var resource|null */
    private $fp = null;

    public function __construct(private string $host, private int $port, private string $secure = 'ssl', private int $timeout = 15)
    {
    }

    public function connect(string $ehloName = 'localhost'): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->host, 'SNI_enabled' => true]]);
        $errno = 0;
        $err = '';
        $fp = @stream_socket_client(($this->secure === 'ssl' ? 'ssl' : 'tcp') . '://' . $this->host . ':' . $this->port, $errno, $err, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new MailException('connect_failed', $err !== '' ? $err : 'connection failed');
        }
        stream_set_timeout($fp, $this->timeout);
        $this->fp = $fp;
        $this->expect(220);
        $this->cmd('EHLO ' . $ehloName, 250);
        if ($this->secure === 'tls') {
            $this->cmd('STARTTLS', 220);
            if (!@stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailException('tls_failed', 'STARTTLS failed');
            }
            $this->cmd('EHLO ' . $ehloName, 250);
        }
    }

    public function login(string $user, string $pass): void
    {
        if (preg_match('/[\r\n\0]/', $user . $pass)) {
            throw new MailException('auth_failed', 'invalid characters');
        }
        try {
            $this->cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), 235);
        } catch (MailException $e) {
            throw new MailException('auth_failed', $e->getMessage());
        }
    }

    /** @param string[] $rcpts */
    public function send(string $from, array $rcpts, string $raw): void
    {
        $this->cmd('MAIL FROM:<' . self::addr($from) . '>', 250);
        foreach ($rcpts as $r) {
            $this->cmd('RCPT TO:<' . self::addr($r) . '>', [250, 251]);
        }
        $this->cmd('DATA', 354);
        $data = preg_replace('/\r\n|\r|\n/', "\r\n", $raw) ?? $raw;
        $data = preg_replace('/^\./m', '..', $data) ?? $data; // dot-stuffing
        $this->write(rtrim($data, "\r\n") . "\r\n.\r\n");
        $this->expect(250);
    }

    public function quit(): void
    {
        if ($this->fp) {
            try {
                $this->cmd('QUIT', 221);
            } catch (\Throwable) {
            }
            @fclose($this->fp);
            $this->fp = null;
        }
    }

    private static function addr(string $a): string
    {
        return str_replace(["\r", "\n", '<', '>', ' '], '', $a);
    }

    private function cmd(string $line, int|array $ok): string
    {
        $this->write($line . "\r\n");
        return $this->expect($ok);
    }

    private function expect(int|array $ok): string
    {
        $ok = (array) $ok;
        $text = '';
        do {
            $l = fgets($this->fp, 4096);
            if ($l === false) {
                $meta = stream_get_meta_data($this->fp);
                throw new MailException(!empty($meta['timed_out']) ? 'timeout' : 'connection_lost', 'connection lost');
            }
            $text .= $l;
            $more = strlen($l) > 3 && $l[3] === '-';
        } while ($more);
        $code = (int) substr($text, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new MailException($code >= 500 ? 'rejected' : 'command_failed', trim(preg_replace('/^\d{3}[ -]/m', '', $text) ?? $text));
        }
        return $text;
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
}
