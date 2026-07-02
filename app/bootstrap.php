<?php

declare(strict_types=1);

use Xitique\Core\Config;
use Xitique\Core\Csrf;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Xitique\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Config::load(BASE_PATH . DIRECTORY_SEPARATOR . '.env');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name(Config::get('SESSION_NAME', 'xitique_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

Csrf::boot();

