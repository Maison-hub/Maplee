<?php

namespace Maplee\Router\Resolver;

class RouteResolver
{
    protected string $routesPath;

    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    public function __construct(string $routesPath)
    {
        $this->routesPath = $routesPath;
    }

    /**
     * Resolve the route
     *
     * @param array<string> $segments
     * @param string $method
     * @param array<string, mixed> $routeCache
     * @return string|null
     */
    public function resolve(array $segments, string $method, array $routeCache = []): ?string
    {
        $this->params = [];

        if (!empty($routeCache)) {
            return $this->resolveFromCache($segments, $method, $routeCache);
        }

        return $this->resolveFromFilesystem($segments, $method);
    }

    /**
     * Get the parameters
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Resolve the route from cache
     *
     * @param array<string> $segments
     * @param string $method
     * @param array<string, mixed> $routeCache
     * @return string|null
     */
    protected function resolveFromCache(array $segments, string $method, array $routeCache): ?string
    {
        $current = '';
        $method = strtolower($method);

        foreach ($segments as $segment) {
            $nextPath = $current ? $current . '/' . $segment : $segment;

            // Check for exact match in files at current path
            if (isset($routeCache['files'][$current][$method])) {
                foreach ($routeCache['files'][$current][$method] as $file) {
                    if ($file['name'] === $segment) {
                        return $file['path'];
                    }
                }
            }
            // Check for exact match in files at next path
            if (isset($routeCache['files'][$nextPath][$method])) {
                foreach ($routeCache['files'][$nextPath][$method] as $file) {
                    if ($file['name'] === $segment) {
                        return $file['path'];
                    }
                }
            }

            // Check for dynamic files
            if (isset($routeCache['files'][$current][$method])) {
                foreach ($routeCache['files'][$current][$method] as $file) {
                    if (preg_match('/^\[(\w+)\]$/', $file['name'], $matches)) {
                        $this->params[$matches[1]] = $segment;
                        return $file['path'];
                    }
                }
            }
            // Check for dynamic files with default GET method
            if ($method === 'get' && isset($routeCache['files'][$current]['get'])) {
                foreach ($routeCache['files'][$current]['get'] as $file) {
                    if (preg_match('/^\[(\w+)\]$/', $file['name'], $matches)) {
                        $this->params[$matches[1]] = $segment;
                        return $file['path'];
                    }
                }
            }

            // Check for dynamic directories
            if (isset($routeCache['dynamic_dirs'][$current])) {
                foreach ($routeCache['dynamic_dirs'][$current] as $dir) {
                    $this->params[$dir['param']] = $segment;
                    $current = $nextPath;
                    continue 2;
                }
            }

            $current = $nextPath;
        }

        // Check for index files in the final directory
        if (isset($routeCache['files'][$current][$method])) {
            foreach ($routeCache['files'][$current][$method] as $file) {
                if ($file['name'] === 'index') {
                    return $file['path'];
                }
            }
        }

        return null;
    }

    /**
     * Resolve the route from filesystem
     *
     * @param array<string> $segments
     * @param string $method
     * @return string|null
     */
    protected function resolveFromFilesystem(array $segments, string $method): ?string
    {
        $current = realpath($this->routesPath);
        if ($current === false) {
            return null;
        }
        $method = strtolower($method);

        foreach ($segments as $segment) {
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
                } elseif (file_exists($fileDefault) && $method === 'get') {
                    return $fileDefault;
                } elseif ($dynamicFile) {
                    $this->params[$dynamicFile['param']] = $segment;
                    return $dynamicFile['path'];
                } elseif ($dynamicFileDefault && $method === 'get') {
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
        } elseif (file_exists($indexDefault) && $method === 'get') {
            return $indexDefault;
        }
        return null;
    }

    /**
     * Match the dynamic folder
     *
     * @param string $dir
     * @param string $segment
     * @return array<string, mixed>|null
     */
    protected function matchDynamicFolder(string $dir, string $segment): ?array
    {
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
     * Match the dynamic file
     *
     * @param string $dir
     * @param string $segment
     * @param string $method
     * @return array<string, mixed>|null
     */
    protected function matchDynamicFile(string $dir, string $segment, string $method): ?array
    {
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
