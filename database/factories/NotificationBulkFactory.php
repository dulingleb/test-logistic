<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationPriorityEnum;
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
            'channel' => fake()->randomElement(NotificationChannelEnum::cases()),
            'priority' => NotificationPriorityEnum::Marketing,
            'message' => fake()->sentence(),
            'recipients_count' => 1,
        ];
    }

    public function transactional(): self
    {
        return $this->state(fn () => ['priority' => NotificationPriorityEnum::Transactional]);
    }

    public function sms(): self
    {
        return $this->state(fn () => ['channel' => NotificationChannelEnum::Sms]);
    }

    public function email(): self
    {
        return $this->state(fn () => ['channel' => NotificationChannelEnum::Email]);
    }
}
