<?php

namespace App\Support;

class NotificationRenderer
{
    public static function render(string $body, array $data): string
    {
        $rendered = $body;
        foreach ($data as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', (string)$value, $rendered);
        }

        return $rendered;
    }
}
