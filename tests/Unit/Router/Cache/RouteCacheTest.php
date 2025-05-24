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
        $firstUpdate = $firstCacheInfo['last_update'];

        // Simulate file modification by writing to a route file
        $testFile = $this->testRoutesPath . '/index.php';
        file_put_contents($testFile, '<?php echo "Modified";', FILE_APPEND);
        
        // Force a small delay to ensure filesystem updates
        usleep(100000); // 100ms delay
        
        $cache->loadCache($this->testRoutesPath);
        $secondCacheInfo = $cache->getCacheInfo();

        // Clean up the test file
        file_put_contents($testFile, '<?php echo "Test";');

        $this->assertNotEquals($firstUpdate, $secondCacheInfo['last_update'], 
            'Cache should be rebuilt when route files are modified');
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
        $invalidCacheFile = '/invalid/path/cache.php';
        $cache = new RouteCache($invalidCacheFile, true);
        
        // Should not throw exception but create cache in temp directory
        $cache->loadCache($this->testRoutesPath);
        
        $cacheInfo = $cache->getCacheInfo();
        $this->assertTrue($cacheInfo['enabled']);
        $this->assertTrue($cacheInfo['file_exists']);
    }
} 