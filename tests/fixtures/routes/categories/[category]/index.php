<?php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

return function (ServerRequestInterface $request, ResponseInterface $response)
{
    $category = $request->getAttribute('category');
    $response->getBody()->write("Category: " . $category);
    return $response;
};
