<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatusEnum;
use App\Services\Notifications\DeliveryConfirmationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ConfirmDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function __construct(
        public readonly string $providerName,
        public readonly string $providerMessageId,
        public readonly string $status,
        public readonly ?string $reason = null,
    ) {}

    public function handle(DeliveryConfirmationService $service): void
    {
        $service->confirm(
            providerName: $this->providerName,
            providerMessageId: $this->providerMessageId,
            status: NotificationStatusEnum::from($this->status),
            reason: $this->reason,
            meta: ['simulated' => true],
        );
    }
}
