<?php

namespace Maplee\Http\Factory;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class UriFactory implements UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        return $psr17Factory->createUri($uri);
    }
}
