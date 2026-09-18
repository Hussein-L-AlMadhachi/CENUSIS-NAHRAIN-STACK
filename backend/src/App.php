<?php

declare(strict_types=1);

namespace Cenusis;

use Cenusis\Rpc\Rpc;
use Cenusis\Rpc\Response;

/**
 * Tiny front-controller application:
 *  - mounts enders-sync style RPC endpoints at {path}/discover and {path}/call
 *  - supports simple GET/POST routes with :param placeholders
 *    (used by src/routes/students_xlsx.php, registered via registerStudentsXlsxRoutes()).
 */
final class App
{
    /** @var array<string, Rpc> keyed by mount path, e.g. '/api/public' */
    private array $rpcs = [];

    /** @var array<int, array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function rpc(string $path, ?callable $validator = null): Rpc
    {
        $rpc = new Rpc($validator);
        $this->rpcs[$path] = $rpc;
        return $rpc;
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->route('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->route('POST', $pattern, $handler);
    }

    public function run(): void
    {
        try {
            $this->dispatch();
        } catch (\Throwable $e) {
            (new Response())->status(500)->json(['error' => 'Internal server error ' . $e]);
        }
    }

    private function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        foreach ($this->rpcs as $rpcPath => $rpc) {
            if ($method === 'GET' && $path === $rpcPath . '/discover') {
                header('Content-Type: application/json');
                echo json_encode($rpc->dump());
                return;
            }

            if ($method === 'POST' && $path === $rpcPath . '/call') {
                $body = $this->parseJsonBody();

                // enders-sync: empty/invalid body -> 400 {"error": "Invalid JSON"}
                if (!is_array($body) || count($body) === 0) {
                    (new Response())->status(400)->json(['error' => 'Invalid JSON']);
                    return;
                }

                $req = [
                    'method' => $method,
                    'path' => $path,
                    'query' => $_GET,
                    'cookies' => $_COOKIE,
                    'body' => $body,
                ];

                $res = new Response();
                $result = $rpc->handler($body, $req, $res);
                $res->json($result);
                return;
            }
        }

        // non-RPC routes (e.g. xlsx import/export)
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $req = [
                'method' => $method,
                'path' => $path,
                'params' => $params,
                'query' => $_GET,
                'cookies' => $_COOKIE,
                'body' => $this->parseJsonBody(),
            ];

            ($route['handler'])($req, new Response());
            return;
        }

        (new Response())->status(404)->json(['error' => 'Not found']);
    }

    private function route(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '/:([A-Za-z_][A-Za-z0-9_]*)/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $pattern
        ) ?? $pattern;

        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    /**
     * @return mixed decoded JSON body (array, scalar) or null when empty/invalid
     */
    private function parseJsonBody(): mixed
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }
        return json_decode($raw, true);
    }
}
