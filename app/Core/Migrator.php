<?php

declare(strict_types=1);

namespace Xitique\Core;

use PDO;
use Xitique\Services\DatabaseSeeder;

final class Migrator
{
    private const VERSION = '2026_07_02_000001_initial_schema';

    public static function run(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $statement = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = :version LIMIT 1');
        $statement->execute(['version' => self::VERSION]);

        if (!$statement->fetch()) {
            $schema = file_get_contents(BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');

            if ($schema === false) {
                Response::error('Schema da base de dados não encontrado.', 500);
            }

            $pdo->exec($schema);

            $insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
            $insert->execute(['version' => self::VERSION]);
        }

        if (Config::bool('DEMO_SEED', true)) {
            DatabaseSeeder::seed($pdo);
        }
    }
}

