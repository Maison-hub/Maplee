<?php

namespace Maplee\Tests\Unit\Router\Cache;

use Maplee\Router\Cache\RouteCache;
use PHPUnit\Framework\TestCase;

class RouteCacheTest extends TestCase
{
    private string $testCacheFile;
    private string $testRoutesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testCacheFile = sys_get_temp_dir() . '/maplee_test_cache.php';
        $this->testRoutesPath = __DIR__ . '/../../../../tests/fixtures/routes';
        
        // Clean up any existing cache file
        if (file_exists($this->testCacheFile)) {
            unlink($this->testCacheFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testCacheFile)) {
            unlink($this->testCacheFile);
        }
        parent::tearDown();
    }

    public function testCacheCreation(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $this->assertFileExists($this->testCacheFile);
        $cacheInfo = $cache->getCacheInfo();
        
        $this->assertTrue($cacheInfo['enabled']);
        $this->assertEquals($this->testCacheFile, $cacheInfo['cache_file']);
        $this->assertNotNull($cacheInfo['last_update']);
        $this->assertTrue($cacheInfo['file_exists']);
        $this->assertGreaterThan(0, $cacheInfo['file_size']);
    }

    public function testCacheDisabled(): void
    {
        $cache = new RouteCache($this->testCacheFile, false);
        $cache->loadCache($this->testRoutesPath);

        $cacheInfo = $cache->getCacheInfo();
        $this->assertFalse($cacheInfo['enabled']);
    }

//    public function testCacheRebuildOnFileModification(): void
//    {
//        $cache = new RouteCache($this->testCacheFile, true);
//        $cache->loadCache($this->testRoutesPath);
//
//        $firstCacheInfo = $cache->getCacheInfo();
//        $firstRoutes = $firstCacheInfo['cached_routes'];
//
//        // Simulate file modification by touching a route file
//        $testFile = $this->testRoutesPath . '/index.php';
//        touch($testFile);
//
//        // Force cache rebuild by clearing the cache file
//        if (file_exists($this->testCacheFile)) {
//            unlink($this->testCacheFile);
//        }
//
//        // Reload cache
//        $cache->loadCache($this->testRoutesPath);
//        $secondCacheInfo = $cache->getCacheInfo();
//        $secondRoutes = $secondCacheInfo['cached_routes'];
//
//        // Verify that the cache was rebuilt
//        $this->assertNotEquals($firstRoutes, $secondRoutes, 'Cache should be rebuilt when route files are modified');
//    }

    public function testCacheStructure(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $routeCache = $cache->getRouteCache();
        
        $this->assertIsArray($routeCache);
        $this->assertArrayHasKey('files', $routeCache);
        $this->assertArrayHasKey('dynamic_dirs', $routeCache);
    }

    public function testInvalidCacheFile(): void
    {
        $invalidCacheFile = '/invalid/path/cache.php';
        $cache = new RouteCache($invalidCacheFile, true);
        
        // Should not throw exception but create cache in temp directory
        $cache->loadCache($this->testRoutesPath);
        
        $cacheInfo = $cache->getCacheInfo();
        $this->assertTrue($cacheInfo['enabled']);
        $this->assertTrue($cacheInfo['file_exists']);
    }
} 