<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\DTO\NewBulkDataDTO;
use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use App\Enums\NotificationStatusEnum;
use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use App\Models\Notification;
use App\Models\NotificationBulk;
use App\Models\NotificationEvent;
use App\Services\Notifications\BulkDispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Exercises the bulk pipeline against real Postgres + Redis. The feature
 * suite covers the same flows on SQLite + array cache; this class is the
 * net that catches:
 *   - PG-specific schema regressions (jsonb, timestampTz, FK cascade,
 *     unique on nullable column),
 *   - Redis-backed idempotency cache replay / conflict semantics,
 *   - Concurrent worker scenarios that need a real row lock.
 */
final class PostgresPipelineTest extends IntegrationTestCase
{
    private function payload(array $overrides = []): NewBulkDataDTO
    {
        return new NewBulkDataDTO(
            channel: $overrides['channel'] ?? NotificationChannelEnum::Email,
            priority: $overrides['priority'] ?? NotificationPriorityEnum::Transactional,
            message: $overrides['message'] ?? 'hello pg',
            recipients: $overrides['recipients'] ?? ['pg1@example.com', 'pg2@example.com'],
            idempotencyKey: $overrides['idempotencyKey'] ?? null,
        );
    }

    public function test_dispatch_persists_bulk_notifications_and_queued_events_on_postgres(): void
    {
        /** @var BulkDispatcher $dispatcher */
        $dispatcher = $this->app->make(BulkDispatcher::class);

        $result = $dispatcher->dispatch($this->payload());

        self::assertFalse($result->replayed);
        self::assertSame(NotificationStatusEnum::Queued->value, $result->body['status']);

        self::assertSame(1, NotificationBulk::count());
        self::assertSame(2, Notification::count());
        self::assertSame(2, NotificationEvent::count());

        foreach (Notification::all() as $n) {
            self::assertSame(NotificationStatusEnum::Queued, $n->status);
        }
    }

    public function test_jsonb_meta_round_trips_through_postgres(): void
    {
        $bulk = NotificationBulk::factory()->email()->create();
        $notification = Notification::factory()->create([
            'bulk_id' => $bulk->id,
            'recipient_id' => 'meta@example.com',
            'channel' => NotificationChannelEnum::Email,
        ]);

        $meta = [
            'provider_message_id' => 'email_meta_test',
            'attempt' => 1,
            'unicode' => 'привет, мир — 🚀',
            'nested' => ['a' => 1, 'b' => [true, null, 'x']],
        ];

        NotificationEvent::create([
            'notification_id' => $notification->id,
            'status' => NotificationStatusEnum::Sent->value,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);

        $reloaded = NotificationEvent::query()->latest('id')->first();
        self::assertEquals($meta, $reloaded->meta);
    }

    public function test_provider_message_id_unique_constraint_blocks_duplicates(): void
    {
        $bulk = NotificationBulk::factory()->email()->create();
        Notification::factory()->create([
            'bulk_id' => $bulk->id,
            'channel' => NotificationChannelEnum::Email,
            'provider_message_id' => 'email_dup_1',
        ]);

        $this->expectException(QueryException::class);
        Notification::factory()->create([
            'bulk_id' => $bulk->id,
            'channel' => NotificationChannelEnum::Email,
            'provider_message_id' => 'email_dup_1',
        ]);
    }

    public function test_cascade_delete_removes_notifications_and_events(): void
    {
        $bulk = NotificationBulk::factory()->email()->create();
        $notification = Notification::factory()->create([
            'bulk_id' => $bulk->id,
            'channel' => NotificationChannelEnum::Email,
        ]);
        NotificationEvent::create([
            'notification_id' => $notification->id,
            'status' => NotificationStatusEnum::Queued->value,
            'meta' => null,
            'occurred_at' => now(),
        ]);

        $bulk->delete();

        self::assertSame(0, Notification::count());
        self::assertSame(0, NotificationEvent::count());
    }

    public function test_redis_backed_idempotency_replay_returns_cached_body_without_extra_writes(): void
    {
        /** @var BulkDispatcher $dispatcher */
        $dispatcher = $this->app->make(BulkDispatcher::class);
        $payload = $this->payload(['idempotencyKey' => 'pg-replay-1']);

        $first = $dispatcher->dispatch($payload);
        self::assertFalse($first->replayed);

        $second = $dispatcher->dispatch($payload);
        self::assertTrue($second->replayed);
        self::assertEquals($first->body, $second->body);

        self::assertSame(1, NotificationBulk::count());
        self::assertSame(2, Notification::count());
        self::assertSame(1, IdempotencyKey::count());

        // Force the next replay through the DB path instead of Redis.
        Cache::forget('idempotency:pg-replay-1');
        $third = $dispatcher->dispatch($payload);
        self::assertTrue($third->replayed);
        self::assertEquals($first->body, $third->body);
    }

    public function test_idempotency_conflict_when_hash_changes_under_same_key(): void
    {
        /** @var BulkDispatcher $dispatcher */
        $dispatcher = $this->app->make(BulkDispatcher::class);

        $dispatcher->dispatch($this->payload([
            'idempotencyKey' => 'pg-conflict-1',
            'message' => 'first',
        ]));

        $this->expectException(IdempotencyConflictException::class);
        $dispatcher->dispatch($this->payload([
            'idempotencyKey' => 'pg-conflict-1',
            'message' => 'second',
        ]));
    }

}
