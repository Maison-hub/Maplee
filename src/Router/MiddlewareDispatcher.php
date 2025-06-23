<?php

namespace Maplee\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareDispatcher implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private array $middlewares;

    /** @var callable */
    private $finalHandler;

    /**
     * @param MiddlewareInterface[] $middlewares
     * @param callable $finalHandler
     */
    public function __construct(array $middlewares, callable $finalHandler)
    {
        $this->middlewares = $middlewares;
        $this->finalHandler = $finalHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($this->middlewares)) {
            $handler = $this->finalHandler;
            return $handler($request);
        }

        $middleware = array_shift($this->middlewares);

        return $middleware->process($request, $this);
    }
}
