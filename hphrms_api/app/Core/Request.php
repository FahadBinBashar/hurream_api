<?php

namespace App\Core;

class Request
{
    public function __construct(
        protected array $get,
        protected array $post,
        protected array $server,
        protected array $files,
        protected array $cookies,
        protected mixed $body
    ) {
    }

    public static function capture(): self
    {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        $body = json_last_error() === JSON_ERROR_NONE ? $decoded : $input;

        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE, $body);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $queryPos = strpos($uri, '?');
        if ($queryPos !== false) {
            $uri = substr($uri, 0, $queryPos);
        }

        return rtrim($uri, '/') ?: '/';
    }

    public function input(string $key = null, $default = null)
    {
        if ($key === null) {
            if (is_array($this->body)) {
                return $this->body;
            }

            return $this->post;
        }

        if (is_array($this->body) && array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        return $this->post[$key] ?? $default;
    }

    public function all(): array
    {
        if (is_array($this->body)) {
            return $this->body;
        }

        return array_merge($this->post, $this->get);
    }

    public function header(string $key, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? $default;
    }
}
