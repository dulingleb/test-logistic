<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationEvent>
 */
class NotificationEventFactory extends Factory
{
    protected $model = NotificationEvent::class;

    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'status' => NotificationStatus::Queued,
            'meta' => null,
            'occurred_at' => now(),
        ];
    }
}
