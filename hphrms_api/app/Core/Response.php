<?php

namespace App\Core;

class Response
{
    public function __construct(
        protected array $payload,
        protected int $status = 200,
        protected array $headers = []
    ) {
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE);
    }
}
