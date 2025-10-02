<?php

namespace Maplee\Router;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Maplee\Http\Factory\UriFactory;
use Maplee\Http\Factory\ResponseFactory;
use Maplee\Router\Cache\RouteCache;
use Maplee\Router\Config\RouterConfig;
use Maplee\Router\Resolver\RouteResolver;
use Maplee\Http\Factory\ServerRequestFactory;
use Nyholm\Psr7\Stream;

class Router
{
    protected string $routesPath;
    protected RouteCache $routeCache;
    protected RouteResolver $routeResolver;
    protected ServerRequestFactory $serverRequestFactory;
    protected ResponseFactoryInterface $responseFactory;
    protected UriFactoryInterface $uriFactory;
    protected MiddlewareManager $middlewareManager;

    /**
     * @var array<string, mixed>
     */
    protected $config;

    /**
     * Router constructor.
     *
     * @param string|null $configPath Path to the configuration file.
     * @param array<string, mixed> $overrides Array of overrides for the configuration.
     */
    public function __construct(?string $configPath = null, array $overrides = [])
    {
        $this->config = RouterConfig::load($configPath, $overrides);


        $this->routesPath = $this->config['routesPath'];
        $this->routeCache = new RouteCache($this->config['cacheFile'], $this->config['useCache']);
        $this->routeResolver = new RouteResolver($this->routesPath);
        $this->serverRequestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
        $this->uriFactory = new UriFactory();
        $this->middlewareManager = new MiddlewareManager();

        if ($this->config['useCache']) {
            $this->routeCache->loadCache($this->routesPath);
        }
    }

    /**
     * Ajoute un middleware global
     */
    public function addMiddleware(\Psr\Http\Server\MiddlewareInterface $middleware): void
    {
        $this->middlewareManager->addGlobalMiddleware($middleware);
    }

    /**
     * Main function of Maplee Router use it in your index.php to handle all requests
     */
    public function handleRequest(): void
    {
        $request = $this->serverRequestFactory->createServerRequestFromGlobals();
        $response = $this->responseFactory->createResponse();

        if ($request->getUri()->getPath() === '/__maplee/routes') {
            $jsonData = json_encode([
                "routes-directory" => $this->routesPath,
                "routes" => $this->listRoutes(),
                "cache" => $this->routeCache->getCacheInfo()
            ], JSON_PRETTY_PRINT);

            if ($jsonData === false) {
                throw new \RuntimeException('Failed to encode routes data to JSON');
            }

            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->createStream($jsonData));
            $this->emitResponse($response);
            return;
        }

        if ($request->getUri()->getPath() === '/__maplee/cache') {
            $jsonData = json_encode($this->routeCache->getCacheInfo(), JSON_PRETTY_PRINT);

            if ($jsonData === false) {
                throw new \RuntimeException('Failed to encode cache data to JSON');
            }

            $response = $response
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->createStream($jsonData));
            $this->emitResponse($response);
            return;
        }

        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        $segments = explode('/', trim($uri, '/'));

        $resolvedFile = $this->routeResolver->resolve(
            $segments,
            $method,
            $this->routeCache->isCacheEnabled(),
            $this->routeCache->getRouteCache()
        );

        if ($resolvedFile && file_exists($resolvedFile)) {
            $params = $this->routeResolver->getParams();
            $queryParams = [];
            parse_str($request->getUri()->getQuery(), $queryParams);
            $params = array_merge($params, $queryParams);

            // Injecting parameters into the request attributes
            foreach ($params as $key => $value) {
                $request = $request->withAttribute((string) $key, $value);
            }

            $result = include $resolvedFile;

            if (is_callable($result)) {
                // Resolve middlewares for the route
                $middlewares = $this->middlewareManager->getMiddlewaresForPath($resolvedFile);

                $finalHandler = function (ServerRequestInterface $request) use ($result, $response) {
                    $routeResponse = $result($request, $response);

                    if (is_string($routeResponse)) {
                        return $response->withBody($this->createStream($routeResponse));
                    } elseif (is_array($routeResponse)) {
                        $jsonData = json_encode($routeResponse);
                        if ($jsonData === false) {
                            throw new \RuntimeException('Failed to encode route response to JSON');
                        }
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withBody($this->createStream($jsonData));
                    } elseif ($routeResponse instanceof ResponseInterface) {
                        return $routeResponse;
                    }
                    return $response;
                };

                $dispatcher = new MiddlewareDispatcher($middlewares, $finalHandler);
                $routeResponse = $dispatcher->handle($request);

                $this->emitResponse($routeResponse);
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
        return Stream::create($content);
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
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        echo $body->getContents();
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
