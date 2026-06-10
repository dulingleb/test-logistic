<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTO\NewBulkDataDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulkNotificationRequest;
use App\Services\Notifications\BulkDispatcher;
use Illuminate\Http\JsonResponse;

class BulkNotificationController extends Controller
{
    public function __construct(
        private readonly BulkDispatcher $dispatcher,
    ) {}

    public function store(StoreBulkNotificationRequest $request): JsonResponse
    {
        $result = $this->dispatcher->dispatch(NewBulkDataDTO::fromRequest($request));

        return response()
            ->json($result->body, $result->replayed ? 200 : 202)
            ->header('Idempotent-Replayed', $result->replayed ? 'true' : 'false');
    }
}
