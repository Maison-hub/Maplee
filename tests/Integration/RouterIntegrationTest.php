<?php

namespace Maplee\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Maplee\Router\Router;

/**
 * @covers \Maplee\Router\Router
 */
class RouterIntegrationTest extends TestCase
{
    private Router $router;
    /** @var array<string, mixed> */
    private array $originalServer;
    /** @var array<string, mixed> */
    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
        
        $this->router = new Router(null, [
            'routesPath' => __DIR__ . '/../fixtures/routes',
            'useCache' => false
        ]);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        parent::tearDown();
    }

    public function testStaticRouteHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/about';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        
        $this->assertEquals("About Page", $output);
    }

//    public function testDynamicRouteHandling(): void
//    {
//        $_SERVER['REQUEST_URI'] = '/users/123';
//        $_SERVER['REQUEST_METHOD'] = 'GET';
//
//        ob_start();
//        $this->router->handleRequest();
//        $output = ob_get_clean();
//
//        $this->assertEquals("User ID: 123", $output);
//    }

    //TODO: Make sure to test method specific route handling
    // This test is commented out because it requires a specific setup for POST requests
    // public function testMethodSpecificRouteHandling(): void
    // {
    //     $_SERVER['REQUEST_METHOD'] = 'POST';
    //     $_SERVER['REQUEST_URI'] = '/api/users';
    //     $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    //     $_SERVER['HTTP_CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        
    //     // Simulate Body data request
    //     $jsonData = json_encode(['name' => 'John', 'email' => 'john@example.com']);
    //     $stream = fopen('php://input', 'r+');
    //     fwrite($stream, $jsonData);
    //     rewind($stream);

    //     ob_start();
    //     $this->router->handleRequest();
    //     $output = ob_get_clean();
        
    //     $this->assertEquals('Create User:{"name":"John"}', $output);
    // }

    public function testIndexRouteHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        
        $this->assertEquals("Blog Index", $output);
    }

    public function testDynamicDirectoryRouteHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/categories/tech';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        
        $this->assertEquals("Category: tech", $output);
    }

//    public function testQueryParametersHandling(): void
//    {
//        $_SERVER['REQUEST_URI'] = '/users/123';
//        $_SERVER['REQUEST_METHOD'] = 'GET';
//        $_SERVER['QUERY_STRING'] = 'format=json';
//
//        ob_start();
//        $this->router->handleRequest();
//        $output = ob_get_clean();
//
//        $this->assertEquals("User ID: 123", $output);
//    }

    public function testNonExistentRouteHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/non-existent';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        
        $this->assertEquals("404 Not Found", $output);
    }

    public function testInvalidMethodHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/about';
        $_SERVER['REQUEST_METHOD'] = 'PUT'; // Method not supported

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        
        $this->assertEquals("404 Not Found", $output);
    }

    public function testDebugRoutesEndpoint(): void
    {
        $_SERVER['REQUEST_URI'] = '/__maplee/routes';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Failed to capture output');
        }
        
        $response = json_decode($output, true);
        if ($response === null) {
            $this->fail('Failed to decode JSON response');
        }

        $this->assertIsArray($response);
        $this->assertArrayHasKey('routes-directory', $response);
        $this->assertArrayHasKey('routes', $response);
        $this->assertArrayHasKey('cache', $response);
        $this->assertContainsEquals('/about', $response['routes']);
        $this->assertContainsEquals('/blog', $response['routes']);
    }

    public function testDebugCacheEndpoint(): void
    {
        $_SERVER['REQUEST_URI'] = '/__maplee/cache';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Failed to capture output');
        }
        
        $response = json_decode($output, true);
        if ($response === null) {
            $this->fail('Failed to decode JSON response');
        }

        $this->assertIsArray($response);
        $this->assertArrayHasKey('enabled', $response);
        $this->assertArrayHasKey('cache_file', $response);
        $this->assertArrayHasKey('last_update', $response);
        $this->assertFalse($response['enabled']); // Cache is disabled in our test setup
    }
} 