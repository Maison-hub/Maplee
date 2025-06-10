<?php

namespace Maplee\Router;

use Maplee\Router\Cache\RouteCache;
use Maplee\Router\Config\RouterConfig;
use Maplee\Router\Resolver\RouteResolver;
use Maplee\Http\Factory\ServerRequestFactory;
use Maplee\Http\Factory\ResponseFactory;
use Maplee\Http\Factory\UriFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    protected string $routesPath;
    protected RouteCache $routeCache;
    protected RouteResolver $routeResolver;
    protected ServerRequestFactory $serverRequestFactory;
    protected ResponseFactory $responseFactory;
    protected UriFactory $uriFactory;

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
        $this->serverRequestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
        $this->uriFactory = new UriFactory();

        if ($config['useCache']) {
            $this->routeCache->loadCache($this->routesPath);
        }
    }

    /**
     * Main function of Maplee Router use it in your index.php to handle all requests
     */
    public function handleRequest(): void
    {
        $request = $this->serverRequestFactory->createServerRequestFromGlobals();
        $response = $this->responseFactory->createResponse();

        if ($request->getUri()->getPath() === '/__maplee/routes') {
            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->createStream(json_encode([
                    "routes-directory" => $this->routesPath,
                    "routes" => $this->listRoutes(),
                    "cache" => $this->routeCache->getCacheInfo()
                ], JSON_PRETTY_PRINT)));
            $this->emitResponse($response);
            return;
        }

        // Add a dedicated endpoint for cache information
        if ($request->getUri()->getPath() === '/__maplee/cache') {
            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->createStream(json_encode($this->routeCache->getCacheInfo(), JSON_PRETTY_PRINT)));
            $this->emitResponse($response);
            return;
        }

        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        $segments = explode('/', trim($uri, '/'));

        $resolvedFile = $this->routeResolver->resolve(
            $segments,
            $method,
            $this->routeCache->getRouteCache()
        );

        if ($resolvedFile && file_exists($resolvedFile)) {
            $params = $this->routeResolver->getParams();
            $queryParams = [];
            parse_str($request->getUri()->getQuery(), $queryParams);
            $params = array_merge($params, $queryParams);

            // Injecter les paramètres dans la requête
            foreach ($params as $key => $value) {
                $request = $request->withAttribute($key, $value);
            }

            $result = include $resolvedFile;

            if (is_callable($result)) {
                $routeResponse = $result($request, $response);

                if (is_string($routeResponse)) {
                    // If the route returns a string, we set it as the body of the response
                    $response = $response->withBody($this->createStream($routeResponse));
                } elseif (is_array($routeResponse)) {
                    // If the route returns an array, we assume it's JSON data
                    $response = $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withBody($this->createStream(json_encode($routeResponse)));
                } elseif ($routeResponse instanceof ResponseInterface) {
                    // If the route returns a ResponseInterface, we use it directly
                    $response = $routeResponse;
                }
                // If the route returns something else, we ignore it
                $this->emitResponse($response);
                return;
            }
        }

        $response = $this->responseFactory->createResponse(404)
            ->withBody($this->createStream('404 Not Found'));
        $this->emitResponse($response);
    }

    /**
     * Create a stream from a string
     */
    private function createStream(string $content): \Psr\Http\Message\StreamInterface
    {
        $stream = $this->serverRequestFactory->createStream();
        $stream->write($content);
        $stream->rewind();
        return $stream;
    }

    /**
     * Emit a response to the client
     */
    private function emitResponse(ResponseInterface $response): void
    {
        // Send status code
        http_response_code($response->getStatusCode());

        // Send headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value));
            }
        }

        // Send body
        echo $response->getBody()->getContents();
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
