<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannelEnum;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class EmailProvider implements NotificationProvider
{
    public function channel(): NotificationChannelEnum
    {
        return NotificationChannelEnum::Email;
    }

    public function send(Notification $notification, string $message): ProviderSendResult
    {
        $providerMessageId = 'email_'.Str::uuid()->toString();

        Log::info('email provider stub send', [
            'notification_id' => $notification->id,
            'to' => $notification->recipient_id,
            'message' => $message,
            'provider_message_id' => $providerMessageId,
        ]);

        return new ProviderSendResult($providerMessageId);
    }
}
