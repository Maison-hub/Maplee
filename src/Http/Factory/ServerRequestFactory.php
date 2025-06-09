<?php

namespace Maplee\Http\Factory;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class ServerRequestFactory implements ServerRequestFactoryInterface
{
    private Psr17Factory $factory;
    private ServerRequestCreator $serverRequestCreator;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
        $this->serverRequestCreator = new ServerRequestCreator(
            $this->factory, // ServerRequestFactory
            $this->factory, // UriFactory
            $this->factory, // UploadedFileFactory
            $this->factory  // StreamFactory
        );
    }

    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        if (is_string($uri)) {
            $uri = $this->factory->createUri($uri);
        }

        return $this->factory->createServerRequest($method, $uri, $serverParams);
    }

    public function createServerRequestFromGlobals(): ServerRequestInterface
    {
        return $this->serverRequestCreator->fromGlobals();
    }

    public function createStream(string $content = ''): StreamInterface
    {
        $stream = $this->factory->createStream();
        if ($content !== '') {
            $stream->write($content);
            $stream->rewind();
        }
        return $stream;
    }
} 