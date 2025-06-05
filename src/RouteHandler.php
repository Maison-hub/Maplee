<?php

namespace Maplee;

use Maplee\MapleeRequest;

class RouteHandler
{
    /**
     * @param callable(MapleeRequest): string $callback
     * @return callable(MapleeRequest): string
     */
    public static function handle(callable $callback): callable
    {
        return $callback;
    }
}
