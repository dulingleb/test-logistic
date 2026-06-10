<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannelEnum;
use App\Models\Notification;

interface NotificationProvider
{
    public function channel(): NotificationChannelEnum;

    public function send(Notification $notification, string $message): ProviderSendResult;
}
