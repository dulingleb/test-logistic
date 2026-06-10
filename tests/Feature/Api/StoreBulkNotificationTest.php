<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\NotificationStatusEnum;
use App\Jobs\SendNotificationJob;
use App\Models\IdempotencyKey;
use App\Models\Notification;
use App\Models\NotificationBulk;
use App\Models\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class StoreBulkNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/notifications/bulk';

    public function test_happy_path_persists_bulk_notifications_events_and_dispatches_one_job_per_recipient(): void
    {
        Queue::fake();

        $response = $this->postJson(self::ENDPOINT, [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'hello world',
            'recipients' => ['a@example.com', 'b@example.com', 'c@example.com'],
        ]);

        $response->assertStatus(202);
        $response->assertHeader('Idempotent-Replayed', 'false');
        $response->assertJsonStructure([
            'bulk_id', 'channel', 'priority', 'recipients_count', 'status', 'accepted_at',
        ]);

        self::assertSame(1, NotificationBulk::count());
        self::assertSame(3, Notification::count());
        self::assertSame(3, NotificationEvent::count());

        $bulk = NotificationBulk::first();
        self::assertSame(3, $bulk->recipients_count);
        self::assertSame('hello world', $bulk->message);

        foreach (Notification::all() as $n) {
            self::assertSame(NotificationStatusEnum::Queued, $n->status);
            self::assertSame(0, $n->attempts);
            self::assertNotNull($n->queued_at);
        }

        Queue::assertPushed(SendNotificationJob::class, 3);
        Queue::assertPushedOn('notifications.transactional', SendNotificationJob::class);
    }

    public function test_marketing_priority_routes_jobs_to_marketing_queue(): void
    {
        Queue::fake();

        $this->postJson(self::ENDPOINT, [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'newsletter',
            'recipients' => ['m@example.com'],
        ])->assertStatus(202);

        Queue::assertPushedOn('notifications.marketing', SendNotificationJob::class);
    }

    public function test_idempotent_replay_returns_cached_response_without_extra_writes(): void
    {
        Queue::fake();

        $body = [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'hi',
            'recipients' => ['repeat@example.com'],
        ];
        $headers = ['Idempotency-Key' => 'replay-key-1'];

        $first = $this->postJson(self::ENDPOINT, $body, $headers);
        $first->assertStatus(202)->assertHeader('Idempotent-Replayed', 'false');

        $second = $this->postJson(self::ENDPOINT, $body, $headers);
        $second->assertStatus(200)->assertHeader('Idempotent-Replayed', 'true');
        self::assertSame($first->json(), $second->json());

        self::assertSame(1, NotificationBulk::count());
        self::assertSame(1, Notification::count());
        self::assertSame(1, IdempotencyKey::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_idempotency_conflict_when_same_key_used_with_different_payload(): void
    {
        Queue::fake();

        $headers = ['Idempotency-Key' => 'conflict-key'];

        $this->postJson(self::ENDPOINT, [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'first',
            'recipients' => ['x@example.com'],
        ], $headers)->assertStatus(202);

        $second = $this->postJson(self::ENDPOINT, [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'second',
            'recipients' => ['x@example.com'],
        ], $headers);

        $second->assertStatus(409)->assertJsonPath('error', 'idempotency_conflict');

        self::assertSame(1, NotificationBulk::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_validation_rejects_invalid_email_recipients(): void
    {
        Queue::fake();

        $this->postJson(self::ENDPOINT, [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'hi',
            'recipients' => ['not-an-email'],
        ])->assertStatus(422)->assertJsonValidationErrors(['recipients.0']);

        Queue::assertNothingPushed();
    }

    public function test_validation_rejects_invalid_sms_recipients(): void
    {
        Queue::fake();

        $this->postJson(self::ENDPOINT, [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'hi',
            'recipients' => ['12345'],
        ])->assertStatus(422)->assertJsonValidationErrors(['recipients.0']);
    }

    public function test_validation_rejects_unknown_channel_and_priority(): void
    {
        Queue::fake();

        $this->postJson(self::ENDPOINT, [
            'channel' => 'pigeon',
            'priority' => 'urgent',
            'message' => 'hi',
            'recipients' => ['x@example.com'],
        ])->assertStatus(422)->assertJsonValidationErrors(['channel', 'priority']);
    }

    public function test_recipients_limit_is_enforced(): void
    {
        Queue::fake();

        $this->postJson(self::ENDPOINT, [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'hi',
            'recipients' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['recipients']);
    }
}
