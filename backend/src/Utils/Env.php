<?php

declare(strict_types=1);

namespace App\Utils;

class Env
{
    private static array $cache = [];

    public static function load(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $val = trim($parts[1]);

            if (preg_match('/^(["\'])(.*)\1$/', $val, $matches)) {
                $val = $matches[2];
            }

            self::$cache[$key] = $val;
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::castValue(self::$cache[$key]);
        }

        $val = getenv($key);
        if ($val !== false) {
            return self::castValue($val);
        }

        if (isset($_ENV[$key])) {
            return self::castValue($_ENV[$key]);
        }

        return $default;
    }

    private static function castValue(string $val): mixed
    {
        $lower = strtolower($val);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }
        if (is_numeric($val)) {
            return str_contains($val, '.') ? (float) $val : (int) $val;
        }

        return $val;
    }
}
