<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

trait SimulatesLatency
{
    private function maybeDelay(): void
    {
        $minMs = (int) config('notifications.provider_delay_ms.min', 0);
        $maxMs = (int) config('notifications.provider_delay_ms.max', 0);

        if ($maxMs <= 0) {
            return;
        }
        if ($minMs < 0) {
            $minMs = 0;
        }
        if ($minMs > $maxMs) {
            $minMs = $maxMs;
        }

        usleep(mt_rand($minMs, $maxMs) * 1000);
    }
}
