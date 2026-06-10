<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationStatusEnum;
use App\Models\Notification;
use App\Models\NotificationEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeliveryConfirmationService
{
    /**
     * Apply a provider callback advancing the notification's terminal state.
     * Idempotent: re-applying the same terminal status is a no-op, and the
     * service refuses to overwrite an already-terminal state.
     *
     * @param  array<string,mixed>  $meta
     */
    public function confirm(
        string $providerName,
        string $providerMessageId,
        NotificationStatusEnum $status,
        ?string $reason = null,
        array $meta = [],
        ?Carbon $occurredAt = null,
    ): ConfirmationOutcome {
        if (! in_array($status, [NotificationStatusEnum::Delivered, NotificationStatusEnum::Failed], true)) {
            throw new InvalidArgumentException('Callback status must be delivered or failed.');
        }

        return DB::transaction(function () use ($providerName, $providerMessageId, $status, $reason, $meta, $occurredAt): ConfirmationOutcome {
            $notification = Notification::where('provider_message_id', $providerMessageId)
                ->lockForUpdate()
                ->first();

            if ($notification === null) {
                return ConfirmationOutcome::NotFound;
            }

            if ($notification->status === $status) {
                return ConfirmationOutcome::AlreadyApplied;
            }

            if (in_array($notification->status, [NotificationStatusEnum::Delivered, NotificationStatusEnum::Failed], true)) {
                return ConfirmationOutcome::Conflict;
            }

            $now = $occurredAt ?? Carbon::now();
            $notification->status = $status;

            if ($status === NotificationStatusEnum::Delivered) {
                $notification->delivered_at = $now;
            } else {
                $notification->failed_at = $now;
                $notification->last_error = $reason !== null
                    ? mb_substr($reason, 0, 65535)
                    : 'provider callback reported failure';
            }
            $notification->save();

            NotificationEvent::create([
                'notification_id' => $notification->id,
                'status' => $status->value,
                'meta' => array_merge([
                    'source' => 'callback',
                    'provider' => $providerName,
                    'provider_message_id' => $providerMessageId,
                    'reason' => $reason,
                ], $meta),
                'occurred_at' => $now,
            ]);

            return ConfirmationOutcome::Applied;
        });
    }
}
