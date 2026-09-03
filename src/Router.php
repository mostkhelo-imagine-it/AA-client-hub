<?php
declare(strict_types=1);

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $params = $this->match($pattern, $path);
            if ($params !== null) {
                if ($method === 'POST') {
                    verify_csrf();
                }
                call_user_func($handler, $params);
                return;
            }
        }

        http_response_code(404);
        render('errors/404');
    }

    /** @return array<string,string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $i => $part) {
            if (str_starts_with($part, ':')) {
                $params[substr($part, 1)] = $pathParts[$i];
            } elseif ($part !== $pathParts[$i]) {
                return null;
            }
        }
        return $params;
    }
}
