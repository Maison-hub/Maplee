<?php

namespace Maplee\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    private int $statusCode = 200;
    private string $reasonPhrase = '';
    private array $headers = [];
    private string $body = '';
    private string $protocolVersion = '1.1';

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): self
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): self
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $new;
    }

    public function withAddedHeader(string $name, $value): self
    {
        $new = clone $this;
        $name = strtolower($name);
        $new->headers[$name] = array_merge($new->headers[$name] ?? [], is_array($value) ? $value : [$value]);
        return $new;
    }

    public function withoutHeader(string $name): self
    {
        $new = clone $this;
        unset($new->headers[strtolower($name)]);
        return $new;
    }

    public function getBody(): StreamInterface
    {
        // Pour l'instant, on retourne une implémentation très basique
        return new class($this->body) implements StreamInterface {
            private string $content;

            public function __construct(string $content)
            {
                $this->content = $content;
            }

            public function __toString(): string
            {
                return $this->content;
            }

            public function close(): void {}
            public function detach() { return null; }
            public function getSize(): ?int { return strlen($this->content); }
            public function tell(): int { return 0; }
            public function eof(): bool { return true; }
            public function isSeekable(): bool { return false; }
            public function seek(int $offset, int $whence = SEEK_SET): void {}
            public function rewind(): void {}
            public function isWritable(): bool { return false; }
            public function write(string $string): int { return 0; }
            public function isReadable(): bool { return true; }
            public function read(int $length): string { return ''; }
            public function getContents(): string { return $this->content; }
            public function getMetadata(?string $key = null) { return null; }
        };
    }

    public function withBody(StreamInterface $body): self
    {
        $new = clone $this;
        $new->body = (string) $body;
        return $new;
    }

    public function withContent(string $content): self
    {
        $new = clone $this;
        $new->body = $content;
        return $new;
    }

    public function send(): void
    {
        // Envoyer le code de statut
        http_response_code($this->statusCode);

        // Envoyer les headers
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value");
            }
        }

        // Envoyer le corps de la réponse
        echo $this->body;
    }
} 