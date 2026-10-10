<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use Illuminate\Http\JsonResponse;

final class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
        ]);
    }
}
