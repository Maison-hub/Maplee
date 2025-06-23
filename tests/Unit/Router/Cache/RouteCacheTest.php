<?php

namespace Maplee\Tests\Unit\Router\Cache;

use Maplee\Router\Cache\RouteCache;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class RouteCacheTest extends TestCase
{
    private string $testCacheFile;
    private string $testRoutesPath;
    private string $testRoutesDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer un répertoire temporaire pour les tests
        $this->testRoutesDir = sys_get_temp_dir() . '/maplee_test_routes_' . uniqid();
        mkdir($this->testRoutesDir, 0777, true);
        
        // Créer quelques fichiers de test
        $this->createTestRouteFiles();
        
        $this->testCacheFile = sys_get_temp_dir() . '/maplee_test_cache_' . uniqid() . '.php';
        $this->testRoutesPath = $this->testRoutesDir;
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers de test
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    private function createTestRouteFiles(): void
    {
        // Créer une structure de routes de test
        $routes = [
            'index.php' => '<?php return "index";',
            'about.php' => '<?php return "about";',
            'users/[id].php' => '<?php return "user";',
            'posts/index.php' => '<?php return "posts index";',
            'posts/[slug].php' => '<?php return "post";',
            'admin/index.php' => '<?php return "admin";',
            'api/users.get.php' => '<?php return "api users get";',
            'api/users.post.php' => '<?php return "api users post";',
        ];

        foreach ($routes as $path => $content) {
            $fullPath = $this->testRoutesDir . '/' . $path;
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($fullPath, $content);
        }
    }

    private function cleanupTestFiles(): void
    {
        if (file_exists($this->testCacheFile)) {
            unlink($this->testCacheFile);
        }

        if (is_dir($this->testRoutesDir)) {
            $this->removeDirectory($this->testRoutesDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    #[Test]
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

    #[Test]
    public function testCacheDisabled(): void
    {
        $cache = new RouteCache($this->testCacheFile, false);
        $cache->loadCache($this->testRoutesPath);

        $cacheInfo = $cache->getCacheInfo();
        $this->assertFalse($cacheInfo['enabled']);
        //$this->assertFalse($cacheInfo['file_exists']);
    }

    #[Test]
    public function testCacheStructure(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $routeCache = $cache->getCacheInfo();

        $this->assertIsArray($routeCache);
        $this->assertArrayHasKey('cached_routes', $routeCache);
        $this->assertArrayHasKey('files', $routeCache['cached_routes']);
        $this->assertArrayHasKey('routes_count', $routeCache);
        $this->assertArrayHasKey('dynamic_dirs', $routeCache['routes_count']);

        // Vérifier la structure des routes statiques
        $this->assertArrayHasKey('', $routeCache['cached_routes']['files']); // Routes racine
        $this->assertArrayHasKey('posts', $routeCache['cached_routes']['files']); // Routes /posts
        $this->assertArrayHasKey('api', $routeCache['cached_routes']['files']); // Routes /api
    }

    #[Test]
    public function testCacheRebuildOnFileModification(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $firstCacheInfo = $cache->getCacheInfo();
        $firstLastUpdate = $firstCacheInfo['last_update'];
        
        // Attendre une seconde pour s'assurer que le timestamp sera différent
        sleep(1);

        // Modifier un fichier de route
        $testFile = $this->testRoutesPath . '/index.php';
        touch($testFile);
        
        // Recharger le cache
        $cache->loadCache($this->testRoutesPath);
        $secondCacheInfo = $cache->getCacheInfo();
        
        $this->assertGreaterThan(
            strtotime($firstLastUpdate),
            strtotime($secondCacheInfo['last_update']),
            'Cache should be rebuilt when route files are modified'
        );
    }

    #[Test]
    public function testInvalidCacheFile(): void
    {
        $invalidCacheFile = '/invalid/path/cache.php';
        $cache = new RouteCache($invalidCacheFile, true);
        
        // Ne devrait pas lever d'exception mais créer le cache dans le répertoire temporaire
        $cache->loadCache($this->testRoutesPath);
        
        $cacheInfo = $cache->getCacheInfo();
        $this->assertTrue($cacheInfo['enabled']);
        $this->assertTrue($cacheInfo['file_exists']);
        $this->assertGreaterThan(0, $cacheInfo['file_size']);
    }

    #[Test]
    public function testCacheWithDynamicRoutes(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $routeCache = $cache->getRouteCache();

        // Vérifier la route utilisateur dynamique
        $this->assertArrayHasKey('users', $routeCache['files']);
        $this->assertArrayHasKey('get', $routeCache['files']['users']);
        
        // Vérifier la route post dynamique
        $this->assertArrayHasKey('posts', $routeCache['files']);
        $this->assertArrayHasKey('get', $routeCache['files']['posts']);
    }

    #[Test]
    public function testCacheWithHttpMethods(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $routeCache = $cache->getCacheInfo();

        // Check if HTTP methods are correctly cached
        $this->assertArrayHasKey('api', $routeCache['cached_routes']['files']);
        $this->assertArrayHasKey('get', $routeCache['cached_routes']['files']['api'] ?? []);

        $routeNames = array_map(
            fn ($route) => $route['name'],
            $routeCache['cached_routes']['files']['api']['get']
        );

        $this->assertContains('users.get', $routeNames);
        $this->assertContains('users.post', $routeNames);
    }

    #[Test]
    public function testCacheRoutesCount(): void
    {
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($this->testRoutesPath);

        $cacheInfo = $cache->getCacheInfo();
        
        $this->assertArrayHasKey('routes_count', $cacheInfo);
        $this->assertArrayHasKey('dynamic_dirs', $cacheInfo['routes_count']);
        $this->assertArrayHasKey('files', $cacheInfo['routes_count']);
        
        // Vérifier que le nombre de routes est correct
        $this->assertGreaterThan(0, $cacheInfo['routes_count']['files']);
    }

    #[Test]
    public function testCacheWithEmptyRoutesDirectory(): void
    {
        // Créer un répertoire vide
        $emptyDir = sys_get_temp_dir() . '/maplee_test_empty_' . uniqid();
        mkdir($emptyDir, 0777, true);
        
        $cache = new RouteCache($this->testCacheFile, true);
        $cache->loadCache($emptyDir);

        $routeCache = $cache->getRouteCache();

        $this->assertIsArray($routeCache);
        $this->assertEmpty($routeCache['cached_routes']);
        $this->assertEquals(0, $routeCache['routes_count']['dynamic_dirs']);
        $this->assertEquals(0, $routeCache['routes_count']['files']);

        // Clean up
        rmdir($emptyDir);
    }

    #[Test]
    public function testCacheWithNonExistentRoutesDirectory(): void
    {
        $nonExistentDir = '/non/existent/path';
        $cache = new RouteCache($this->testCacheFile, true);

        $cache->loadCache($nonExistentDir);

        $routeCache = $cache->getCacheInfo();

        $this->assertIsArray($routeCache);
        $this->assertArrayHasKey('cached_routes', $routeCache);
        $this->assertArrayHasKey('routes_count', $routeCache);
        $this->assertArrayHasKey('dynamic_dirs', $routeCache['routes_count']);
        $this->assertArrayHasKey('files', $routeCache['routes_count']);

        $this->assertEmpty($routeCache['cached_routes']['files']);
        $this->assertEquals(0, $routeCache['routes_count']['dynamic_dirs']);
        $this->assertEquals(0, $routeCache['routes_count']['files']);
    }
}
