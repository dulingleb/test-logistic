<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use App\Enums\NotificationStatusEnum;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBulk;
use App\Models\NotificationEvent;
use Illuminate\Support\Facades\Artisan;

/**
 * Proves that a worker watching both notification queues drains
 * notifications.transactional before notifications.marketing, even when
 * marketing jobs are published first. This is the queue-priority
 * invariant the API contract relies on.
 *
 * Requires real RabbitMQ + Postgres from docker-compose.
 */
final class PriorityOrderingTest extends IntegrationTestCase
{
    public function test_transactional_jobs_drain_before_marketing_under_a_single_worker(): void
    {
        $marketingCount = 5;
        $transactionalCount = 3;

        $marketingIds = $this->seedQueuedNotifications(
            count: $marketingCount,
            priority: NotificationPriorityEnum::Marketing,
            queue: 'notifications.marketing',
            recipientPrefix: 'mkt',
        );

        $transactionalIds = $this->seedQueuedNotifications(
            count: $transactionalCount,
            priority: NotificationPriorityEnum::Transactional,
            queue: 'notifications.transactional',
            recipientPrefix: 'tx',
        );

        $totalJobs = $marketingCount + $transactionalCount;
        for ($i = 0; $i < $totalJobs; $i++) {
            Artisan::call('queue:work', [
                '--queue' => 'notifications.transactional,notifications.marketing',
                '--once' => true,
                '--sleep' => 0,
                '--tries' => 1,
            ]);
        }

        $sentOrder = NotificationEvent::query()
            ->where('status', NotificationStatusEnum::Sent->value)
            ->orderBy('id')
            ->pluck('notification_id')
            ->all();

        self::assertCount(
            $totalJobs,
            $sentOrder,
            'every queued notification should have produced a Sent event',
        );

        $firstChunk = array_slice($sentOrder, 0, $transactionalCount);
        $lastChunk = array_slice($sentOrder, $transactionalCount);

        self::assertEqualsCanonicalizing(
            $transactionalIds,
            $firstChunk,
            'all transactional notifications must be processed before any marketing one',
        );
        self::assertEqualsCanonicalizing(
            $marketingIds,
            $lastChunk,
            'remaining slots must be filled by the marketing notifications',
        );

        self::assertSame(
            $totalJobs,
            Notification::where('status', NotificationStatusEnum::Sent)->count(),
        );
    }

    /**
     * @return array<int,string>
     */
    private function seedQueuedNotifications(
        int $count,
        NotificationPriorityEnum $priority,
        string $queue,
        string $recipientPrefix,
    ): array {
        $bulk = NotificationBulk::factory()->create([
            'channel' => NotificationChannelEnum::Email,
            'priority' => $priority,
            'message' => "{$recipientPrefix} message",
            'recipients_count' => $count,
        ]);

        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $n = Notification::factory()->create([
                'bulk_id' => $bulk->id,
                'recipient_id' => "{$recipientPrefix}-{$i}@example.com",
                'channel' => NotificationChannelEnum::Email,
                'priority' => $priority,
                'status' => NotificationStatusEnum::Queued,
                'attempts' => 0,
            ]);
            $ids[] = $n->id;

            SendNotificationJob::dispatch($n->id)->onQueue($queue);
        }

        return $ids;
    }
}
