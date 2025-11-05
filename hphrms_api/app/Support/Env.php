<?php

namespace App\Support;

class Env
{
    protected static array $cache = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"' ");
            self::$cache[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        self::$cache[$key] = $value;

        return $value;
    }
}
