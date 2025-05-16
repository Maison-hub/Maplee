<?php

namespace Maplee\Tests;

use PHPUnit\Framework\TestCase;
use Maplee\RouteHandler;
use Maplee\MapleeRequest;

/**
 * @covers \Maplee\RouteHandler
 */
class RouteHandlerTest extends TestCase
{
    /**
     * @covers \Maplee\RouteHandler::handle
     */
    public function testHandleReturnsCallable(): void
    {
        $callback = function (MapleeRequest $request) {
            return 'Test Response';
        };

        $handler = RouteHandler::handle($callback);
        $this->assertIsCallable($handler);
    }

    /**
     * @covers \Maplee\RouteHandler::handle
     */
    public function testHandleWithRouteParameters(): void
    {
        $expectedId = '123';
        
        $callback = function (MapleeRequest $request) {
            return "Post ID is: " . $request->getParams()['id'];
        };

        $handler = RouteHandler::handle($callback);
        
        // Créer une requête avec des paramètres simulés
        $request = new MapleeRequest(
            '/blog/post/123',
            'GET',
            ['id' => $expectedId],
            []
        );

        $response = $handler($request);
        
        $this->assertEquals("Post ID is: " . $expectedId, $response);
    }

    /**
     * @covers \Maplee\RouteHandler::handle
     */
    public function testHandleWithMultipleRouteParameters(): void
    {
        $callback = function (MapleeRequest $request) {
            $params = $request->getParams();
            return "Category: " . $params['category'] . ", Post ID: " . $params['id'];
        };

        $handler = RouteHandler::handle($callback);
        
        $request = new MapleeRequest(
            '/blog/tech/123',
            'GET',
            [
                'category' => 'tech',
                'id' => '123'
            ],
            []
        );

        $response = $handler($request);
        
        $this->assertEquals("Category: tech, Post ID: 123", $response);
    }
} 