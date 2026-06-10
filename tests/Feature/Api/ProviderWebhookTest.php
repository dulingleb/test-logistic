<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationStatusEnum;
use App\Models\Notification;
use App\Models\NotificationBulk;
use App\Models\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProviderWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PROVIDER = 'email-stub';

    private function makeSentEmail(string $providerMessageId = 'email_pm_1'): Notification
    {
        $bulk = NotificationBulk::factory()->email()->create();

        return Notification::factory()->sent()->create([
            'bulk_id' => $bulk->id,
            'recipient_id' => 'wh@example.com',
            'channel' => NotificationChannelEnum::Email,
            'provider_message_id' => $providerMessageId,
        ]);
    }

    private function endpoint(string $provider = self::PROVIDER): string
    {
        return "/api/notifications/webhooks/{$provider}";
    }

    public function test_delivered_callback_advances_status_and_logs_event(): void
    {
        $n = $this->makeSentEmail();

        $this->postJson($this->endpoint(), [
            'provider_message_id' => $n->provider_message_id,
            'status' => 'delivered',
            'meta' => ['received_by' => 'test'],
        ])->assertStatus(200)->assertJsonPath('outcome', 'applied');

        $n->refresh();
        self::assertSame(NotificationStatusEnum::Delivered, $n->status);
        self::assertNotNull($n->delivered_at);

        $event = NotificationEvent::where('notification_id', $n->id)->latest('id')->first();
        self::assertSame('delivered', $event->status->value);
        self::assertSame('callback', $event->meta['source']);
        self::assertSame(self::PROVIDER, $event->meta['provider']);
        self::assertSame('test', $event->meta['received_by']);
    }

    public function test_replaying_same_terminal_status_is_idempotent_already_applied(): void
    {
        $n = $this->makeSentEmail();

        $body = ['provider_message_id' => $n->provider_message_id, 'status' => 'delivered'];

        $this->postJson($this->endpoint(), $body)->assertStatus(200);

        $this->postJson($this->endpoint(), $body)
            ->assertStatus(200)
            ->assertJsonPath('outcome', 'already_applied');

        self::assertSame(
            1,
            NotificationEvent::where('notification_id', $n->id)
                ->where('status', NotificationStatusEnum::Delivered->value)
                ->count(),
            'second callback must not insert a duplicate delivered event',
        );
    }

    public function test_conflicting_terminal_transition_returns_409(): void
    {
        $n = $this->makeSentEmail();

        $this->postJson($this->endpoint(), [
            'provider_message_id' => $n->provider_message_id,
            'status' => 'delivered',
        ])->assertStatus(200);

        $this->postJson($this->endpoint(), [
            'provider_message_id' => $n->provider_message_id,
            'status' => 'failed',
            'reason' => 'too late',
        ])->assertStatus(409)->assertJsonPath('outcome', 'conflict');

        $n->refresh();
        self::assertSame(NotificationStatusEnum::Delivered, $n->status);
    }

    public function test_unknown_provider_message_id_returns_404(): void
    {
        $this->postJson($this->endpoint(), [
            'provider_message_id' => 'no_such_id',
            'status' => 'delivered',
        ])->assertStatus(404)->assertJsonPath('outcome', 'not_found');
    }

    public function test_invalid_status_is_rejected_by_validation(): void
    {
        $n = $this->makeSentEmail();

        $this->postJson($this->endpoint(), [
            'provider_message_id' => $n->provider_message_id,
            'status' => 'queued',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_webhook_requires_secret_when_configured(): void
    {
        config()->set('notifications.webhook_secret', 's3cr3t');
        $n = $this->makeSentEmail();

        $this->postJson($this->endpoint(), [
            'provider_message_id' => $n->provider_message_id,
            'status' => 'delivered',
        ])->assertStatus(403);

        $this->withHeaders(['X-Webhook-Secret' => 's3cr3t'])
            ->postJson($this->endpoint(), [
                'provider_message_id' => $n->provider_message_id,
                'status' => 'delivered',
            ])->assertStatus(200);
    }
}
