<?php

namespace Maplee;

class RouteHandler
{
    public static function handle(callable $callback): callable
    {
        return $callback;
    }
}
