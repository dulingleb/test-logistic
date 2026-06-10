<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationStatusEnum;
use App\Exceptions\PermanentProviderException;
use App\Jobs\DeadLetterNotificationJob;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBulk;
use App\Models\NotificationEvent;
use App\Services\Notifications\NotificationSender;
use App\Services\Notifications\Providers\NotificationProvider;
use App\Services\Notifications\Providers\ProviderRegistry;
use App\Services\Notifications\Providers\ProviderSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class RetryAndDlqTest extends TestCase
{
    use RefreshDatabase;

    private function bindEmailProvider(NotificationProvider $provider): void
    {
        $this->app->instance(ProviderRegistry::class, new ProviderRegistry([$provider]));
    }

    private function makeQueuedEmail(): Notification
    {
        $bulk = NotificationBulk::factory()->email()->transactional()->create([
            'message' => 'retry test',
            'recipients_count' => 1,
        ]);

        return Notification::factory()->create([
            'bulk_id' => $bulk->id,
            'recipient_id' => 'retry@example.com',
            'channel' => NotificationChannelEnum::Email,
            'status' => NotificationStatusEnum::Queued,
            'attempts' => 0,
        ]);
    }

    public function test_transient_failure_increments_attempts_logs_retry_event_and_rethrows(): void
    {
        $notification = $this->makeQueuedEmail();

        $this->bindEmailProvider(new class implements NotificationProvider {
            public function channel(): NotificationChannelEnum
            {
                return NotificationChannelEnum::Email;
            }

            public function send($notification, string $message): ProviderSendResult
            {
                throw new RuntimeException('temporary outage');
            }
        });

        $sender = $this->app->make(NotificationSender::class);

        $caught = null;
        try {
            $sender->send($notification->id);
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'sender must re-throw transient errors so the queue worker retries');
        self::assertInstanceOf(RuntimeException::class, $caught);

        $notification->refresh();
        self::assertSame(NotificationStatusEnum::Queued, $notification->status);
        self::assertSame(1, $notification->attempts);
        self::assertStringContainsString('temporary outage', $notification->last_error);

        $events = NotificationEvent::where('notification_id', $notification->id)->orderBy('id')->get();
        self::assertCount(1, $events);
        self::assertSame('queued', $events->first()->status->value);
        self::assertSame('retry', $events->first()->meta['phase']);
        self::assertSame(1, $events->first()->meta['attempt']);
    }

    public function test_permanent_failure_marks_failed_immediately_and_rethrows_to_caller(): void
    {
        $notification = $this->makeQueuedEmail();

        $this->bindEmailProvider(new class implements NotificationProvider {
            public function channel(): NotificationChannelEnum
            {
                return NotificationChannelEnum::Email;
            }

            public function send($notification, string $message): ProviderSendResult
            {
                throw new PermanentProviderException('hard bounce');
            }
        });

        $sender = $this->app->make(NotificationSender::class);

        $this->expectException(PermanentProviderException::class);
        try {
            $sender->send($notification->id);
        } finally {
            $notification->refresh();
            self::assertSame(NotificationStatusEnum::Failed, $notification->status);
            self::assertSame(1, $notification->attempts);
            self::assertNotNull($notification->failed_at);
            self::assertSame('hard bounce', $notification->last_error);
            self::assertSame('failed', NotificationEvent::where('notification_id', $notification->id)
                ->latest('id')->first()->status->value);
        }
    }

    public function test_exhausted_retries_via_failed_hook_finalize_failed_and_push_to_dlq(): void
    {
        Bus::fake();

        $notification = $this->makeQueuedEmail();
        $notification->attempts = 5;
        $notification->save();

        $job = new SendNotificationJob($notification->id);
        $job->failed(new RuntimeException('all retries exhausted'));

        $notification->refresh();
        self::assertSame(NotificationStatusEnum::Failed, $notification->status);
        self::assertNotNull($notification->failed_at);
        self::assertSame('all retries exhausted', $notification->last_error);

        $event = NotificationEvent::where('notification_id', $notification->id)->latest('id')->first();
        self::assertSame('failed', $event->status->value);

        Bus::assertDispatched(
            DeadLetterNotificationJob::class,
            fn (DeadLetterNotificationJob $j) => $j->notificationId === $notification->id
                && $j->reason === 'all retries exhausted'
                && ($j->context['exception'] ?? null) === RuntimeException::class,
        );
    }

    public function test_failed_hook_routes_dlq_to_configured_queue(): void
    {
        Bus::fake();
        config()->set('notifications.dead_letter_queue', 'custom.dlq');

        $notification = $this->makeQueuedEmail();
        (new SendNotificationJob($notification->id))->failed(new RuntimeException('boom'));

        Bus::assertDispatched(DeadLetterNotificationJob::class, function (DeadLetterNotificationJob $j) {
            return $j->queue === 'custom.dlq';
        });
    }

    public function test_sender_is_idempotent_when_status_is_no_longer_queued(): void
    {
        $notification = $this->makeQueuedEmail();
        $notification->status = NotificationStatusEnum::Sent;
        $notification->save();

        $callCount = 0;
        $this->bindEmailProvider(new class($callCount) implements NotificationProvider {
            public function __construct(private int &$callCount) {}

            public function channel(): NotificationChannelEnum
            {
                return NotificationChannelEnum::Email;
            }

            public function send($notification, string $message): ProviderSendResult
            {
                $this->callCount++;

                return new ProviderSendResult('should-not-happen');
            }
        });

        $this->app->make(NotificationSender::class)->send($notification->id);

        self::assertSame(0, $callCount, 'sender must skip notifications that are not in Queued state');
    }
}
