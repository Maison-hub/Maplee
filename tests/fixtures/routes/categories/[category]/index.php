<?php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

return function (ServerRequestInterface $request, ResponseInterface $response) {
    return "Category: " . $request->getAttribute('category');
};