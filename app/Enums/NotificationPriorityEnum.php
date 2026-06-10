<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationPriorityEnum: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    public function queueName(): string
    {
        return match ($this) {
            self::Transactional => 'notifications.transactional',
            self::Marketing => 'notifications.marketing',
        };
    }
}
