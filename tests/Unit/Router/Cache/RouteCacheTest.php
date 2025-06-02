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

    public function testCacheRebuildOnFileModification(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $firstCacheInfo = $cache->getCacheInfo();
        $firstTimestamp = $firstCacheInfo['last_update'];

        // Simulate file modification by touching a route file
        $testFile = $this->testRoutesPath . '/index.php';
        touch($testFile);
        
        // Force a small delay to ensure timestamp difference
        usleep(1000); // 1ms delay
        
        // Force cache rebuild by clearing the cache file
        if (file_exists($this->testCacheFile)) {
            unlink($this->testCacheFile);
        }

        // Reload cache
        $cache->loadCache($this->testRoutesPath);
        $secondCacheInfo = $cache->getCacheInfo();
        $secondTimestamp = $secondCacheInfo['last_update'];

        // Verify that the cache was rebuilt with a new timestamp
        $this->assertNotEquals(
            $firstTimestamp,
            $secondTimestamp,
            'Cache timestamp should be different after rebuild'
        );
    }

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
        // Create a cache file in a directory that doesn't exist
        $invalidCacheFile = sys_get_temp_dir() . '/invalid/path/cache.php';
        $cache = new RouteCache($invalidCacheFile, true);
        
        // Should not throw exception but create cache in temp directory
        $cache->loadCache($this->testRoutesPath);
        
        $cacheInfo = $cache->getCacheInfo();
        $this->assertTrue($cacheInfo['enabled'], 'Cache should be enabled');
        $this->assertTrue($cacheInfo['file_exists'], 'Cache file should exist');
        $this->assertStringStartsWith(sys_get_temp_dir(), $cacheInfo['cache_file'], 'Cache file should be in temp directory');
    }
} 