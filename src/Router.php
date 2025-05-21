<?php

namespace Maplee;

use Maplee\Router\Router as RouterImplementation;

/**
 * @method static RouterImplementation create(?string $configPath = null, array $overrides = [])
 */
class Router extends RouterImplementation
{
    /**
     * Create a new Router instance.
     *
     * @param string|null $configPath Path to the configuration file.
     * @param array<string, mixed> $overrides Array of overrides for the configuration.
     */
    public static function create(?string $configPath = null, array $overrides = []): self
    {
        return new self($configPath, $overrides);
    }
}
