<?php

declare(strict_types=1);

namespace Tests\Feature\Pipeline;

use App\Enums\NotificationStatusEnum;
use App\Models\Notification;
use App\Models\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The phpunit.xml config sets QUEUE_CONNECTION=sync, so SendNotificationJob
 * executes inline after the controller's DB transaction commits. This lets
 * us verify the full pipeline (controller -> dispatcher -> job -> sender ->
 * provider stub -> DB) in a single HTTP test, without standing up a broker.
 */
final class SyncDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.provider_failure_rate.permanent', 0);
        config()->set('notifications.provider_failure_rate.transient', 0);
        config()->set('notifications.delivery_callback.enabled', false);
    }

    public function test_email_recipients_reach_sent_state_after_sync_pipeline_runs(): void
    {
        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'pipeline test',
            'recipients' => ['p1@example.com', 'p2@example.com'],
        ]);

        $response->assertStatus(202);

        $notifications = Notification::all();
        self::assertCount(2, $notifications);

        foreach ($notifications as $n) {
            self::assertSame(NotificationStatusEnum::Sent, $n->status);
            self::assertSame(1, $n->attempts);
            self::assertNotNull($n->sent_at);
            self::assertNull($n->failed_at);
            self::assertNotNull($n->provider_message_id);
            self::assertStringStartsWith('email_', $n->provider_message_id);

            $events = NotificationEvent::where('notification_id', $n->id)
                ->orderBy('occurred_at')->orderBy('id')
                ->get()
                ->map(fn ($e) => $e->status->value)
                ->all();

            self::assertSame(['queued', 'sent'], $events);
        }
    }

    public function test_sms_recipients_use_sms_provider_stub(): void
    {
        $this->postJson('/api/notifications/bulk', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'sms pipeline test',
            'recipients' => ['+19990001111'],
        ])->assertStatus(202);

        $n = Notification::first();
        self::assertSame(NotificationStatusEnum::Sent, $n->status);
        self::assertStringStartsWith('sms_', $n->provider_message_id);
    }
}
