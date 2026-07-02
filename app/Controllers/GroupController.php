<?php

declare(strict_types=1);

namespace Xitique\Controllers;

use Xitique\Core\Auth;
use Xitique\Core\Request;
use Xitique\Core\Response;
use Xitique\Services\XitiqueService;

final class GroupController
{
    public function index(): never
    {
        $userId = Auth::requireUserId();
        $groups = (new XitiqueService())->listGroups($userId);

        Response::json(['ok' => true, 'groups' => $groups]);
    }

    public function store(): never
    {
        $userId = Auth::requireUserId();
        $dashboard = (new XitiqueService())->createGroup($userId, Request::body());

        Response::json(['ok' => true, 'dashboard' => $dashboard], 201);
    }

    public function show(int $id): never
    {
        $userId = Auth::requireUserId();
        $dashboard = (new XitiqueService())->dashboard($id, $userId);

        Response::json(['ok' => true, 'dashboard' => $dashboard]);
    }

    public function addMember(int $id): never
    {
        $userId = Auth::requireUserId();
        $result = (new XitiqueService())->addMember($id, $userId, Request::body());

        Response::json(['ok' => true] + $result, 201);
    }

    public function removeMember(int $groupId, int $memberId): never
    {
        $userId = Auth::requireUserId();
        $result = (new XitiqueService())->removeMember($groupId, $memberId, $userId);

        Response::json(['ok' => true] + $result);
    }

    public function start(int $id): never
    {
        $userId = Auth::requireUserId();
        $result = (new XitiqueService())->startGroup($id, $userId);

        Response::json(['ok' => true] + $result);
    }
}

