<?php
namespace App\Core;

/**
 * Minimal HTTP router.
 * Maps  METHOD + path  ->  [ControllerClass, 'method'].
 */
class Router
{
    /** @var array<string, array<string, callable|array>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->stripBasePath($uri);
        $path = $this->normalize($path);
        $method = strtoupper($method);

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            $this->notFound();
            return;
        }

        [$class, $action] = $handler;
        (new $class())->$action();
    }

    /** Remove query string and the app's base path (e.g. /admin). */
    private function stripBasePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $base = BASE_PATH;
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        return $path === '' ? '/' : $path;
    }

    /** Collapse to a leading-slash, no-trailing-slash form. */
    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path;
    }

    private function notFound(): void
    {
        http_response_code(404);
        $view = new View();
        $view->render('errors/404', ['title' => 'Not found'], null);
    }
}
