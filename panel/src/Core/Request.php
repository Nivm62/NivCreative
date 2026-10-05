<?php
declare(strict_types=1);

namespace Nivc\Core;

final class Request
{
    private static ?string $base = null;
    private ?array $json = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $server,
        public readonly array $cookies,
        private readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = self::basePath();
        $path = ($base !== '' && str_starts_with($uri, $base)) ? substr($uri, strlen($base)) : $uri;
        $path = '/' . trim(rawurldecode($path), '/');
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path === '' ? '/' : $path,
            $_GET,
            $_POST,
            $_SERVER,
            $_COOKIE,
            (string) file_get_contents('php://input')
        );
    }

    /** Base URL path of the app: "/panel" when served from a sub-folder, "" on a sub-domain root. */
    public static function basePath(): string
    {
        if (self::$base === null) {
            $cfg = (string) Config::get('base_url', '');
            if ($cfg !== '') {
                self::$base = rtrim((string) parse_url($cfg, PHP_URL_PATH), '/');
            } else {
                $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
                self::$base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
            }
        }
        return self::$base;
    }

    public static function resetBase(): void
    {
        self::$base = null;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($this->server[$key] ?? '');
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('Content-Type') ?: (string) ($this->server['CONTENT_TYPE'] ?? '')), 'application/json');
    }

    /** Parsed JSON body (empty array if not JSON / invalid). */
    public function json(): array
    {
        if ($this->json === null) {
            $d = $this->rawBody !== '' ? json_decode($this->rawBody, true) : null;
            $this->json = is_array($d) ? $d : [];
        }
        return $this->json;
    }

    /** Body fields: JSON if sent as JSON, otherwise form fields. */
    public function body(): array
    {
        return $this->isJson() ? $this->json() : $this->post;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $b = $this->body();
        return $b[$key] ?? $this->query[$key] ?? $default;
    }

    public function ip(): string
    {
        $hdr = Config::get('ip_header');
        if ($hdr && !empty($this->server[$hdr])) {
            $ip = trim(explode(',', (string) $this->server[$hdr])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 400);
    }

    public function isHttps(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/') || str_contains($this->header('Accept'), 'application/json');
    }
}
