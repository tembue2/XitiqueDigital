<?php

declare(strict_types=1);

namespace Xitique\Controllers;

use Xitique\Core\Auth;
use Xitique\Core\Request;
use Xitique\Core\Response;
use Xitique\Services\XitiqueService;

final class AuthController
{
    public function register(): never
    {
        $service = new XitiqueService();
        $user = $service->registerUser(
            Request::string('name'),
            Request::string('phone'),
            Request::string('password')
        );

        Auth::login((int) $user['id']);

        unset($user['password_hash']);

        Response::json(['ok' => true, 'user' => $user], 201);
    }

    public function login(): never
    {
        $service = new XitiqueService();
        $user = $service->authenticate(Request::string('phone'), Request::string('password'));

        Auth::login((int) $user['id']);

        Response::json(['ok' => true, 'user' => $user]);
    }

    public function logout(): never
    {
        Auth::logout();

        Response::json(['ok' => true]);
    }

    public function me(): never
    {
        $user = Auth::user();

        if (!$user) {
            Response::json(['ok' => true, 'user' => null]);
        }

        $groups = (new XitiqueService())->listGroups((int) $user['id']);

        Response::json(['ok' => true, 'user' => $user, 'groups' => $groups]);
    }
}

