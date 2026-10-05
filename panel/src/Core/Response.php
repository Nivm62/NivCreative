<?php
declare(strict_types=1);

namespace Nivc\Core;

final class Response
{
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self($status, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), $headers + [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store, private']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self($status, '', ['Location' => $to, 'Cache-Control' => 'no-store']);
    }

    public static function error(string $code, string $message, int $status, array $extra = []): self
    {
        return self::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message] + $extra], $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $this->body;
    }
}
