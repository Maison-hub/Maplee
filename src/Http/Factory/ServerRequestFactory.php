<?php

namespace Maplee\Http\Factory;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class ServerRequestFactory implements ServerRequestFactoryInterface
{
    /**
     * Create a new server request from global variables.
     *
     * @return ServerRequestInterface
     */
    public function createServerRequestFromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

        $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
            $psr17Factory, // ServerRequestFactory
            $psr17Factory, // UriFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // StreamFactory
        );

        $serverRequest = $creator->fromGlobals();
        return $serverRequest;
    }

    /**
     * Create a new server request with the given method and URI.
     *
     * @param string $method The HTTP method (e.g., 'GET', 'POST').
     * @param UriInterface|string $uri The URI as a string or a UriInterface.
     * @param array<string, mixed> $serverParams Additional server parameters.
     * @return ServerRequestInterface
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $serverRequest = $psr17Factory->createServerRequest($method, $uri, $serverParams);
        return $serverRequest;
    }
}
