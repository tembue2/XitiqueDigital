<?php

declare(strict_types=1);

namespace Xitique\Core;

final class Response
{
    /** @param array<string, mixed> $payload */
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @param array<string, mixed> $details */
    public static function error(string $message, int $status = 400, array $details = []): never
    {
        self::json([
            'ok' => false,
            'message' => $message,
            'details' => $details,
        ], $status);
    }
}

