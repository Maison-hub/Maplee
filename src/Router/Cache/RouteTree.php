<?php
namespace Maplee\Router\Cache;

class RouteTree
{
    /** @var RouteDirectory[] */
    public array $roots = [];

    public function addRoot(RouteDirectory $root): void
    {
        $this->roots[] = $root;
    }

    public function toArray(): array
    {
        return [
            'roots' => array_map(fn($root) => $root->toArray(), $this->roots)
        ];
    }

}