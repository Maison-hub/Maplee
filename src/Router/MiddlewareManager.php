<?php

namespace Maplee\Router;

use Psr\Http\Server\MiddlewareInterface;

class MiddlewareManager
{
    /** @var MiddlewareInterface[] */
    private array $globalMiddlewares = [];

    /**
     * Adds a global middleware that will be applied to all routes
     */
    public function addGlobalMiddleware(MiddlewareInterface $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    /**
     * Returns the middlewares for a specific path, including global and folder-specific middlewares
     *
     * @param string $resolvedFile The resolved file path for which to get the middlewares
     * @return MiddlewareInterface[] An array of middleware instances
     */
    public function getMiddlewaresForPath(string $resolvedFile): array
    {
        $middlewares = $this->globalMiddlewares;
        $path = dirname($resolvedFile);

        while ($path !== dirname($path)) {
            $middlewareFile = $path . DIRECTORY_SEPARATOR . '_middleware.php';

            if (file_exists($middlewareFile)) {
                $result = include $middlewareFile;
                if (is_array($result)) {
                    // Convert strings to middleware instances
                    $folderMiddlewares = array_map(function ($mw) {
                        return is_string($mw) ? new $mw() : $mw;
                    }, $result);
                    $middlewares = array_merge($middlewares, $folderMiddlewares);
                }
            }

            $path = dirname($path);
        }

        return $middlewares;
    }

    /**
     * Returns all global middlewares
     * @return MiddlewareInterface[] An array of global middleware instances
     */
    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }
}
