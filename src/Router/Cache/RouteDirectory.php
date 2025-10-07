<?php
namespace Maplee\Router\Cache;

class RouteDirectory
{
    public string $name;
    public ?string $param = null; // null si statique, sinon nom du param dynamique
    /** @var RouteEndpoint[] */
    public array $endpoints = [];
    /** @var RouteDirectory[] */
    public array $children = [];

    public function __construct(string $name, ?string $param = null)
    {
        $this->name = $name;
        $this->param = $param;
    }

    public function addEndpoint(RouteEndpoint $endpoint): void
    {
        $this->endpoints[] = $endpoint;
    }

    public function addChild(RouteDirectory $child): void
    {
        $this->children[] = $child;
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->param) {
            $result['param'] = $this->param;
        }
        if ($this->endpoints !== []) {
            $result['endpoints'] = array_map(fn($ep) => $ep->toArray(), $this->endpoints);
        }

        foreach ($this->children as $child) {
            $result += $child->toArray();
        }

        return [
            $this->name . "/" => $result
        ];
    }
}