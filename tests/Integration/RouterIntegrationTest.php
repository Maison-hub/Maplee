<?php

namespace Maplee\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Maplee\Router;

/**
 * @covers \Maplee\Router
 */
class RouterIntegrationTest extends TestCase
{
    private string $testRoutesPath;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoutesPath = __DIR__ . '/../fixtures/test-routes';
        $this->createTestRoutes();
        
        $this->router = new Router(null, overrides: ['routesPath' => $this->testRoutesPath, 'useCache' => false]);
        
        // Simuler les variables serveur
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = '';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testRoutesPath);
        parent::tearDown();
    }

    private function createTestRoutes(): void
    {
        // Créer la structure de test
        if (!is_dir($this->testRoutesPath)) {
            mkdir($this->testRoutesPath, 0777, true);
        }

        // Route racine
        file_put_contents($this->testRoutesPath . '/index.php', '<?php
            return function($request) {
                return "Homepage";
            };
        ');

        // Route simple GET
        mkdir($this->testRoutesPath . '/about');
        file_put_contents($this->testRoutesPath . '/about/index.get.php', '<?php
            return function($request) {
                return "About page";
            };
        ');

        // Route POST
        file_put_contents($this->testRoutesPath . '/about/index.post.php', '<?php
            return function($request) {
                return "About page POST";
            };
        ');

        // Route avec paramètre dynamique
        mkdir($this->testRoutesPath . '/blog');
        mkdir($this->testRoutesPath . '/blog/[category]');
        file_put_contents($this->testRoutesPath . '/blog/[category]/index.php', '<?php
            return function($request) {
                return "Category: " . $request->getParams()["category"];
            };
        ');

        // Route avec plusieurs paramètres
        mkdir($this->testRoutesPath . '/blog/[category]/[id]');
        file_put_contents($this->testRoutesPath . '/blog/[category]/[id]/index.php', '<?php
            return function($request) {
                $params = $request->getParams();
                return "Category: " . $params["category"] . ", ID: " . $params["id"];
            };
        ');

        // Route avec query parameters
        mkdir($this->testRoutesPath . '/search');
        file_put_contents($this->testRoutesPath . '/search/index.php', '<?php
            return function($request) {
                return "Search: " . ($request->getParams()["q"] ?? "no query");
            };
        ');

        // Route avec POST data
        mkdir($this->testRoutesPath . '/contact');
        file_put_contents($this->testRoutesPath . '/contact/index.post.php', '<?php
            return function($request) {
                $body = $request->getBody();
                return "Message from: " . ($body["email"] ?? "unknown");
            };
        ');
    }

    public function testHomePage(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Homepage', $response);
    }

    public function testSimpleGetRoute(): void
    {
        $_SERVER['REQUEST_URI'] = '/about';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('About page', $response);
    }

    public function testPostRoute(): void
    {
        $_SERVER['REQUEST_URI'] = '/about';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('About page POST', $response);
    }

    public function testDynamicParameter(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog/tech';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Category: tech', $response);
    }

    public function testMultipleDynamicParameters(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog/tech/123';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Category: tech, ID: 123', $response);
    }

    public function testQueryParameters(): void
    {
        $_SERVER['REQUEST_URI'] = '/search';
        $_SERVER['QUERY_STRING'] = 'q=php';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Search: php', $response);
    }

    public function testPostData(): void
    {
        $_SERVER['REQUEST_URI'] = '/contact';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'test@example.com';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Message from: test@example.com', $response);
        $_POST = []; // Clean up
    }

    public function test404NotFound(): void
    {
        $_SERVER['REQUEST_URI'] = '/not-exists';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('404 Not Found', $response);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            is_dir($filePath) ? $this->removeDirectory($filePath) : unlink($filePath);
        }
        rmdir($path);
    }
} 