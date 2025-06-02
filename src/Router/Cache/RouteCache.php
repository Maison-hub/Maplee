<?php

namespace Maplee\Router\Cache;

class RouteCache
{
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
    protected string $cacheFile;
    protected bool $useCache;

    public function __construct(string $cacheFile, bool $useCache = true)
    {
        // If the cache file path is not writable, use temp directory
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
            $cacheFile = sys_get_temp_dir() . '/maplee_route_cache.php';
        }

        $this->cacheFile = $cacheFile;
        $this->useCache = $useCache;
    }

    public function loadCache(string $routesPath): void
    {
        if (!$this->useCache || !file_exists($this->cacheFile)) {
            $this->rebuildCache($routesPath);
            return;
        }

        $cache = include $this->cacheFile;
        if (!is_array($cache) || !isset($cache['timestamp']) || !isset($cache['routes'])) {
            $this->rebuildCache($routesPath);
            return;
        }

        // Check if any route file has been modified since the cache was created
        $lastModified = $this->getLastModifiedTime($routesPath);
        if ($lastModified > $cache['timestamp']) {
            $this->rebuildCache($routesPath);
            return;
        }

        $this->routeCache = $cache['routes'];
        $this->lastCacheUpdate = $cache['timestamp'];
    }

    /**
     * Get information about the cache status
     *
     * @return array{
     *     enabled: bool,
     *     cache_file: string,
     *     last_update: string|null,
     *     file_exists: bool,
     *     file_size: int,
     *     routes_count: array{
     *         dynamic_dirs: int,
     *         files: int
     *     },
     *     cached_routes: array{
     *         dynamic_dirs?: array<string, array<array{param: string, path: string}>>,
     *         files?: array<string, array<string, array<array{name: string, path: string}>>>
     *     }
     * }
     */
    public function getCacheInfo(): array
    {
        $fileSize = file_exists($this->cacheFile) ? filesize($this->cacheFile) : 0;
        if ($fileSize === false) {
            $fileSize = 0;
        }

        return [
            'enabled' => $this->useCache,
            'cache_file' => $this->cacheFile,
            'last_update' => $this->lastCacheUpdate ? date('Y-m-d H:i:s', $this->lastCacheUpdate) : null,
            'file_exists' => file_exists($this->cacheFile),
            'file_size' => $fileSize,
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
     * Get the cached routes
     *
     * @return array{
     *     dynamic_dirs?: array<string, array<array{param: string, path: string}>>,
     *     files?: array<string, array<string, array<array{name: string, path: string}>>>
     * }
     */
    public function getRouteCache(): array
    {
        return $this->routeCache;
    }

    protected function rebuildCache(string $routesPath): void
    {
        $this->routeCache = [];
        $this->scanRoutesForCache($routesPath, '');

        $cache = [
            'timestamp' => (int)(microtime(true) * 1000),
            'routes' => $this->routeCache
        ];

        // Create cache directory if it doesn't exist
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        // If we still can't write to the directory, use temp directory
        if (!is_writable($cacheDir)) {
            $this->cacheFile = sys_get_temp_dir() . '/maplee_route_cache.php';
        }

        // Save cache to file
        file_put_contents(
            $this->cacheFile,
            '<?php return ' . var_export($cache, true) . ';'
        );

        $this->lastCacheUpdate = $cache['timestamp'];
    }

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
}
