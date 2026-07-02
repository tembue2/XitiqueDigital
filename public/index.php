<?php

declare(strict_types=1);

use Xitique\Controllers\AuthController;
use Xitique\Controllers\GroupController;
use Xitique\Controllers\PaymentController;
use Xitique\Controllers\PayoutController;
use Xitique\Core\Config;
use Xitique\Core\Csrf;
use Xitique\Core\Database;
use Xitique\Core\HttpException;
use Xitique\Core\Response;
use Xitique\Core\Router;

if (PHP_SAPI === 'cli-server') {
    $requested = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $requested;

    if (is_file($file) && basename($file) !== 'index.php') {
        return false;
    }
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = static function (): string {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($uri, $scriptDir)) {
        $uri = substr($uri, strlen($scriptDir)) ?: '/';
    }

    $uri = '/' . trim($uri, '/');

    return $uri === '/' ? '/' : $uri;
};

$requestPath = $path();

if (str_starts_with($requestPath, '/api')) {
    try {
        Csrf::verify();

        $router = new Router();
        $router->get('/api/health', static function (): never {
            Database::connection();
            Response::json(['ok' => true, 'service' => 'Xitique Digital', 'time' => date(DATE_ATOM)]);
        });
        $router->post('/api/auth/register', [AuthController::class, 'register']);
        $router->post('/api/auth/login', [AuthController::class, 'login']);
        $router->post('/api/auth/logout', [AuthController::class, 'logout']);
        $router->get('/api/me', [AuthController::class, 'me']);
        $router->get('/api/groups', [GroupController::class, 'index']);
        $router->post('/api/groups', [GroupController::class, 'store']);
        $router->get('/api/groups/{id}', [GroupController::class, 'show']);
        $router->post('/api/groups/{id}/members', [GroupController::class, 'addMember']);
        $router->delete('/api/groups/{groupId}/members/{memberId}', [GroupController::class, 'removeMember']);
        $router->post('/api/groups/{id}/start', [GroupController::class, 'start']);
        $router->post('/api/groups/{groupId}/payments', [PaymentController::class, 'store']);
        $router->post('/api/payouts/{id}/confirm', [PayoutController::class, 'confirm']);

        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $requestPath);
    } catch (HttpException $exception) {
        Response::error($exception->getMessage(), $exception->status(), $exception->details());
    } catch (Throwable $throwable) {
        $debug = Config::bool('APP_DEBUG', false);
        Response::error('Erro interno do servidor.', 500, $debug ? ['exception' => $throwable->getMessage()] : []);
    }
}

$appName = htmlspecialchars(Config::get('APP_NAME', 'Xitique Digital') ?? 'Xitique Digital', ENT_QUOTES, 'UTF-8');
$csrf = htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <title><?= $appName ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <div id="app" class="app-shell" data-app-name="<?= $appName ?>">
        <main class="loading-screen">
            <p>A carregar o Xitique Digital...</p>
        </main>
    </div>
    <script type="module" src="assets/app.js"></script>
</body>
</html>
