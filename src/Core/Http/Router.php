<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Attribute\Route;
use App\Core\Container;
use App\Exception\NotFoundException;
use ReflectionClass;

final class Router
{
    /** @var list<array<string, mixed>> */
    private array $routes = [];

    public function __construct(private Container $container)
    {
    }

    public function register(string $controllerClass): void
    {
        $class = new ReflectionClass($controllerClass);

        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();
                $this->routes[] = $this->compile($controllerClass, $method->getName(), $route);
            }
        }

        usort($this->routes, static function (array $left, array $right): int {
            return [$right['static'], -$right['params'], strlen((string) $right['path'])]
                <=> [$left['static'], -$left['params'], strlen((string) $left['path'])];
        });
    }

    public function dispatch(Request $request): Response
    {
        $match = $this->match($request->method, $request->path);
        if ($match === null) {
            throw new NotFoundException('Page not found.');
        }

        [$route, $params] = $match;
        $request->setRouteParams($params);
        if ($route['role'] !== null) {
            $request->setAttribute('role', $route['role']);
        }

        $controller = $this->container->get($route['controller']);
        $action = $route['action'];

        $destination = static function (Request $request) use ($controller, $action): Response {
            $response = $controller->{$action}($request);
            if (!$response instanceof Response) {
                throw new \RuntimeException($action . ' must return a response.');
            }

            return $response;
        };

        foreach (array_reverse($route['middleware']) as $middlewareClass) {
            $middleware = $this->container->get($middlewareClass);
            $next = $destination;
            $destination = static function (Request $request) use ($middleware, $next): Response {
                return $middleware->handle($request, $next);
            };
        }

        return $destination($request);
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>}|null */
    private function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
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

            return [$route, $params];
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function compile(string $controller, string $action, Route $route): array
    {
        $segments = explode('/', trim($route->path, '/'));
        $static = 0;
        $params = 0;
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (str_starts_with($segment, '{')) {
                $params++;
            } else {
                $static++;
            }
        }

        $pattern = preg_replace_callback(
            '/\{([a-z_]+)\}/',
            static function (array $matches): string {
                $name = $matches[1];
                $expression = $name === 'id' ? '\d+' : '[a-z0-9\-]+';

                return '(?P<' . $name . '>' . $expression . ')';
            },
            $route->path,
        );

        return [
            'controller' => $controller,
            'action' => $action,
            'methods' => $route->methods(),
            'middleware' => $route->middleware,
            'role' => $route->role,
            'path' => $route->path,
            'regex' => '#^' . $pattern . '$#',
            'static' => $static,
            'params' => $params,
        ];
    }
}
