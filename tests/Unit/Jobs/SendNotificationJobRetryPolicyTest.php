<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SendNotificationJob;
use Tests\TestCase;

final class SendNotificationJobRetryPolicyTest extends TestCase
{
    public function test_tries_comes_from_config(): void
    {
        config()->set('notifications.send_job.tries', 7);

        self::assertSame(7, (new SendNotificationJob('id'))->tries());
    }

    public function test_backoff_array_length_matches_base_config(): void
    {
        config()->set('notifications.send_job.backoff', [1, 5, 30, 300]);
        config()->set('notifications.send_job.jitter', 0);

        self::assertSame(
            [1, 5, 30, 300],
            (new SendNotificationJob('id'))->backoff(),
        );
    }

    public function test_backoff_values_stay_within_jitter_band(): void
    {
        $base = [1, 5, 30, 300];
        $jitter = 0.25;

        config()->set('notifications.send_job.backoff', $base);
        config()->set('notifications.send_job.jitter', $jitter);

        $job = new SendNotificationJob('id');

        // 50 samples — non-deterministic, but the band check is strict so it
        // catches arithmetic mistakes without producing flakes.
        for ($i = 0; $i < 50; $i++) {
            $values = $job->backoff();

            self::assertCount(count($base), $values);

            foreach ($base as $idx => $expected) {
                $actual = $values[$idx];
                $low = max(1, (int) floor($expected - $expected * $jitter));
                $high = (int) ceil($expected + $expected * $jitter);

                self::assertGreaterThanOrEqual($low, $actual, "slot {$idx} below jitter band");
                self::assertLessThanOrEqual($high, $actual, "slot {$idx} above jitter band");
            }
        }
    }
}
