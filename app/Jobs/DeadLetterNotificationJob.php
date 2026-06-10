<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Marker job parked on the DLQ after SendNotificationJob has exhausted
 * its tries. Carries enough context to replay or audit by hand. No
 * worker should consume this queue in production; handle() exists only
 * so the payload stays valid if someone replays it onto a live queue.
 */
final class DeadLetterNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  array<string,mixed>  $context
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly string $reason,
        public readonly int $attempts,
        public readonly array $context = [],
    ) {}

    public function handle(): void
    {
        Log::warning('notification dead-lettered (replayed)', [
            'notification_id' => $this->notificationId,
            'reason' => $this->reason,
            'attempts' => $this->attempts,
            'context' => $this->context,
        ]);
    }
}
