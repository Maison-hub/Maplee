<?php

namespace Maplee\Tests\Unit\Router\Resolver;

use Maplee\Router\Resolver\RouteResolver;
use PHPUnit\Framework\TestCase;

class RouteResolverTest extends TestCase
{
    private string $testRoutesPath;
    private RouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoutesPath = __DIR__ . '/../../../../tests/fixtures/routes';
        $this->resolver = new RouteResolver($this->testRoutesPath);
    }

    public function testResolveStaticRoute(): void
    {
        $segments = ['about'];
        $method = 'GET';
        
        $result = $this->resolver->resolve($segments, $method);
        
        $this->assertNotNull($result);
        $this->assertStringEndsWith('about.php', $result);
        $this->assertEmpty($this->resolver->getParams());
    }

    public function testResolveDynamicRoute(): void
    {
        $segments = ['users', '123'];
        $method = 'GET';
        
        $result = $this->resolver->resolve($segments, $method);
        
        $this->assertNotNull($result);
        $this->assertStringEndsWith('[id].php', $result);
        $this->assertEquals(['id' => '123'], $this->resolver->getParams());
    }

    public function testResolveWithMethodSpecificFile(): void
    {
        $segments = ['api', 'users'];
        $method = 'POST';
        
        $result = $this->resolver->resolve($segments, $method);
        
        $this->assertNotNull($result);
        $this->assertStringEndsWith('users.post.php', $result);
    }

    public function testResolveIndexFile(): void
    {
        $segments = ['blog'];
        $method = 'GET';
        
        $result = $this->resolver->resolve($segments, $method);
        
        $this->assertNotNull($result);
        $this->assertStringEndsWith('index.php', $result);
    }

    public function testResolveWithCache(): void
    {
        $cache = [
            'files' => [
                'blog' => [
                    'get' => [
                        ['name' => 'index', 'path' => $this->testRoutesPath . '/blog/index.php']
                    ]
                ]
            ]
        ];

        $segments = ['blog'];
        $method = 'GET';
        
        $result = $this->resolver->resolve($segments, $method, $cache);
        
        $this->assertNotNull($result);
        $this->assertEquals($this->testRoutesPath . '/blog/index.php', $result);
    }

    public function testResolveNonExistentRoute(): void
    {
        $segments = ['non-existent'];
        $method = 'GET';
        
        $result = $this->resolver->resolve($segments, $method);
        
        $this->assertNull($result);
        $this->assertEmpty($this->resolver->getParams());
    }

    public function testResolveWithInvalidMethod(): void
    {
        $segments = ['about'];
        $method = 'INVALID';
        
        $result = $this->resolver->resolve($segments, $method);
        
        $this->assertNull($result);
    }

    public function testResolveWithDynamicDirectory(): void
    {
        $segments = ['categories', 'tech'];
        $method = 'GET';
        
        $result = $this->resolver->resolve($segments, $method);
        
        $this->assertNotNull($result);
        $this->assertEquals(['category' => 'tech'], $this->resolver->getParams());
    }
} 