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
        
        $this->router = new Router(null, ['routesPath' => $this->testRoutesPath]);
        
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

        // Route with query parameters
        mkdir($this->testRoutesPath . '/search');
        file_put_contents($this->testRoutesPath . '/search/index.php', '<?php
            return function($request) {
                return "Search: " . ($request->getParams()["q"] ?? "no query");
            };
        ');

        // Route with POST data
        mkdir($this->testRoutesPath . '/contact');
        file_put_contents($this->testRoutesPath . '/contact/index.post.php', '<?php
            return function($request) {
                $body = $request->getBody();
                return "Message from: " . ($body["email"] ?? "unknown");
            };
        ');

        // Route avec paramètres adjacents
        mkdir($this->testRoutesPath . '/articles');
        file_put_contents($this->testRoutesPath . '/articles/[slug][date].php', '<?php
            return function($request) {
                $params = $request->getParams();
                return "Article: " . $params["slug"] . " (Date: " . $params["date"] . ")";
            };
        ');

        // Route avec préfixe statique
        mkdir($this->testRoutesPath . '/users');
        file_put_contents($this->testRoutesPath . '/users/user[uuid].php', '<?php
            return function($request) {
                $params = $request->getParams();
                return "User UUID: " . $params["uuid"];
            };
        ');

        // Route avec multiples paramètres sans séparateur
        mkdir($this->testRoutesPath . '/products');
        file_put_contents($this->testRoutesPath . '/products/item[category][id][variant].php', '<?php
            return function($request) {
                $params = $request->getParams();
                return sprintf(
                    "Product - Category: %s, ID: %s, Variant: %s",
                    $params["category"],
                    $params["id"],
                    $params["variant"]
                );
            };
        ');

        // Route avec méthode spécifique et paramètres multiples
        mkdir($this->testRoutesPath . '/api');
        file_put_contents($this->testRoutesPath . '/api/[type]-[id].post.php', '<?php
            return function($request) {
                $params = $request->getParams();
                return "API POST - Type: " . $params["type"] . ", ID: " . $params["id"];
            };
        ');

        // Route avec trois paramètres
        mkdir($this->testRoutesPath . '/products');
        file_put_contents($this->testRoutesPath . '/products/[category]-[brand]-[id].php', '<?php
            return function($request) {
                $params = $request->getParams();
                return sprintf(
                    "Product - Category: %s, Brand: %s, ID: %s",
                    $params["category"],
                    $params["brand"],
                    $params["id"]
                );
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

    public function testAdjacentParameters(): void
    {
        $_SERVER['REQUEST_URI'] = '/articles/mon-article2024-03-20';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Article: mon-article (Date: 2024-03-20)', $response);
    }

    public function testStaticPrefixWithParameter(): void
    {
        $_SERVER['REQUEST_URI'] = '/users/user123e4567-e89b-12d3-a456-426614174000';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('User UUID: 123e4567-e89b-12d3-a456-426614174000', $response);
    }

    public function testMultipleParametersWithoutSeparator(): void
    {
        $_SERVER['REQUEST_URI'] = '/products/itemelectronics123blue';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Product - Category: electronics, ID: 123, Variant: blue', $response);
    }

    public function testMethodSpecificWithMultipleParams(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/user-456';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('API POST - Type: user, ID: 456', $response);
    }

    public function testThreeParametersInFileName(): void
    {
        $_SERVER['REQUEST_URI'] = '/products/electronics-samsung-789';
        ob_start();
        $this->router->handleRequest();
        $response = ob_get_clean();
        
        $this->assertEquals('Product - Category: electronics, Brand: samsung, ID: 789', $response);
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