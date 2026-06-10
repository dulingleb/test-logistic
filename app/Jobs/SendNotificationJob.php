<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Total attempts including retries.
     */
    public int $tries = 3;

    /**
     * Backoff between retries in seconds: 5s, 30s, 5m.
     *
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [5, 30, 300];
    }

    public function __construct(public readonly string $notificationId) {}

    public function handle(): void
    {
        // Stage 4: resolve provider for $notification->channel,
        //          send via provider stub, update status, log event.
    }
}
