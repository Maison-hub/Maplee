<?php

namespace Maplee;

class MapleeRequest
{
    public string $uri;
    public string $method;
    public array $params;
    public array $body;

    public function __construct(string $uri, string $method, array $params = [], array $body = [])
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->params = $params;
        $this->body = $body;
    }

    public function getParam(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function getBody(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }
}
