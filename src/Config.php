<?php

namespace Maplee;

class Config
{
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
