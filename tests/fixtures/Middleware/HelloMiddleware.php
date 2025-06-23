<?php

namespace Maplee\tests\fixtures\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HelloMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Appelle le prochain middleware ou le contrôleur
        $response = $handler->handle($request);

        // On récupère le contenu actuel du body
        $body = (string) $response->getBody();

        // On crée un nouveau body avec le texte ajouté
        $newBodyContent = "[Hello]" . $body;

        // On crée un nouveau stream
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $newBodyContent);
        rewind($stream);

        // On retourne une nouvelle réponse avec le body modifié
        return $response->withBody(new \Nyholm\Psr7\Stream($stream));
    }
}
