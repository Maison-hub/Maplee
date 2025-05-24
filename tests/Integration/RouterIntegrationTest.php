<?php

namespace Maplee\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Maplee\Router;

/**
 * @covers \Maplee\Router
 */
class RouterIntegrationTest extends TestCase
{
    private Router $router;
    private array $originalServer;
    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
        
        $this->router = Router::create(null, [
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

    public function testDynamicRouteHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/users/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();

        $this->assertEquals("User Profile: 123", $output);
    }

    public function testMethodSpecificRouteHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/users';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['name' => 'John'];

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();

        $this->assertEquals('Create User: {"name":"John"}', $output);
    }

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

    public function testQueryParametersHandling(): void
    {
        $_SERVER['REQUEST_URI'] = '/users/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = 'format=json';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();

        $this->assertEquals("User Profile: 123", $output);
    }

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
        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('routes-directory', $response);
        $this->assertArrayHasKey('routes', $response);
        $this->assertArrayHasKey('cache', $response);
        $this->assertContains('/about', $response['routes']);
        $this->assertContains('/blog', $response['routes']);
    }

    public function testDebugCacheEndpoint(): void
    {
        $_SERVER['REQUEST_URI'] = '/__maplee/cache';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        $this->router->handleRequest();
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('enabled', $response);
        $this->assertArrayHasKey('cache_file', $response);
        $this->assertArrayHasKey('last_update', $response);
        $this->assertFalse($response['enabled']); // Cache is disabled in our test setup
    }
} 