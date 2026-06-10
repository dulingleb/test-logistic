<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationStatusEnum;
use App\Exceptions\PermanentProviderException;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Services\Notifications\Providers\ProviderRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotificationSender
{
    public function __construct(
        private readonly ProviderRegistry $providers,
    ) {}

    /**
     * @throws Throwable on transient failure — re-thrown so the queue worker retries.
     */
    public function send(string $notificationId): void
    {
        $deferred = null;

        DB::transaction(function () use ($notificationId, &$deferred): void {
            $notification = Notification::with('bulk')
                ->lockForUpdate()
                ->find($notificationId);

            if ($notification === null || $notification->status !== NotificationStatusEnum::Queued) {
                return;
            }

            $notification->attempts++;

            try {
                $result = $this->providers
                    ->for($notification->channel)
                    ->send($notification, (string) $notification->bulk?->message);
            } catch (PermanentProviderException $e) {
                $this->finalizeFailure($notification, $e->getMessage());
                $deferred = $e;

                return;
            } catch (Throwable $e) {
                $notification->last_error = mb_substr($e->getMessage(), 0, 65535);
                $notification->save();

                $this->logEvent($notification, NotificationStatusEnum::Queued, [
                    'phase' => 'retry',
                    'attempt' => $notification->attempts,
                    'error' => $e->getMessage(),
                ]);

                Log::channel('notifications')->warning('notification.retry', [
                    'event' => 'notification.retry',
                    'notification_id' => $notification->id,
                    'channel' => $notification->channel->value,
                    'priority' => $notification->priority->value,
                    'attempt' => $notification->attempts,
                    'error' => $e->getMessage(),
                ]);

                $deferred = $e;

                return;
            }

            $now = Carbon::now();
            $notification->status = NotificationStatusEnum::Sent;
            $notification->sent_at = $now;
            $notification->provider_message_id = $result->providerMessageId;
            $notification->last_error = null;
            $notification->save();

            $this->logEvent($notification, NotificationStatusEnum::Sent, [
                'provider_message_id' => $result->providerMessageId,
                'attempt' => $notification->attempts,
            ], $now);

            Log::channel('notifications')->info('notification.sent', [
                'event' => 'notification.sent',
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'priority' => $notification->priority->value,
                'attempt' => $notification->attempts,
                'provider_message_id' => $result->providerMessageId,
            ]);
        });

        if ($deferred !== null) {
            throw $deferred;
        }
    }

    public function markFailedFinal(string $notificationId, string $reason): void
    {
        DB::transaction(function () use ($notificationId, $reason): void {
            $notification = Notification::lockForUpdate()->find($notificationId);

            if ($notification === null || $notification->status === NotificationStatusEnum::Failed) {
                return;
            }

            $this->finalizeFailure($notification, $reason);
        });
    }

    private function finalizeFailure(Notification $notification, string $reason): void
    {
        $now = Carbon::now();
        $notification->status = NotificationStatusEnum::Failed;
        $notification->failed_at = $now;
        $notification->last_error = mb_substr($reason, 0, 65535);
        $notification->save();

        $this->logEvent($notification, NotificationStatusEnum::Failed, [
            'attempt' => $notification->attempts,
            'error' => $reason,
        ], $now);

        Log::channel('notifications')->error('notification.failed', [
            'event' => 'notification.failed',
            'notification_id' => $notification->id,
            'channel' => $notification->channel->value,
            'priority' => $notification->priority->value,
            'attempt' => $notification->attempts,
            'error' => $reason,
        ]);
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    private function logEvent(
        Notification $notification,
        NotificationStatusEnum $status,
        array $meta,
        ?Carbon $occurredAt = null,
    ): void {
        NotificationEvent::create([
            'notification_id' => $notification->id,
            'status' => $status->value,
            'meta' => $meta,
            'occurred_at' => $occurredAt ?? Carbon::now(),
        ]);
    }
}
