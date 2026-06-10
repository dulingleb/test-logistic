<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\PermanentProviderException;
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

    public function __construct(public readonly string $notificationId) {}

    public function tries(): int
    {
        return (int) config('notifications.send_job.tries', 5);
    }

    /**
     * Per-attempt delay in seconds with jitter.
     *
     * @return array<int,int>
     */
    public function backoff(): array
    {
        /** @var array<int,int> $base */
        $base = (array) config('notifications.send_job.backoff', [1, 5, 30, 300]);
        $jitter = (float) config('notifications.send_job.jitter', 0.25);

        return array_map(function (int $seconds) use ($jitter): int {
            if ($jitter <= 0) {
                return $seconds;
            }
            $delta = $seconds * $jitter;
            $low = (int) floor($seconds - $delta);
            $high = (int) ceil($seconds + $delta);

            return max(1, mt_rand(max(1, $low), max(1, $high)));
        }, $base);
    }

    public function handle(NotificationSender $sender): void
    {
        try {
            $sender->send($this->notificationId);
        } catch (PermanentProviderException $e) {
            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        $attempts = $this->attempts();

        app(NotificationSender::class)->markFailedFinal(
            $this->notificationId,
            $e->getMessage(),
        );

        DeadLetterNotificationJob::dispatch(
            $this->notificationId,
            $e->getMessage(),
            $attempts,
            ['exception' => $e::class],
        )->onQueue((string) config('notifications.dead_letter_queue', 'notifications.dlq'));
    }
}
