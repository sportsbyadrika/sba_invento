<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable|array,3:array}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $mw = []): void
    {
        $this->routes[] = ['GET', $path, $handler, $mw];
    }

    public function post(string $path, callable|array $handler, array $mw = []): void
    {
        $this->routes[] = ['POST', $path, $handler, $mw];
    }

    /** @return array{0:callable|array,1:array,2:array}|null */
    public function match(string $method, string $path): ?array
    {
        $path = '/' . trim($path, '/');
        $methodMismatch = false;
        foreach ($this->routes as [$m, $pattern, $handler, $mw]) {
            $regex = '#^' . preg_replace_callback(
                '#\{(\w+)(?::([^}]+))?\}#',
                static fn ($x) => '(?P<' . $x[1] . '>' . ($x[2] ?? '[^/]+') . ')',
                $pattern
            ) . '$#';
            if (preg_match($regex, $path, $mt)) {
                if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
                    $methodMismatch = true;
                    continue;
                }
                $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, $params, $mw];
            }
        }
        if ($methodMismatch) {
            throw new HttpException(405, 'Method not allowed.');
        }
        return null;
    }
}
