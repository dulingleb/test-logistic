<?php

declare(strict_types=1);

namespace App\Services\Notifications;

enum ConfirmationOutcome: string
{
    case Applied = 'applied';
    case AlreadyApplied = 'already_applied';
    case Conflict = 'conflict';
    case NotFound = 'not_found';
}
