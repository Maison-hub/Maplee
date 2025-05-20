<?php

namespace Maplee;

use Maplee\MapleeRequest;

class Router
{
    protected string $routesPath;
    protected bool $useCache;
    protected ?string $cacheFile;

    /**
     * Cache of routes structure
     *
     * @var array{
     *     dynamic_dirs?: array<string, array<array{param: string, path: string}>>,
     *     files?: array<string, array<string, array<array{name: string, path: string}>>>
     * }
     */
    protected array $routeCache = [];
    protected ?int $lastCacheUpdate = null;

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
        $this->useCache = $config['useCache'] ?? true;
        $this->cacheFile = $config['cacheFile'] ?? sys_get_temp_dir() . '/maplee_route_cache.php';

        if ($this->useCache) {
            $this->loadCache();
        }
    }

    /**
     * Load the route cache from file
     */
    protected function loadCache(): void
    {
        if ($this->cacheFile === null || !file_exists($this->cacheFile)) {
            $this->rebuildCache();
            return;
        }

        $cache = include $this->cacheFile;
        if (!is_array($cache) || !isset($cache['timestamp']) || !isset($cache['routes'])) {
            $this->rebuildCache();
            return;
        }

        // Check if any route file has been modified since the cache was created
        $lastModified = $this->getLastModifiedTime($this->routesPath);
        if ($lastModified > $cache['timestamp']) {
            $this->rebuildCache();
            return;
        }

        $this->routeCache = $cache['routes'];
        $this->lastCacheUpdate = $cache['timestamp'];
    }

    /**
     * Get cache information for debugging
     *
     * @return array<string, mixed>
     */
    protected function getCacheInfo(): array
    {
        $cacheFile = $this->cacheFile ?? '';
        return [
            'enabled' => $this->useCache,
            'cache_file' => $cacheFile,
            'last_update' => $this->lastCacheUpdate ? date('Y-m-d H:i:s', $this->lastCacheUpdate) : null,
            'file_exists' => $cacheFile !== '' && file_exists($cacheFile),
            'file_size' => ($cacheFile !== '' && file_exists($cacheFile)) ? filesize($cacheFile) : 0,
            'routes_count' => [
                'dynamic_dirs' => isset($this->routeCache['dynamic_dirs'])
                    ? count($this->routeCache['dynamic_dirs'], COUNT_RECURSIVE)
                    : 0,
                'files' => isset($this->routeCache['files'])
                    ? count($this->routeCache['files'], COUNT_RECURSIVE)
                    : 0
            ],
            'cached_routes' => $this->routeCache
        ];
    }

    /**
     * Rebuild the route cache
     */
    protected function rebuildCache(): void
    {
        if (!is_string($this->cacheFile) && $this->cacheFile === null) {
            return;
        }

        $this->routeCache = [];
        $this->scanRoutesForCache($this->routesPath, '');

        $cache = [
            'timestamp' => time(),
            'routes' => $this->routeCache
        ];

        // Create cache directory if it doesn't exist
        $cacheDir = dirname(($this->cacheFile ?? Config::$cachePath));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Save cache to file
        file_put_contents(
            $this->cacheFile,
            '<?php return ' . var_export($cache, true) . ';'
        );

        $this->lastCacheUpdate = $cache['timestamp'];
    }

    /**
     * Get the last modified time of all files in a directory
     *
     * @return int The last modified timestamp
     */
    protected function getLastModifiedTime(string $path): int
    {
        $lastModified = filemtime($path);
        if ($lastModified === false) {
            return 0;
        }

        $items = @scandir($path);
        if ($items === false) {
            return $lastModified;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $dirLastModified = $this->getLastModifiedTime($fullPath);
                $lastModified = max($lastModified, $dirLastModified);
            } else {
                $fileModified = filemtime($fullPath);
                if ($fileModified !== false) {
                    $lastModified = max($lastModified, $fileModified);
                }
            }
        }

        return $lastModified;
    }

    /**
     * Scan routes for cache building
     */
    protected function scanRoutesForCache(string $basePath, string $prefix): void
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
            $relativePath = $prefix ? $prefix . '/' . $item : $item;

            if (is_dir($fullPath)) {
                // Handle dynamic directories
                if (preg_match('/^\[(\w+)\]$/', $item, $matches)) {
                    $this->routeCache['dynamic_dirs'][$prefix][] = [
                        'param' => $matches[1],
                        'path' => $fullPath
                    ];
                }
                $this->scanRoutesForCache($fullPath, $relativePath);
            } else {
                // Handle files
                if (preg_match('/^(.+)\.(?:(get|post|put|delete|patch)\.)?php$/', $item, $matches)) {
                    $fileName = $matches[1];
                    $method = $matches[2] ?? 'get';

                    $this->routeCache['files'][$prefix][$method][] = [
                        'name' => $fileName,
                        'path' => $fullPath
                    ];
                }
            }
        }
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
                "cache" => $this->getCacheInfo()
            ], JSON_PRETTY_PRINT);
            return;
        }

        // Add a dedicated endpoint for cache information
        if ($uri === '/__maplee/cache') {
            header('Content-Type: application/json');
            echo json_encode($this->getCacheInfo(), JSON_PRETTY_PRINT);
            return;
        }

        $uri = trim((string) $uri, '/');
        $path = $uri;
        $segments = explode('/', $path);

        $resolvedFile = $this->useCache
            ? $this->resolveFileFromCache($segments)
            : $this->resolveFile($segments);

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
     * Resolve file from cache
     *
     * @param array<int, string> $segments The URL segments to resolve
     */
    protected function resolveFileFromCache(array $segments): ?string
    {
        $current = '';
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->params = [];

        foreach ($segments as $segment) {
            $nextPath = $current ? $current . '/' . $segment : $segment;

            // Check for exact match in files at current path
            if (isset($this->routeCache['files'][$current][$method])) {
                foreach ($this->routeCache['files'][$current][$method] as $file) {
                    if ($file['name'] === $segment) {
                        return $file['path'];
                    }
                }
            }
            // Check for exact match in files at next path
            if (isset($this->routeCache['files'][$nextPath][$method])) {
                foreach ($this->routeCache['files'][$nextPath][$method] as $file) {
                    if ($file['name'] === $segment) {
                        return $file['path'];
                    }
                }
            }

            // Check for dynamic files
            if (isset($this->routeCache['files'][$current][$method])) {
                foreach ($this->routeCache['files'][$current][$method] as $file) {
                    if (preg_match('/^\[(\w+)\]$/', $file['name'], $matches)) {
                        $this->params[$matches[1]] = $segment;
                        return $file['path'];
                    }
                }
            }
            // Check for dynamic files with default GET method
            if ($method === 'get' && isset($this->routeCache['files'][$current]['get'])) {
                foreach ($this->routeCache['files'][$current]['get'] as $file) {
                    if (preg_match('/^\[(\w+)\]$/', $file['name'], $matches)) {
                        $this->params[$matches[1]] = $segment;
                        return $file['path'];
                    }
                }
            }

            // Check for dynamic directories
            if (isset($this->routeCache['dynamic_dirs'][$current])) {
                foreach ($this->routeCache['dynamic_dirs'][$current] as $dir) {
                    $this->params[$dir['param']] = $segment;
                    $current = $nextPath;
                    continue 2;
                }
            }

            $current = $nextPath;
        }

        // Check for index files in the final directory
        if (isset($this->routeCache['files'][$current][$method])) {
            foreach ($this->routeCache['files'][$current][$method] as $file) {
                if ($file['name'] === 'index') {
                    return $file['path'];
                }
            }
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
