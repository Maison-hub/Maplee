<?php

use Maplee\RouteHandler;

return RouteHandler::handle(function ($request) {
    return "User Profile: " . $request->getParam('id');
});