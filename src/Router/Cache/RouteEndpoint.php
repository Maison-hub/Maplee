<?php
namespace Maplee\Router\Cache;

class RouteEndpoint
{
    public string $name;
    public string $path;
    public string $method;
    public ?string $param;

    public function __construct(string $name, string $path, string $method = 'get', ?string $param = null)
    {
        $this->name = $name;
        $this->path = $path;
        $this->method = $method;
        $this->param = $param;
    }

    public function toArray(): array
    {
        $result = [];
        if ($this->param !== null) {
            $result[$this->name]['param'] = $this->param;
        }

        $result[$this->name]['path'] = $this->path;
        $result[$this->name]['method'] = $this->method;

        return $result;
    }
}