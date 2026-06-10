<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Models\NotificationBulk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationBulk>
 */
class NotificationBulkFactory extends Factory
{
    protected $model = NotificationBulk::class;

    public function definition(): array
    {
        return [
            'idempotency_key' => null,
            'channel' => fake()->randomElement(NotificationChannel::cases()),
            'priority' => NotificationPriority::Marketing,
            'message' => fake()->sentence(),
            'recipients_count' => 1,
        ];
    }

    public function transactional(): self
    {
        return $this->state(fn () => ['priority' => NotificationPriority::Transactional]);
    }

    public function sms(): self
    {
        return $this->state(fn () => ['channel' => NotificationChannel::Sms]);
    }

    public function email(): self
    {
        return $this->state(fn () => ['channel' => NotificationChannel::Email]);
    }
}
