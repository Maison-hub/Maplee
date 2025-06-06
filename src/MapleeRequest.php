<?php

namespace Maplee;

class MapleeRequest
{
    /**
     * @param string $uri
     * @param string $method
     * @param array<int|string, mixed> $params
     * @param array<string, mixed> $body
     */
    public function __construct(
        private string $uri,
        private string $method,
        private array $params,
        private array $body
    ) {
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }


    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getBody(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }
}
