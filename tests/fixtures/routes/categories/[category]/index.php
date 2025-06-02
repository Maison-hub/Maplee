<?php

use Maplee\RouteHandler;

return RouteHandler::handle(function ($request) {
    return "Category: " . $request->getParam('category');
});