<?php
declare(strict_types=1);

namespace Core\Http;

final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->header('Content-Type', 'text/html; charset=utf-8');
    }

    public static function text(string $body, int $status = 200): self
    {
        return (new self($body, $status))->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return (new self(json_encode($data, JSON_THROW_ON_ERROR), $status))
            ->header('Content-Type', 'application/json; charset=utf-8');
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return (new self('', $status))->header('Location', $to);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return self::html($body, 404);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
