<?php

namespace Maplee\Http\Factory;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        return $psr17Factory->createResponse($code, $reasonPhrase);
    }
}
