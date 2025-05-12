<?php

namespace Maplee;

class MapleeRequest
{
    public string $uri;
    public string $method;

    /** @var array<int|string, mixed> */
    public array $params;

    /** @var array<string, mixed> */
    public array $body;

    /**
     * MapleeRequest constructor.
     *
     * @param string $uri The URI of the request.
     * @param string $method The HTTP method of the request (e.g., GET, POST).
     * @param array<int|string, mixed> $params The query parameters of the request.
     * @param array<string, mixed> $body The body parameters of the request.
     */
    public function __construct(string $uri, string $method, array $params = [], array $body = [])
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->params = $params;
        $this->body = $body;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getBody(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }
}
