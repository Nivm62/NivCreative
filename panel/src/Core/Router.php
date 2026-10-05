<?php
declare(strict_types=1);

namespace Nivc\Core;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:callable|array,3:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler, array $mw = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler, $mw];
    }

    public function get(string $p, callable|array $h, array $mw = []): void
    {
        $this->add('GET', $p, $h, $mw);
    }

    public function post(string $p, callable|array $h, array $mw = []): void
    {
        $this->add('POST', $p, $h, $mw);
    }

    public function put(string $p, callable|array $h, array $mw = []): void
    {
        $this->add('PUT', $p, $h, $mw);
    }

    public function delete(string $p, callable|array $h, array $mw = []): void
    {
        $this->add('DELETE', $p, $h, $mw);
    }

    /** @return array{0:callable|array,1:array,2:array}|null handler, params, middleware */
    public function match(string $method, string $path): ?array
    {
        $allowed = false;
        foreach ($this->routes as [$m, $regex, $h, $mw]) {
            if (preg_match($regex, $path, $mt)) {
                if ($m === $method || ($method === 'HEAD' && $m === 'GET')) {
                    $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
                    return [$h, $params, $mw];
                }
                $allowed = true;
            }
        }
        if ($allowed) {
            throw new HttpException(405, 'Method not allowed', 'method_not_allowed');
        }
        return null;
    }
}
