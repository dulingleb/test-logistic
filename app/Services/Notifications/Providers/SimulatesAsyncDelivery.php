<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationStatusEnum;
use App\Jobs\ConfirmDeliveryJob;

trait SimulatesAsyncDelivery
{
    private function scheduleDeliveryCallback(string $channel, string $providerMessageId): void
    {
        if (! (bool) config('notifications.delivery_callback.enabled', false)) {
            return;
        }

        $minSec = (int) config('notifications.delivery_callback.delay_seconds.min', 1);
        $maxSec = (int) config('notifications.delivery_callback.delay_seconds.max', 10);
        if ($maxSec < $minSec) {
            $maxSec = $minSec;
        }

        $failureRate = (float) config('notifications.delivery_callback.failure_rate', 0);
        $isFailure = $failureRate > 0 && (mt_rand(0, 9999) / 10000) < $failureRate;

        ConfirmDeliveryJob::dispatch(
            providerName: $channel.'-stub',
            providerMessageId: $providerMessageId,
            status: $isFailure ? NotificationStatusEnum::Failed->value : NotificationStatusEnum::Delivered->value,
            reason: $isFailure ? 'simulated delivery failure callback' : null,
        )
            ->onQueue('notifications.transactional')
            ->delay(now()->addSeconds(mt_rand($minSec, $maxSec)));
    }
}
