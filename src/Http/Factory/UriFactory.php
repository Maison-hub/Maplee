<?php

namespace Maplee\Http\Factory;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class UriFactory implements UriFactoryInterface
{
    private Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return $this->factory->createUri($uri);
    }

    /**
     * Create a URI from server variables
     * @param array<string, mixed> $server
     * @return UriInterface
     */
    public function createUriFromServer(array $server): UriInterface
    {
        $uri = $this->factory->createUri('');

        if (isset($server['HTTPS']) && $server['HTTPS'] === 'on') {
            $uri = $uri->withScheme('https');
        } else {
            $uri = $uri->withScheme('http');
        }

        if (isset($server['HTTP_HOST'])) {
            $uri = $uri->withHost($server['HTTP_HOST']);
        }

        if (isset($server['SERVER_PORT'])) {
            $uri = $uri->withPort((int) $server['SERVER_PORT']);
        }

        if (isset($server['REQUEST_URI'])) {
            $path = (string) (parse_url($server['REQUEST_URI'], PHP_URL_PATH) ?? '');
            $query = (string) (parse_url($server['REQUEST_URI'], PHP_URL_QUERY) ?? '');

            $uri = $uri->withPath($path);
            $uri = $uri->withQuery($query);
        }

        return $uri;
    }
}
