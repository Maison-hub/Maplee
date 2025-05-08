<?php

namespace Maplee;

class Router
{
    protected string $routesPath;
    protected array $params = [];

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
                "routes-directory"=> $this->routesPath,
                "routes" => $this->listRoutes(),
            ], JSON_PRETTY_PRINT);
            return;
        }

        $path = trim($uri, '/');
        $segments = explode('/', $path);

        $resolvedFile = $this->resolveFile($segments);

        if ($resolvedFile && file_exists($resolvedFile)) {
            $result = include $resolvedFile;

            if (is_callable($result)) {
                $response = $result($this->params);
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
     * @param array $segments
     * @return string|null
     */
    protected function resolveFile(array $segments): ?string
    {
        $this->params = [];
        $current = realpath($this->routesPath);
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
                    var_dump($this->params);
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

    public function listRoutes(): array
    {
        $routes = [];
        $this->scanRoutes($this->routesPath, '', $routes);
        return $routes;
    }

    protected function scanRoutes(string $basePath, string $prefix, array &$routes): void
    {
        foreach (scandir($basePath) as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $basePath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->scanRoutes($fullPath, $prefix . '/' . $item, $routes);
            } elseif ($item === 'index.php') {
                $routes[] = $prefix ?: '/';
            }
        }
    }

    protected function matchDynamicFolder(string $dir, string $segment): ?array
    {
        foreach (scandir($dir) as $entry) {
            if (preg_match('/^\[(\w+)\]$/', $entry, $matches) && is_dir($dir . DIRECTORY_SEPARATOR . $entry)) {
                return [
                    'param' => $matches[1],
                    'path' => $dir . DIRECTORY_SEPARATOR . $entry
                ];
            }
        }
        return null;
    }

    protected function matchDynamicFile(string $dir, string $segment, string $method): ?array
    {
        foreach (scandir($dir) as $entry) {
            if (preg_match('/^\[(\w+)\]\.' . $method . '\.php$/', $entry, $matches)) {
                return [
                    'param' => $matches[1],
                    'path' => $dir . '/' . $entry
                ];
            }
    
            // Si méthode GET par défaut (aucun suffixe)
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
