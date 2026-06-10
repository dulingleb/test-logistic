<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\NotificationPriorityEnum;
use PHPUnit\Framework\TestCase;

final class NotificationPriorityEnumTest extends TestCase
{
    public function test_transactional_maps_to_its_own_queue(): void
    {
        self::assertSame('notifications.transactional', NotificationPriorityEnum::Transactional->queueName());
    }

    public function test_marketing_maps_to_its_own_queue(): void
    {
        self::assertSame('notifications.marketing', NotificationPriorityEnum::Marketing->queueName());
    }

    public function test_each_priority_routes_to_a_distinct_queue(): void
    {
        $queues = array_map(
            static fn (NotificationPriorityEnum $p) => $p->queueName(),
            NotificationPriorityEnum::cases(),
        );

        self::assertSame(count($queues), count(array_unique($queues)));
    }
}
