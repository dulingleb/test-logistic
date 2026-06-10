<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\NotificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProviderCallbackRequest;
use App\Services\Notifications\ConfirmationOutcome;
use App\Services\Notifications\DeliveryConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ProviderWebhookController extends Controller
{
    public function __construct(
        private readonly DeliveryConfirmationService $service,
    ) {}

    public function __invoke(ProviderCallbackRequest $request, string $provider): JsonResponse
    {
        $v = $request->validated();

        $outcome = $this->service->confirm(
            providerName: $provider,
            providerMessageId: $v['provider_message_id'],
            status: NotificationStatusEnum::from($v['status']),
            reason: $v['reason'] ?? null,
            meta: $v['meta'] ?? [],
            occurredAt: isset($v['occurred_at']) ? Carbon::parse($v['occurred_at']) : null,
        );

        return match ($outcome) {
            ConfirmationOutcome::Applied => response()->json(['outcome' => $outcome->value], 200),
            ConfirmationOutcome::AlreadyApplied => response()->json(['outcome' => $outcome->value], 200),
            ConfirmationOutcome::Conflict => response()->json(['outcome' => $outcome->value], 409),
            ConfirmationOutcome::NotFound => response()->json(['outcome' => $outcome->value], 404),
        };
    }
}
