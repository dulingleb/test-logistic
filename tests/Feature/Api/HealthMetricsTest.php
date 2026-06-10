<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use App\Enums\NotificationStatusEnum;
use App\Models\Notification;
use App\Models\NotificationBulk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_emits_prometheus_text_with_counters_by_status_channel_priority(): void
    {
        $bulk = NotificationBulk::factory()->email()->transactional()->create();

        Notification::factory()->count(2)->sent()->create([
            'bulk_id' => $bulk->id,
            'channel' => NotificationChannelEnum::Email,
            'priority' => NotificationPriorityEnum::Transactional,
            'attempts' => 1,
        ]);
        Notification::factory()->failed()->create([
            'bulk_id' => $bulk->id,
            'channel' => NotificationChannelEnum::Email,
            'priority' => NotificationPriorityEnum::Transactional,
            'attempts' => 5,
        ]);

        $response = $this->get('/api/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $body = $response->getContent();
        self::assertStringContainsString('# TYPE notifications_total counter', $body);
        self::assertStringContainsString(
            'notifications_total{status="sent",channel="email",priority="transactional"} 2',
            $body,
        );
        self::assertStringContainsString(
            'notifications_total{status="failed",channel="email",priority="transactional"} 1',
            $body,
        );
        self::assertStringContainsString('notifications_attempts_total{status="sent"} 2', $body);
        self::assertStringContainsString('notifications_attempts_total{status="failed"} 5', $body);
    }

    public function test_metrics_returns_only_help_and_type_when_no_notifications_exist(): void
    {
        $response = $this->get('/api/metrics');

        $response->assertStatus(200);
        $body = $response->getContent();
        self::assertStringContainsString('# HELP notifications_total', $body);
        self::assertStringContainsString('# HELP notifications_attempts_total', $body);
    }

    public function test_metrics_includes_marketing_and_sms_buckets_with_correct_counts(): void
    {
        $bulk = NotificationBulk::factory()->sms()->create([
            'priority' => NotificationPriorityEnum::Marketing,
        ]);

        Notification::factory()->count(3)->create([
            'bulk_id' => $bulk->id,
            'channel' => NotificationChannelEnum::Sms,
            'priority' => NotificationPriorityEnum::Marketing,
            'status' => NotificationStatusEnum::Queued,
        ]);

        $body = $this->get('/api/metrics')->getContent();

        self::assertStringContainsString(
            'notifications_total{status="queued",channel="sms",priority="marketing"} 3',
            $body,
        );
    }
}
