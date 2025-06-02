<?php

namespace Maplee\Router;

use Maplee\Router\Cache\RouteCache;
use Maplee\Router\Config\RouterConfig;
use Maplee\Router\Resolver\RouteResolver;
use Maplee\MapleeRequest;

class Router
{
    protected string $routesPath;
    protected RouteCache $routeCache;
    protected RouteResolver $routeResolver;

    /**
     * Router constructor.
     *
     * @param string|null $configPath Path to the configuration file.
     * @param array<string, mixed> $overrides Array of overrides for the configuration.
     */
    public function __construct(?string $configPath = null, array $overrides = [])
    {
        $config = RouterConfig::load($configPath, $overrides);

        $this->routesPath = $config['routesPath'];
        $this->routeCache = new RouteCache($config['cacheFile'], $config['useCache']);
        $this->routeResolver = new RouteResolver($this->routesPath);

        if ($config['useCache']) {
            $this->routeCache->loadCache($this->routesPath);
        }
    }

    /**
     * Main function of Maplee Router use it in your index.php to handle all requests
     */
    public function handleRequest(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

        // Return debug information about the routes
        if ($uri === '/__maplee/routes') {
            header('Content-Type: application/json');
            echo json_encode([
                "routes-directory" => $this->routesPath,
                "routes" => $this->listRoutes(),
                "cache" => $this->routeCache->getCacheInfo()
            ], JSON_PRETTY_PRINT);
            return;
        }

        // Add a dedicated endpoint for cache information
        if ($uri === '/__maplee/cache') {
            header('Content-Type: application/json');
            echo json_encode($this->routeCache->getCacheInfo(), JSON_PRETTY_PRINT);
            return;
        }

        $uri = trim((string) $uri, '/');
        $segments = explode('/', $uri);

        $resolvedFile = $this->routeResolver->resolve(
            $segments,
            $_SERVER['REQUEST_METHOD'],
            $this->routeCache->getRouteCache()
        );

        if ($resolvedFile && file_exists($resolvedFile)) {
            $params = $this->routeResolver->getParams();
            if (isset($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $queryParams);
                $params = array_merge($params, $queryParams);
            }

            $request = new MapleeRequest(
                $uri,
                $_SERVER['REQUEST_METHOD'],
                $params,
                $_POST
            );

            $result = include $resolvedFile;

            if (is_callable($result)) {
                $response = $result($request);
                echo $response;
            }
        } else {
            http_response_code(404);
            echo "404 Not Found";
        }
    }

    /**
     * List all routes in the routes directory
     *
     * @return array<string>
     */
    public function listRoutes(): array
    {
        $routes = [];
        $this->scanRoutes($this->routesPath, '', $routes);
        return $routes;
    }

    /**
     * Scan the routes directory recursively
     *
     * @param string $basePath
     * @param string $prefix
     * @param array<string> &$routes
     */
    protected function scanRoutes(string $basePath, string $prefix, array &$routes): void
    {
        $items = @scandir($basePath);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $basePath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->scanRoutes($fullPath, $prefix . '/' . $item, $routes);
            } elseif ($item === 'index.php' || $item === 'index.get.php') {
                $routes[] = $prefix ?: '/';
            } elseif (preg_match('/^(.+)\.(?:(get|post|put|delete|patch)\.)?php$/', $item, $matches)) {
                $route = $prefix ? $prefix . '/' . $matches[1] : '/' . $matches[1];
                if (!in_array($route, $routes)) {
                    $routes[] = $route;
                }
            }
        }
    }
}
