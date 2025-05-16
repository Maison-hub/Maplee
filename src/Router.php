<?php

namespace Maplee;

use Maplee\MapleeRequest;

class Router
{
    protected string $routesPath;

    /**
     * @var array<int|string, mixed>
     */
    protected array $params = [];

    /**
     * Router constructor.
     *
     * @param string|null $configPath Path to the configuration file.
     * @param array<string, mixed> $overrides Array of overrides for the configuration.
     */
    public function __construct(?string $configPath = null, array $overrides = [])
    {
        $fileConfig = Config::load($configPath);
        $config = array_merge($fileConfig, $overrides);

        $this->routesPath = $config['routesPath'] ?? __DIR__ . '/../../routes';
    }

    /**
     * Main function of Maplee Router use it in your index.php to handle all requests
     *
     * @return void
     *
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
            ], JSON_PRETTY_PRINT);
            return;
        }
        $uri = trim((string) $uri, '/');
        $path = $uri;
        $segments = explode('/', $path);

        $resolvedFile = $this->resolveFile($segments);

        if ($resolvedFile && file_exists($resolvedFile)) {
            if (isset($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $queryParams);
                $this->params = array_merge($this->params, $queryParams);
            }
            $request = new MapleeRequest(
                $uri,
                $_SERVER['REQUEST_METHOD'],
                $this->params,
                $_POST // Simulate body parameters
            );

            $result = include $resolvedFile;

            if (is_callable($result)) {
                $response = $result($request); // Pass the MapleeRequest object
                echo $response;
            }
        } else {
            http_response_code(404);
            echo "404 Not Found";
        }
    }

    /**
     * Resolve the file path based on the segments
     *
     * @param array<string> $segments
     * @return string|null
     */
    protected function resolveFile(array $segments): ?string
    {
        $this->params = [];
        $current = realpath($this->routesPath);
        if ($current === false) {
            return null;
        }
        $method = strtolower($_SERVER['REQUEST_METHOD']);

        foreach ($segments as $i => $segment) {
            $nextDir = $current . DIRECTORY_SEPARATOR . $segment;
            $dynamicDir = $this->matchDynamicFolder($current, $segment);

            if (is_dir($nextDir)) {
                $current = $nextDir;
            } elseif ($dynamicDir) {
                $current = $dynamicDir['path'];
                $this->params[$dynamicDir['param']] = $segment;
            } else {
                $fileMethod = $current . DIRECTORY_SEPARATOR . $segment . '.' . $method . '.php';
                $fileDefault = $current . DIRECTORY_SEPARATOR . $segment . '.php';

                $dynamicFile = $this->matchDynamicFile($current, $segment, $method);
                $dynamicFileDefault = $this->matchDynamicFile($current, $segment, 'get');

                if (file_exists($fileMethod)) {
                    return $fileMethod;
                } elseif (file_exists($fileDefault)) {
                    return $fileDefault;
                } elseif ($dynamicFile) {
                    $this->params[$dynamicFile['param']] = $segment;
                    return $dynamicFile['path'];
                } elseif ($dynamicFileDefault) {
                    $this->params[$dynamicFileDefault['param']] = $segment;
                    return $dynamicFileDefault['path'];
                }
                return null;
            }
        }

        $indexMethod = $current . '/index.' . $method . '.php';
        $indexDefault = $current . '/index.php';

        if (file_exists($indexMethod)) {
            return $indexMethod;
        } elseif (file_exists($indexDefault)) {
            return $indexDefault;
        }
        return null;
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
     * @param array<string> $routes
     * @return void
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
            } elseif ($item === 'index.php') {
                $routes[] = $prefix ?: '/';
            }
        }
    }

    /**
     * Match dynamic folders
     *
     * @param string|false $dir
     * @param string $segment
     * @return array<string, string>|null
     */
    protected function matchDynamicFolder(string|false $dir, string $segment): ?array
    {
        if ($dir === false) {
            return null;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return null;
        }

        foreach ($items as $entry) {
            if (preg_match('/^\[(\w+)\]$/', $entry, $matches) && is_dir($dir . DIRECTORY_SEPARATOR . $entry)) {
                return [
                    'param' => $matches[1],
                    'path' => $dir . DIRECTORY_SEPARATOR . $entry
                ];
            }
        }
        return null;
    }

    /**
     * Match dynamic files
     *
     * @param string|false $dir
     * @param string $segment
     * @param string $method
     * @return array<string, string>|null
     */
    protected function matchDynamicFile(string|false $dir, string $segment, string $method): ?array
    {
        if ($dir === false) {
            return null;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return null;
        }

        foreach ($items as $entry) {
            if (preg_match('/^\[(\w+)\]\.' . $method . '\.php$/', $entry, $matches)) {
                return [
                    'param' => $matches[1],
                    'path' => $dir . '/' . $entry
                ];
            }
            if ($method === 'get' && preg_match('/^\[(\w+)\]\.php$/', $entry, $matches)) {
                return [
                    'param' => $matches[1],
                    'path' => $dir . '/' . $entry
                ];
            }
        }
        return null;
    }
}
