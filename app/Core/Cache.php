<?php

declare(strict_types=1);

namespace Xitique\Core;

final class Cache
{
    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::put($key, $value, $ttlSeconds);

        return $value;
    }

    public static function get(string $key): mixed
    {
        $path = self::path($key);

        if (!is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (!is_array($payload) || ($payload['expires_at'] ?? 0) < time()) {
            @unlink($path);

            return null;
        }

        return $payload['value'] ?? null;
    }

    public static function put(string $key, mixed $value, int $ttlSeconds): void
    {
        $directory = STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache';

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(self::path($key), json_encode([
            'expires_at' => time() + $ttlSeconds,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function forgetPrefix(string $prefix): void
    {
        $safePrefix = self::safe($prefix);
        $files = glob(STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $safePrefix . '*') ?: [];

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function path(string $key): string
    {
        return STORAGE_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . self::safe($key) . '.json';
    }

    private static function safe(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $key) ?: sha1($key);
    }
}

