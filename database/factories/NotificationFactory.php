<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
use App\Enums\NotificationStatusEnum;
use App\Models\Notification;
use App\Models\NotificationBulk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $channel = fake()->randomElement(NotificationChannelEnum::cases());
        $recipient = $channel === NotificationChannelEnum::Sms
            ? fake()->e164PhoneNumber()
            : fake()->safeEmail();

        return [
            'bulk_id' => NotificationBulk::factory(),
            'recipient_id' => $recipient,
            'channel' => $channel,
            'priority' => NotificationPriorityEnum::Marketing,
            'status' => NotificationStatusEnum::Queued,
            'attempts' => 0,
            'last_error' => null,
            'payload' => null,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatusEnum::Sent,
            'sent_at' => now(),
        ]);
    }

    public function delivered(): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatusEnum::Delivered,
            'sent_at' => now()->subSecond(),
            'delivered_at' => now(),
        ]);
    }

    public function failed(string $error = 'provider error'): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatusEnum::Failed,
            'failed_at' => now(),
            'last_error' => $error,
        ]);
    }
}
