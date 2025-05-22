<?php

use Maplee\RouteHandler;

return RouteHandler::handle(function ($request) {
    return "Create User: " . json_encode($request->post);
});