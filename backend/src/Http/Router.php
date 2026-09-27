<?php

declare(strict_types=1);

namespace App\Http;

use App\Utils\Logger;
use Throwable;

class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];
    private string $currentPrefix = '';
    private array $currentGroupMiddlewares = [];

    public function use(callable|string $middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->currentPrefix;
        $previousMiddlewares = $this->currentGroupMiddlewares;

        if (isset($attributes['prefix'])) {
            $this->currentPrefix = rtrim($this->currentPrefix . '/' . trim($attributes['prefix'], '/'), '/');
        }

        if (isset($attributes['middleware'])) {
            $mw = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->currentGroupMiddlewares = array_merge($this->currentGroupMiddlewares, $mw);
        }

        $callback($this);

        $this->currentPrefix = $previousPrefix;
        $this->currentGroupMiddlewares = $previousMiddlewares;
    }

    public function addRoute(string $method, string $path, mixed $handler, array $middlewares = []): self
    {
        $fullPath = '/' . trim($this->currentPrefix . '/' . trim($path, '/'), '/');
        $allMiddlewares = array_merge($this->currentGroupMiddlewares, $middlewares);

        // Convert {param} into named capture groups
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];

        return $this;
    }

    public function get(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function options(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middlewares);
    }

    public function dispatch(Request $request): Response
    {
        // Handle OPTIONS preflight early if requested
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, X-Requested-With',
            ]);
        }

        $method = $request->getMethod();
        $path = $request->getPath();

        $matchedRoute = null;
        $matchedParams = [];
        $methodNotAllowed = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['method'] === $method) {
                    $matchedRoute = $route;
                    foreach ($matches as $key => $val) {
                        if (is_string($key)) {
                            $matchedParams[$key] = $val;
                        }
                    }
                    break;
                } else {
                    $methodNotAllowed = true;
                }
            }
        }

        if ($matchedRoute === null) {
            if ($methodNotAllowed) {
                return Response::error("Method {$method} not allowed for path {$path}", 405);
            }
            return Response::error("Route {$path} not found.", 404);
        }

        $request->setParams($matchedParams);

        // Build middleware execution pipeline
        $pipeline = array_merge($this->globalMiddlewares, $matchedRoute['middlewares']);
        $handler = $matchedRoute['handler'];

        $runner = function (Request $req) use ($handler): Response {
            try {
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $action] = $handler;
                    $controller = is_string($class) ? new $class() : $class;
                    return $controller->$action($req);
                }

                if (is_callable($handler)) {
                    return $handler($req);
                }

                return Response::error('Invalid route handler configuration.', 500);
            } catch (Throwable $e) {
                Logger::error($e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 500;
                $message = config('app.debug', false) ? $e->getMessage() : 'An internal server error occurred.';
                return Response::error($message, $statusCode);
            }
        };

        // Wrap pipeline in reverse
        for ($i = count($pipeline) - 1; $i >= 0; $i--) {
            $currentMw = $pipeline[$i];
            $next = $runner;
            $runner = function (Request $req) use ($currentMw, $next): Response {
                if (is_string($currentMw) && class_exists($currentMw)) {
                    $instance = new $currentMw();
                    return $instance->handle($req, $next);
                }
                if (is_callable($currentMw)) {
                    return $currentMw($req, $next);
                }
                return $next($req);
            };
        }

        return $runner($request);
    }
}
