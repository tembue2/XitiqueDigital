<?php

declare(strict_types=1);

namespace Xitique\Core;

use PDO;

final class Auth
{
    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = self::id();

        if ($id === null) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, name, phone, status, created_at FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        Csrf::boot();
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function requireUserId(): int
    {
        $id = self::id();

        if ($id === null) {
            Response::error('Autenticação necessária.', 401);
        }

        return $id;
    }
}

