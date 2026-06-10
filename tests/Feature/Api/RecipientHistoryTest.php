<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\NotificationChannelEnum;
use App\Models\Notification;
use App\Models\NotificationBulk;
use App\Models\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecipientHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_notifications_for_requested_recipient(): void
    {
        $bulk = NotificationBulk::factory()->email()->create();

        Notification::factory()->count(2)->create([
            'bulk_id' => $bulk->id,
            'recipient_id' => 'alice@example.com',
            'channel' => NotificationChannelEnum::Email,
        ]);
        Notification::factory()->create([
            'bulk_id' => $bulk->id,
            'recipient_id' => 'bob@example.com',
            'channel' => NotificationChannelEnum::Email,
        ]);

        $response = $this->getJson('/api/notifications/by-recipient/alice@example.com');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);

        foreach ($response->json('data') as $row) {
            self::assertSame('alice@example.com', $row['recipient_id']);
        }
    }

    public function test_includes_event_timeline_in_payload(): void
    {
        $bulk = NotificationBulk::factory()->email()->create();
        $n = Notification::factory()->sent()->create([
            'bulk_id' => $bulk->id,
            'recipient_id' => 'carol@example.com',
            'channel' => NotificationChannelEnum::Email,
            'provider_message_id' => 'email_pm_test',
        ]);

        NotificationEvent::factory()->create([
            'notification_id' => $n->id,
            'status' => 'queued',
            'occurred_at' => now()->subSeconds(2),
        ]);
        NotificationEvent::factory()->create([
            'notification_id' => $n->id,
            'status' => 'sent',
            'meta' => ['attempt' => 1, 'provider_message_id' => 'email_pm_test'],
            'occurred_at' => now(),
        ]);

        $response = $this->getJson('/api/notifications/by-recipient/carol@example.com');

        $statuses = array_column($response->json('data.0.events'), 'status');
        self::assertSame(['queued', 'sent'], $statuses);
    }

    public function test_pagination_per_page_is_clamped_between_1_and_200(): void
    {
        $bulk = NotificationBulk::factory()->email()->create();
        Notification::factory()->count(5)->create([
            'bulk_id' => $bulk->id,
            'recipient_id' => 'paged@example.com',
            'channel' => NotificationChannelEnum::Email,
        ]);

        $this->getJson('/api/notifications/by-recipient/paged@example.com?per_page=2')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/notifications/by-recipient/paged@example.com?per_page=99999')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 200);

        $this->getJson('/api/notifications/by-recipient/paged@example.com?per_page=0')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 1);
    }
}
