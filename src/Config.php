<?php

namespace Maplee;

class Config
{
    /**
     * Load the configuration file.
     *
     * @param string|null $configPath Path to the configuration file.
     * @return array<string, mixed> The loaded configuration.
     */
    public static function load(?string $configPath = null): array
    {
        $defaultPath = __DIR__ . '/../config/maplee.php';
        $file = $configPath ?? $defaultPath;

        if (file_exists($file)) {
            $config = include $file;

            if (is_array($config)) {
                return $config;
            }
        }

        return [];
    }
}
