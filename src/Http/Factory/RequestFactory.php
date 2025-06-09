<?php

namespace Maplee\Http\Factory;

use Maplee\Http\Request;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Factory that creates HTTP requests.
 */
class RequestFactory implements RequestFactoryInterface
{
    /**
     * Create request
     * 
     * @param string $method The HTTP method associated with the request.
     * @param \Psr\Http\Message\UriInterface|string $uri The URI associated with the request.
     * @return \Psr\Http\Message\RequestInterface
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }

}
