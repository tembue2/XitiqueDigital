<?php

declare(strict_types=1);

namespace Xitique\Core;

final class Request
{
    /** @var array<string, mixed>|null */
    private static ?array $json = null;

    /** @return array<string, mixed> */
    public static function body(): array
    {
        if (self::$json !== null) {
            return self::$json;
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        self::$json = is_array($decoded) ? $decoded : $_POST;

        return self::$json;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $body = self::body();

        return $body[$key] ?? $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function number(string $key, float $default = 0): float
    {
        $value = self::input($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }
}

