<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationPriority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';
}
