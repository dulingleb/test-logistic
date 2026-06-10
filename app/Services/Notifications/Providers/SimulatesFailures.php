<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Exceptions\PermanentProviderException;
use RuntimeException;

trait SimulatesFailures
{
    private function maybeFail(string $channel): void
    {
        $permanent = (float) config('notifications.provider_failure_rate.permanent', 0);
        $transient = (float) config('notifications.provider_failure_rate.transient', 0);

        if ($permanent <= 0 && $transient <= 0) {
            return;
        }

        $roll = mt_rand(0, 9999) / 10000;

        if ($roll < $permanent) {
            throw new PermanentProviderException("{$channel} provider: simulated permanent failure");
        }

        if ($roll < $permanent + $transient) {
            throw new RuntimeException("{$channel} provider: simulated transient failure");
        }
    }
}
