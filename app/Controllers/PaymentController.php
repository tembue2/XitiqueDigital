<?php

declare(strict_types=1);

namespace Xitique\Controllers;

use Xitique\Core\Auth;
use Xitique\Core\Request;
use Xitique\Core\Response;
use Xitique\Services\XitiqueService;

final class PaymentController
{
    public function store(int $groupId): never
    {
        $userId = Auth::requireUserId();
        $result = (new XitiqueService())->registerPayment($groupId, $userId, Request::body());

        Response::json(['ok' => true] + $result);
    }
}

