<?php

namespace Maplee;

use JetBrains\PhpStorm\NoReturn;

class RouteHandler
{
    public static function handle(callable $callback): callable
    {
        return $callback;
    }
}
