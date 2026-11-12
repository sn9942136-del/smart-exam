<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Config\App;
use App\Controllers\ErrorController;
use App\Filters\FilterInterface;

/**
 * Router Library
 * Fast URL dispatcher with parameter extraction, route grouping, and middleware filters.
 */
class Router
{
    private static array $routes = [];
    private static array $groupStack = [];

    /**
     * Dapatkan seluruh rute yang terdaftar
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Daftarkan route GET
     */
    public static function get(string $uri, array|callable $handler, array $options = []): void
    {
        self::addRoute(['GET'], $uri, $handler, $options);
    }

    /**
     * Daftarkan route POST
     */
    public static function post(string $uri, array|callable $handler, array $options = []): void
    {
        self::addRoute(['POST'], $uri, $handler, $options);
    }

    /**
     * Daftarkan route PUT
     */
    public static function put(string $uri, array|callable $handler, array $options = []): void
    {
        self::addRoute(['PUT'], $uri, $handler, $options);
    }

    /**
     * Daftarkan route DELETE
     */
    public static function delete(string $uri, array|callable $handler, array $options = []): void
    {
        self::addRoute(['DELETE'], $uri, $handler, $options);
    }

    /**
     * Daftarkan route untuk berbagai HTTP method
     */
    public static function match(array $methods, string $uri, array|callable $handler, array $options = []): void
    {
        self::addRoute(array_map('strtoupper', $methods), $uri, $handler, $options);
    }

    /**
     * Route grouping dengan prefix dan filters bersama
     */
    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = $attributes;
        $callback();
        array_pop(self::$groupStack);
    }

    /**
     * Tambahkan route ke routing table
     */
    private static function addRoute(array $methods, string $uri, array|callable $handler, array $options): void
    {
        $prefix = '';
        $filters = [];

        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['filter'])) {
                $groupFilters = is_array($group['filter']) ? $group['filter'] : [$group['filter']];
                $filters = array_merge($filters, $groupFilters);
            }
        }

        if (isset($options['filter'])) {
            $routeFilters = is_array($options['filter']) ? $options['filter'] : [$options['filter']];
            $filters = array_merge($filters, $routeFilters);
        }

        $fullUri = $prefix . '/' . trim($uri, '/');
        $fullUri = '/' . trim($fullUri, '/');

        self::$routes[] = [
            'methods' => $methods,
            'uri'     => $fullUri,
            'handler' => $handler,
            'filters' => array_unique($filters)
        ];
    }

    /**
     * Dispatch request yang masuk ke Controller yang sesuai
     */
    public static function dispatch(Request $request): void
    {
        $requestMethod = $request->getMethod();
        $requestPath = $request->getPath();

        foreach (self::$routes as $route) {
            if (!in_array($requestMethod, $route['methods'], true) && !in_array('ANY', $route['methods'], true)) {
                continue;
            }

            // Convert route pattern {param} to regex
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $requestPath, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                $request->setRouteParams($params);

                // Jalankan Middleware / Filters (Before)
                $activeFilters = self::resolveFilters($route['filters']);
                foreach ($activeFilters as $item) {
                    /** @var FilterInterface $filterInstance */
                    $filterInstance = $item['instance'];
                    $filterArgs = $item['arguments'];

                    $filterResponse = $filterInstance->before($request, $filterArgs);
                    if ($filterResponse instanceof Response) {
                        $filterResponse->send();
                        return;
                    }
                }

                // Eksekusi Controller Handler
                $response = self::executeHandler($route['handler'], $request, $params);

                // Jalankan Middleware / Filters (After)
                foreach ($activeFilters as $item) {
                    /** @var FilterInterface $filterInstance */
                    $filterInstance = $item['instance'];
                    $filterArgs = $item['arguments'];
                    $filterInstance->after($request, $response, $filterArgs);
                }

                $response->send();
                return;
            }
        }

        // Rute Tidak Ditemukan -> 404
        self::handleNotFound($request);
    }

    /**
     * Menjalankan handler controller atau callable
     */
    private static function executeHandler(array|callable $handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $result = call_user_func_array($handler, array_merge([$request], $params));
        } elseif (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $method] = $handler;

            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Controller [{$controllerClass}] tidak ditemukan.");
            }

            $controller = new $controllerClass($request);

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method [{$method}] pada Controller [{$controllerClass}] tidak ditemukan.");
            }

            $result = call_user_func_array([$controller, $method], array_values($params));
        } else {
            throw new \RuntimeException("Format Handler Route tidak valid.");
        }

        if ($result instanceof Response) {
            return $result;
        }

        $response = new Response();
        $response->setContent((string) $result);
        return $response;
    }

    /**
     * Resolusi Filter string/class menjadi instance FilterInterface
     */
    private static function resolveFilters(array $filters): array
    {
        $filterAliases = [
            'auth'       => \App\Filters\AuthFilter::class,
            'role'       => \App\Filters\RoleFilter::class,
            'permission' => \App\Filters\PermissionFilter::class,
            'csrf'       => \App\Filters\CsrfFilter::class,
            'rate_limit' => \App\Filters\RateLimitFilter::class,
        ];

        $resolved = [];
        foreach ($filters as $filterDef) {
            $parts = explode(':', $filterDef, 2);
            $filterName = $parts[0];
            $filterArgs = isset($parts[1]) ? explode(',', $parts[1]) : [];

            $filterClass = $filterAliases[$filterName] ?? $filterName;

            if (class_exists($filterClass)) {
                $instance = new $filterClass();
                if ($instance instanceof FilterInterface) {
                    $resolved[] = [
                        'instance'  => $instance,
                        'arguments' => $filterArgs
                    ];
                }
            }
        }

        return $resolved;
    }

    /**
     * Menangani 404 Not Found
     */
    private static function handleNotFound(Request $request): void
    {
        $controller = new ErrorController($request);
        $response = $controller->notFound();
        $response->send();
    }
}
