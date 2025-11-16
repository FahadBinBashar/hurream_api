<?php

namespace App\Core;

use Illuminate\Http\JsonResponse;

class Response extends JsonResponse
{
    public function __construct(array $payload, int $status = 200, array $headers = [])
    {
        parent::__construct($payload, $status, $headers);
    }
}
