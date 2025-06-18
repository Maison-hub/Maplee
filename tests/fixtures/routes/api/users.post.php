<?php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

return function (ServerRequestInterface $request, ResponseInterface $response) {
    // Récupérer le body de la requête
    $body = $request->getParsedBody() ?? $_POST;
    
    return "Create User: " . json_encode($body);
}; 