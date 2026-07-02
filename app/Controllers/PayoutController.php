<?php

declare(strict_types=1);

namespace Xitique\Controllers;

use Xitique\Core\Auth;
use Xitique\Core\Response;
use Xitique\Services\XitiqueService;

final class PayoutController
{
    public function confirm(int $id): never
    {
        $userId = Auth::requireUserId();
        $result = (new XitiqueService())->confirmPayout($id, $userId);

        Response::json(['ok' => true] + $result);
    }
}

