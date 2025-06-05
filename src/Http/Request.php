<?php

namespace Maplee\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request implements ServerRequestInterface
{
    public function getCookieParams(): array
    {
        return $_COOKIE;
    }
} 