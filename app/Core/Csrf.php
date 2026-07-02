<?php

declare(strict_types=1);

namespace Xitique\Core;

final class Csrf
{
    public static function boot(): void
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
    }

    public static function token(): string
    {
        return (string) ($_SESSION['_csrf'] ?? '');
    }

    public static function verify(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $given = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!hash_equals(self::token(), $given)) {
            Response::error('Sessão expirada. Recarregue a página e tente novamente.', 419);
        }
    }
}

