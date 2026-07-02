<?php

declare(strict_types=1);

namespace Xitique\Core;

use PDO;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $connection = Config::get('DB_CONNECTION', 'sqlite');

        if ($connection !== 'sqlite') {
            Response::error('Esta versão mínima está configurada para SQLite.', 500);
        }

        $databasePath = Config::path('DB_DATABASE', 'database/database.sqlite');
        $directory = dirname($databasePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        self::$pdo = new PDO('sqlite:' . $databasePath);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        if (Config::bool('AUTO_MIGRATE', true)) {
            Migrator::run(self::$pdo);
        }

        return self::$pdo;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }
}

