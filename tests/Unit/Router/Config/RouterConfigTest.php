<?php

namespace Maplee\Tests\Unit\Router\Config;

use Maplee\Router\Config\RouterConfig;
use PHPUnit\Framework\TestCase;

class RouterConfigTest extends TestCase
{
    private string $testConfigFile;
    private string $originalCachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testConfigFile = sys_get_temp_dir() . '/maplee_test_config.php';
        $this->originalCachePath = RouterConfig::$cachePath;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testConfigFile)) {
            unlink($this->testConfigFile);
        }
        RouterConfig::$cachePath = $this->originalCachePath;
        parent::tearDown();
    }

    public function testDefaultConfig(): void
    {
        $config = RouterConfig::load();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('routesPath', $config);
        $this->assertArrayHasKey('useCache', $config);
        $this->assertArrayHasKey('cacheFile', $config);
        $this->assertTrue($config['useCache']);
        $this->assertEquals(RouterConfig::$cachePath, $config['cacheFile']);
    }

    public function testCustomConfigFile(): void
    {
        $customConfig = [
            'routesPath' => '/custom/routes',
            'useCache' => false,
            'cacheFile' => '/custom/cache.php'
        ];

        file_put_contents($this->testConfigFile, '<?php return ' . var_export($customConfig, true) . ';');
        
        $config = RouterConfig::load($this->testConfigFile);
        
        $this->assertEquals('/custom/routes', $config['routesPath']);
        $this->assertFalse($config['useCache']);
        $this->assertEquals('/custom/cache.php', $config['cacheFile']);
    }

    public function testConfigOverrides(): void
    {
        $overrides = [
            'routesPath' => '/overridden/routes',
            'useCache' => false
        ];

        $config = RouterConfig::load(null, $overrides);
        
        $this->assertEquals('/overridden/routes', $config['routesPath']);
        $this->assertFalse($config['useCache']);
        $this->assertEquals(RouterConfig::$cachePath, $config['cacheFile']);
    }

    public function testInvalidConfigFile(): void
    {
        $config = RouterConfig::load('/invalid/path/config.php');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('routesPath', $config);
        $this->assertArrayHasKey('useCache', $config);
        $this->assertArrayHasKey('cacheFile', $config);
    }

    public function testInvalidConfigContent(): void
    {
        file_put_contents($this->testConfigFile, '<?php return "invalid";');
        
        $config = RouterConfig::load($this->testConfigFile);
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('routesPath', $config);
        $this->assertArrayHasKey('useCache', $config);
        $this->assertArrayHasKey('cacheFile', $config);
    }
} 