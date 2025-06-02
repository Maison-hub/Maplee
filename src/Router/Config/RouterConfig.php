<?php

namespace Maplee\Router\Config;

class RouterConfig
{
    public static string $cachePath = '/tmp/maplee_route_cache.php';

    /**
     * @param string|null $configPath Path to the configuration file.
     * @param array<string, mixed> $overrides Array of overrides for the configuration.
     * @return array<string, mixed>
     */
    public static function load(?string $configPath = null, array $overrides = []): array
    {
        $defaultConfig = [
            'routesPath' => __DIR__ . '/../../../routes',
            'useCache' => true,
            'cacheFile' => self::$cachePath
        ];

        $fileConfig = [];
        if ($configPath !== null && file_exists($configPath)) {
            $fileConfig = include $configPath;
            if (!is_array($fileConfig)) {
                $fileConfig = [];
            }
        }

        return array_merge($defaultConfig, $fileConfig, $overrides);
    }
}
