<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Notifications\NotificationSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [5, 30, 300];
    }

    public function __construct(public readonly string $notificationId) {}

    public function handle(NotificationSender $sender): void
    {
        $sender->send($this->notificationId);
    }

    public function failed(Throwable $e): void
    {
        app(NotificationSender::class)->markFailedFinal(
            $this->notificationId,
            $e->getMessage(),
        );
    }
}
