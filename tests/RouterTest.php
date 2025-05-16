<?php

namespace Maplee\Tests;

use PHPUnit\Framework\TestCase;
use Maplee\Router;
use Maplee\Config;

/**
 * @covers \Maplee\Router
 */
class RouterTest extends TestCase
{
    private Router $router;
    private string $testRoutesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoutesPath = __DIR__ . '/fixtures/routes';
        if (!is_dir($this->testRoutesPath)) {
            mkdir($this->testRoutesPath, 0777, true);
        }
        
        $this->router = new Router(null, ['routesPath' => $this->testRoutesPath]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testRoutesPath);
        parent::tearDown();
    }

    public function testResolveFileFindsIndexFile(): void
    {
        // Create a test route file
        $indexFile = $this->testRoutesPath . '/index.php';
        file_put_contents($indexFile, '<?php return function($request) { return "Hello World"; };');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $resolveFileMethod = new \ReflectionMethod(Router::class, 'resolveFile');
        $resolveFileMethod->setAccessible(true);
        
        $result = $resolveFileMethod->invoke($this->router, []);
        
        $this->assertEquals($indexFile, $result);
    }

    public function testResolveFileFindsMethodSpecificFile(): void
    {
        // Create a test route file
        $getFile = $this->testRoutesPath . '/test.get.php';
        file_put_contents($getFile, '<?php return function($request) { return "GET response"; };');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $resolveFileMethod = new \ReflectionMethod(Router::class, 'resolveFile');
        $resolveFileMethod->setAccessible(true);
        
        $result = $resolveFileMethod->invoke($this->router, ['test']);
        
        $this->assertEquals($getFile, $result);
    }

    public function testResolveFileWithDynamicParameter(): void
    {
        // Create a dynamic parameter route
        $paramDir = $this->testRoutesPath . '/[id]';
        mkdir($paramDir);
        $paramFile = $paramDir . '/index.php';
        file_put_contents($paramFile, '<?php return function($request) { return "Dynamic param"; };');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $resolveFileMethod = new \ReflectionMethod(Router::class, 'resolveFile');
        $resolveFileMethod->setAccessible(true);
        
        $result = $resolveFileMethod->invoke($this->router, ['123']);
        
        $this->assertEquals($paramFile, $result);
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